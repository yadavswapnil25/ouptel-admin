<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\Page;
use App\Models\JobApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JobsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $type = $request->query('type', 'all');
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $query = Job::query();

        if ($type === 'my_jobs') {
            // Filter jobs by the authenticated user
            // Check if user_id or user column exists
            // Handle both string and integer types
            $tokenUserIdStr = (string) $tokenUserId;
            $tokenUserIdInt = (int) $tokenUserId;
            
            if (Schema::hasColumn('Wo_Job', 'user_id')) {
                // Try both string and integer matching (handle type mismatch)
                $query->where(function ($q) use ($tokenUserIdStr, $tokenUserIdInt) {
                    $q->where('user_id', $tokenUserIdStr)
                      ->orWhere('user_id', $tokenUserIdInt);
                });
            } elseif (Schema::hasColumn('Wo_Job', 'user')) {
                // Try both string and integer matching (handle type mismatch)
                $query->where(function ($q) use ($tokenUserIdStr, $tokenUserIdInt) {
                    $q->where('user', $tokenUserIdStr)
                      ->orWhere('user', $tokenUserIdInt);
                });
            } else {
                // If neither column exists, return empty results
                $query->where('id', 0);
            }
        } elseif ($type === 'applied_jobs') {
            // Filter jobs that the user has applied to
            if (Schema::hasTable('Wo_Job_Apply') || Schema::hasTable('Wo_JobApplications')) {
                $applyTable = Schema::hasTable('Wo_Job_Apply') ? 'Wo_Job_Apply' : 'Wo_JobApplications';
                $jobIdColumn = Schema::hasColumn($applyTable, 'job_id') ? 'job_id' : 'job';
                $userIdColumn = Schema::hasColumn($applyTable, 'user_id') ? 'user_id' : 'user';
                
                $appliedJobIds = DB::table($applyTable)
                    ->where($userIdColumn, $tokenUserId)
                    ->pluck($jobIdColumn)
                    ->toArray();
                
                if (!empty($appliedJobIds)) {
                    $query->whereIn('id', $appliedJobIds);
                } else {
                    // User hasn't applied to any jobs
                    $query->where('id', 0);
                }
            } else {
                // Table doesn't exist, return empty results
                $query->where('id', 0);
            }
        } elseif ($type === 'saved_jobs') {
            // Note: Wo_JobApplication table might not exist
            // Return empty results for now
            $query->where('id', 0); // This will return no results
        } else {
            // Return all jobs
        }

        // Filter by page_id if provided
        if ($request->filled('page_id') && Schema::hasColumn('Wo_Job', 'page_id')) {
            $query->where('page_id', (int) $request->query('page_id'));
        }

        // Filter by job_type if column exists
        if ($request->filled('job_type') && Schema::hasColumn('Wo_Job', 'job_type')) {
            $query->where('job_type', $request->query('job_type'));
        }

        // Filter by category if column exists
        if ($request->filled('category_id') && Schema::hasColumn('Wo_Job', 'category')) {
            $query->where('category', (int) $request->query('category_id'));
        }

        // Note: category column might not exist in Wo_Jobs table
        // if ($request->filled('category')) {
        //     $query->where('category_id', $request->query('category'));
        // }

        // Note: type column might not exist in Wo_Job table
        // if ($request->filled('type')) {
        //     $query->where('type', (string) $request->query('type'));
        // }

        if ($request->filled('location')) {
            $query->where('location', 'like', '%' . $request->query('location') . '%');
        }

        // Note: salary column doesn't exist in Wo_Job table
        // if ($request->filled('salary_min')) {
        //     $query->where('salary', '>=', $request->query('salary_min'));
        // }

        // if ($request->filled('salary_max')) {
        //     $query->where('salary', '<=', $request->query('salary_max'));
        // }

        if ($request->filled('term')) {
            $like = '%' . str_replace('%', '\\%', $request->query('term')) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                  ->orWhere('description', 'like', $like);
                // Note: company column doesn't exist in Wo_Job table
            });
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Job $job) use ($tokenUserId) {
            // Get owner user_id from job attributes
            $ownerUserId = $job->user_id ?? $job->user ?? null;
            $isOwner = $ownerUserId && (string) $ownerUserId === (string) $tokenUserId;
            
            // Get owner details if user_id exists
            $owner = [
                'user_id' => $ownerUserId,
                'username' => 'Unknown',
                'avatar_url' => null,
            ];
            
            if ($ownerUserId && Schema::hasTable('Wo_Users')) {
                try {
                    $ownerUser = DB::table('Wo_Users')->where('user_id', $ownerUserId)->first();
                    if ($ownerUser) {
                        $owner['username'] = $ownerUser->username ?? 'Unknown';
                        $avatar = $ownerUser->avatar ?? '';
                        if ($avatar) {
                            $owner['avatar_url'] = asset('storage/' . $avatar);
                        }
                    }
                } catch (\Exception $e) {
                    // Keep default values
                }
            }

            // Determine if current authenticated user has applied to this job
            $isApplied = false;
            if ($tokenUserId) {
                // Check both possible application table/column names (WoWonder compatibility)
                if (Schema::hasTable('Wo_Job_Apply')) {
                    $applyTable = 'Wo_Job_Apply';
                } elseif (Schema::hasTable('Wo_JobApplications')) {
                    $applyTable = 'Wo_JobApplications';
                } else {
                    $applyTable = null;
                }

                if ($applyTable) {
                    $jobIdColumn = Schema::hasColumn($applyTable, 'job_id') ? 'job_id' : 'job';
                    $userIdColumn = Schema::hasColumn($applyTable, 'user_id') ? 'user_id' : 'user';

                    $userIdStr = (string) $tokenUserId;
                    $isApplied = DB::table($applyTable)
                        ->where($jobIdColumn, $job->id)
                        ->where(function ($query) use ($userIdColumn, $userIdStr) {
                            $query->where($userIdColumn, $userIdStr)
                                  ->orWhere($userIdColumn, (int) $userIdStr);
                        })
                        ->exists();
                }
            }
            
            $image = null;
            if (Schema::hasColumn('Wo_Job', 'image') && $job->image) {
                $image = (str_starts_with($job->image, 'http://') || str_starts_with($job->image, 'https://'))
                    ? $job->image
                    : asset('storage/' . $job->image);
            }

            // Salary information from minimum/maximum/salary_date/currency columns (if they exist)
            $salaryPeriod = null;
            $currency = null;
            if (Schema::hasColumn('Wo_Job', 'salary_date')) {
                $salaryPeriod = $job->attributes['salary_date'] ?? null;
            }
            if (Schema::hasColumn('Wo_Job', 'currency')) {
                $currency = $job->attributes['currency'] ?? null;
            }

            // Job type from job_type column if it exists
            $jobType = 'full_time';
            if (Schema::hasColumn('Wo_Job', 'job_type')) {
                $jobType = (string) ($job->job_type ?? 'full_time');
            }

            // Wo_Job.category may store either the job category ID or the lang_key;
            // resolve to the English label via Wo_Job_Categories + Wo_Langs.
            $rawCategory = $job->category ?? null;
            $categoryId = $rawCategory !== null && $rawCategory !== '' ? (int) $rawCategory : null;
            $categoryName = null;
            if ($rawCategory !== null && $rawCategory !== '') {
                $categoryMaps = $this->categoryMap();
                if ($categoryId && isset($categoryMaps['by_id'][$categoryId])) {
                    $categoryName = $categoryMaps['by_id'][$categoryId];
                } elseif (isset($categoryMaps['by_lang_key'][(string) $rawCategory])) {
                    $categoryName = $categoryMaps['by_lang_key'][(string) $rawCategory];
                }
            }

            return [
                'id' => $job->id,
                'title' => $job->title,
                'description' => $job->description,
                'image' => $image,
                'location' => $job->location,
                'min_salary' => $job->minimum ?? 0,
                'max_salary' => $job->maximum ?? 0,
                'salary_period' => $salaryPeriod,
                'currency' => $currency,
                'job_type' => $jobType,
                'category_id' => $job->category ?? null,
                'category_name' => $categoryName,
                'status' => $job->status,
                'applications_count' => $job->applications_count,
                'is_applied' => $isApplied,
                'is_owner' => $isOwner,
                'owner' => $owner,
                'created_at' => $job->time ? date('c', $job->time_as_timestamp) : null,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $validated = $request->validate([
            'page_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:2000'],
            'location' => ['required', 'string', 'max:100'],
            'minimum' => ['nullable', 'numeric', 'min:0'],
            'maximum' => ['nullable', 'numeric', 'min:0'],
            'salary_date' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'max:10'],
            'job_type' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'integer'],
            'image' => ['nullable', 'image', 'max:5120'],
            'question_one' => ['nullable', 'string', 'max:200'],
            'question_one_type' => ['nullable', 'string', 'max:100'],
            'question_one_answers' => ['nullable', 'string'],
            'question_two' => ['nullable', 'string', 'max:200'],
            'question_two_type' => ['nullable', 'string', 'max:100'],
            'question_two_answers' => ['nullable', 'string'],
            'question_three' => ['nullable', 'string', 'max:200'],
            'question_three_type' => ['nullable', 'string', 'max:100'],
            'question_three_answers' => ['nullable', 'string'],
        ]);

        // Ensure page exists and belongs to user, and is verified
        $page = Page::where('page_id', $validated['page_id'])->first();
        if (!$page) {
            return response()->json(['ok' => false, 'message' => 'Page not found'], 404);
        }

        if ((string) $page->user_id !== (string) $userId) {
            return response()->json(['ok' => false, 'message' => 'You are not the owner of this page'], 403);
        }

        if (!$page->verified) {
            return response()->json(['ok' => false, 'message' => 'Only verified pages can post jobs'], 400);
        }

        // Check if job title already exists
        $existingJob = Job::where('title', $validated['title'])->first();
        if ($existingJob) {
            return response()->json(['ok' => false, 'message' => 'Job title is already taken'], 400);
        }

        $job = new Job();
        $job->title = $validated['title'];
        $job->description = $validated['description'];
        // Note: company column doesn't exist in Wo_Job table
        $job->location = $validated['location'];
        // Optional salary fields if columns exist
        if (!is_null($validated['minimum']) && Schema::hasColumn('Wo_Job', 'minimum')) {
            $job->setAttribute('minimum', (float) $validated['minimum']);
        }
        if (!is_null($validated['maximum']) && Schema::hasColumn('Wo_Job', 'maximum')) {
            $job->setAttribute('maximum', (float) $validated['maximum']);
        }
        if (!empty($validated['salary_date']) && Schema::hasColumn('Wo_Job', 'salary_date')) {
            $job->setAttribute('salary_date', $validated['salary_date']);
        }
        if (!empty($validated['currency']) && Schema::hasColumn('Wo_Job', 'currency')) {
            $job->setAttribute('currency', $validated['currency']);
        }
        // Optional image upload if image column exists
        if ($request->hasFile('image') && Schema::hasColumn('Wo_Job', 'image')) {
            try {
                $path = $request->file('image')->store('jobs', 'public');
                $job->image = $path;
            } catch (\Exception $e) {
                // fail silently, job will still be created
            }
        }
        // Note: salary column doesn't exist in Wo_Job table
        // Note: type column doesn't exist in Wo_Job table
        $job->status = '1'; // Active
        // Attach page_id if column exists
        if (Schema::hasColumn('Wo_Job', 'page_id')) {
            $job->setAttribute('page_id', (int) $validated['page_id']);
        }
        // Attach category if column exists
        if (!is_null($validated['category']) && Schema::hasColumn('Wo_Job', 'category')) {
            $job->setAttribute('category', (int) $validated['category']);
        }
        // Attach job_type if column exists
        $jobType = $validated['job_type'] ?? $request->input('type');
        if (!empty($jobType) && Schema::hasColumn('Wo_Job', 'job_type')) {
            $job->setAttribute('job_type', (string) $jobType);
        }
        // Store questions if columns exist
        $questionFields = [
            'question_one', 'question_one_type', 'question_one_answers',
            'question_two', 'question_two_type', 'question_two_answers',
            'question_three', 'question_three_type', 'question_three_answers',
        ];
        foreach ($questionFields as $field) {
            $value = $request->input($field, '');
            if ($value !== '' && Schema::hasColumn('Wo_Job', $field)) {
                $job->setAttribute($field, $value);
            }
        }

        // Save user_id if column exists
        if (Schema::hasColumn('Wo_Job', 'user_id')) {
            $job->user_id = (string) $userId;
        } elseif (Schema::hasColumn('Wo_Job', 'user')) {
            $job->user = (string) $userId;
        }
        $job->time = (string) time();
        $job->save();

        $image = null;
        if (Schema::hasColumn('Wo_Job', 'image') && $job->image) {
            $image = (str_starts_with($job->image, 'http://') || str_starts_with($job->image, 'https://'))
                ? $job->image
                : asset('storage/' . $job->image);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Job created successfully',
            'data' => [
                'id' => $job->id,
                'title' => $job->title,
                'description' => $job->description,
                'image' => $image,
                'company' => 'Unknown Company', // Default value since column doesn't exist
                'location' => $job->location,
                'salary' => 0, // Default value since column doesn't exist
                'type' => 'full-time', // Default value since column doesn't exist
                'status' => $job->status,
                'applications_count' => 0,
                'is_applied' => false,
                'is_owner' => false, // Simplified since user_id doesn't exist
                'owner' => [
                    'user_id' => null,
                    'username' => 'Unknown',
                    'avatar_url' => null,
                ],
                'created_at' => $job->time ? date('c', $job->time_as_timestamp) : null,
            ],
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        // Auth is optional - public jobs can be viewed without auth
        $tokenUserId = null;
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        }
        
        $job = Job::where('id', $id)->first();
        if (!$job) {
            return response()->json(['ok' => false, 'message' => 'Job not found'], 404);
        }

        // Get applications for this job
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $applicationsQuery = JobApplication::where('job_id', $id)
            ->orderByDesc('time');

        $applications = $applicationsQuery->paginate($perPage);

        // Build applications data with full WoWonder-style fields
        $jobQuestions = $this->extractJobQuestions($job);
        $questionMap = [];
        foreach ($jobQuestions as $q) {
            if (!empty($q['number'])) {
                $questionMap[$q['number']] = $q;
            }
        }

        $applicationsData = $applications->getCollection()->map(function (JobApplication $application) use ($questionMap) {
            $attrs = $application->getAttributes();

            // Map dynamic questions + answers (e.g. notice period, etc.)
            $qa = [];
            $q1Answer = $attrs['question_one_answer'] ?? '';
            $q2Answer = $attrs['question_two_answer'] ?? '';
            $q3Answer = $attrs['question_three_answer'] ?? '';

            if (!empty($q1Answer) && isset($questionMap[1])) {
                $qa[] = [
                    'label' => $questionMap[1]['text'],
                    'answer' => $q1Answer,
                ];
            }
            if (!empty($q2Answer) && isset($questionMap[2])) {
                $qa[] = [
                    'label' => $questionMap[2]['text'],
                    'answer' => $q2Answer,
                ];
            }
            if (!empty($q3Answer) && isset($questionMap[3])) {
                $qa[] = [
                    'label' => $questionMap[3]['text'],
                    'answer' => $q3Answer,
                ];
            }

            // Basic applicant info stored directly on Wo_Job_Apply
            $userName = $attrs['user_name'] ?? '';
            $location = $attrs['location'] ?? '';
            $phone = $attrs['phone_number'] ?? '';
            $email = $attrs['email'] ?? '';

            // Extra profile info from Wo_Users (for profile URL / username)
            $userId = $attrs['user_id'] ?? null;
            $username = null;
            $avatarUrl = null;
            $profilePath = null;
            $baseUrl = config('app.url', 'https://ouptel.com');
            if ($userId && Schema::hasTable('Wo_Users')) {
                try {
                    $userRow = DB::table('Wo_Users')->where('user_id', $userId)->first();
                    if ($userRow) {
                        $username = $userRow->username ?? null;
                        $avatar = $userRow->avatar ?? '';
                        if (!empty($avatar)) {
                            // If avatar already full URL, use as-is; otherwise prefix storage path
                            if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
                                $avatarUrl = $avatar;
                            } else {
                                $avatarUrl = asset('storage/' . ltrim($avatar, '/'));
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // ignore, keep nulls
                }
            }
            if ($userId) {
                // Full URL to profile page, not just path
                $profilePath = rtrim($baseUrl, '/') . '/profile/' . $userId;
            }

            return [
                'id' => $application->id,
                'user_id' => $userId,
                'username' => $username,
                // Full URL to profile image stored in /storage
                'profile_url' => $avatarUrl,
                // Frontend route path to profile page
                'profile_path' => $profilePath,
                'user_name' => $userName,
                'location' => $location,
                'phone_number' => $phone,
                'email' => $email,
                'position' => $attrs['position'] ?? '',
                'where_did_you_work' => $attrs['where_did_you_work'] ?? '',
                'experience_description' => $attrs['experience_description'] ?? '',
                'experience_start_date' => $attrs['experience_start_date'] ?? '',
                'experience_end_date' => $attrs['experience_end_date'] ?? '',
                'questions' => $qa,
                'created_at' => $application->time ? date('c', $application->time_as_timestamp) : null,
            ];
        });
        
        // Check if user has applied (only if authenticated)
        $isApplied = false;
        if ($tokenUserId) {
            $isApplied = $job->isAppliedByUser($tokenUserId);
        }
        
        // Check if user is the owner
        $ownerUserId = $job->user_id ?? $job->user ?? null;
        $isOwner = $ownerUserId && (string) $ownerUserId === (string) $tokenUserId;
        
        // Get owner details
        $owner = [
            'user_id' => $ownerUserId,
            'username' => 'Unknown',
            'avatar_url' => null,
        ];
        
        if ($ownerUserId && Schema::hasTable('Wo_Users')) {
            try {
                $ownerUser = DB::table('Wo_Users')->where('user_id', $ownerUserId)->first();
                if ($ownerUser) {
                    $owner['username'] = $ownerUser->username ?? 'Unknown';
                    $avatar = $ownerUser->avatar ?? '';
                    if ($avatar) {
                        $owner['avatar_url'] = asset('storage/' . $avatar);
                    }
                }
            } catch (\Exception $e) {
                // Keep default values
            }
        }
        
        // Get applications count
        $applicationsCount = 0;
        if (Schema::hasTable('Wo_Job_Apply') || Schema::hasTable('Wo_JobApplications')) {
            $applyTable = Schema::hasTable('Wo_Job_Apply') ? 'Wo_Job_Apply' : 'Wo_JobApplications';
            $jobIdColumn = Schema::hasColumn($applyTable, 'job_id') ? 'job_id' : 'job';
            try {
                $applicationsCount = DB::table($applyTable)
                    ->where($jobIdColumn, $id)
                    ->count();
            } catch (\Exception $e) {
                // Keep default value
            }
        }

        $showRawCategory = $job->category ?? null;
        $showCategoryId = $showRawCategory !== null && $showRawCategory !== '' ? (int) $showRawCategory : null;
        $showCategoryName = null;
        if ($showRawCategory !== null && $showRawCategory !== '') {
            $categoryMaps = $this->categoryMap();
            if ($showCategoryId && isset($categoryMaps['by_id'][$showCategoryId])) {
                $showCategoryName = $categoryMaps['by_id'][$showCategoryId];
            } elseif (isset($categoryMaps['by_lang_key'][(string) $showRawCategory])) {
                $showCategoryName = $categoryMaps['by_lang_key'][(string) $showRawCategory];
            }
        }

        $showImage = null;
        if (!empty($job->attributes['image'])) {
            $img = $job->attributes['image'];
            $showImage = str_starts_with($img, 'http') ? $img : asset('storage/' . $img);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'job' => [
                    'id' => $job->id,
                    'title' => $job->title,
                    'description' => $job->description,
                    'image' => $showImage,
                    'location' => $job->location,
                    'min_salary' => $job->minimum ?? 0,
                    'max_salary' => $job->maximum ?? 0,
                    'salary_period' => $job->attributes['salary_date'] ?? 'per_month',
                    'currency' => $job->attributes['currency'] ?? '₹',
                    'job_type' => $job->attributes['job_type'] ?? 'full-time',
                    'category_id' => $showCategoryId,
                    'category_name' => $showCategoryName,
                    'status' => $job->status,
                    'applications_count' => $applicationsCount,
                    'is_applied' => $isApplied,
                    'is_owner' => $isOwner,
                    'owner' => $owner,
                    'questions' => $this->extractJobQuestions($job),
                    'created_at' => $job->time ? date('c', $job->time_as_timestamp) : null,
                ],
                'applications' => $applicationsData,
                'meta' => [
                    'current_page' => $applications->currentPage(),
                    'per_page' => $applications->perPage(),
                    'total' => $applications->total(),
                    'last_page' => $applications->lastPage(),
                ],
            ],
        ]);
    }

    public function applications(Request $request, $id): JsonResponse
    {
        $job = Job::where('id', $id)->first();
        if (!$job) {
            return response()->json(['ok' => false, 'message' => 'Job not found'], 404);
        }

        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $query = JobApplication::where('job_id', $id)
            ->orderByDesc('time');

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (JobApplication $application) {
            return [
                'id' => $application->id,
                'cover_letter' => '', // Default value since column doesn't exist
                'resume_url' => '', // Default value since column doesn't exist
                'status' => 'pending', // Default value since column doesn't exist
                'created_at' => $application->time ? date('c', $application->time_as_timestamp) : null,
                'applicant' => [
                    'user_id' => $application->user_id,
                    'username' => 'Unknown', // Since we can't get user details
                    'avatar_url' => null,
                ],
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function apply(Request $request, $id): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $job = Job::where('id', $id)->first();
        if (!$job) {
            return response()->json(['ok' => false, 'message' => 'Job not found'], 404);
        }

        $alreadyApplied = DB::table('Wo_Job_Apply')
            ->where('job_id', $id)
            ->where('user_id', $userId)
            ->exists();
        if ($alreadyApplied) {
            return response()->json(['ok' => false, 'message' => 'You have already applied to this job'], 400);
        }

        $request->validate([
            'user_name' => 'required|string|max:100',
            'phone_number' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:50',
            'email' => 'required|email|max:100',
            'position' => 'nullable|string|max:100',
            'where_did_you_work' => 'nullable|string|max:100',
            'experience_description' => 'nullable|string|max:300',
            'experience_start_date' => 'nullable|string|max:50',
            'experience_end_date' => 'nullable|string|max:50',
            'question_one_answer' => 'nullable|string|max:200',
            'question_two_answer' => 'nullable|string|max:200',
            'question_three_answer' => 'nullable|string|max:200',
        ]);

        $pageId = $job->page_id ?? 0;

        // Normalize experience dates to avoid null constraint issues
        $expStart = $request->input('experience_start_date', '');
        $expEndRaw = $request->input('experience_end_date', '');
        $expEnd = $expEndRaw === null || $expEndRaw === '' ? $expStart : $expEndRaw;

        $insertData = [
            'user_id' => $userId,
            'job_id' => (int) $id,
            'page_id' => (int) $pageId,
            'user_name' => $request->input('user_name', ''),
            'phone_number' => $request->input('phone_number', ''),
            'location' => $request->input('location', ''),
            'email' => $request->input('email', ''),
            'position' => $request->input('position', ''),
            'where_did_you_work' => $request->input('where_did_you_work', ''),
            'experience_description' => $request->input('experience_description', ''),
            'experience_start_date' => $expStart,
            'experience_end_date' => $expEnd,
            'question_one_answer' => $request->input('question_one_answer', ''),
            'question_two_answer' => $request->input('question_two_answer', ''),
            'question_three_answer' => $request->input('question_three_answer', ''),
            'time' => (string) time(),
        ];

        $appId = DB::table('Wo_Job_Apply')->insertGetId($insertData);

        // Notify page owner about new application
        try {
            if (Schema::hasTable('Wo_Notifications') && Schema::hasTable('Wo_Pages')) {
                // Resolve page owner from Wo_Pages
                $pageOwnerId = null;
                if ($pageId) {
                    $pageRow = DB::table('Wo_Pages')->where('page_id', $pageId)->first();
                    if ($pageRow && isset($pageRow->user_id)) {
                        $pageOwnerId = (string) $pageRow->user_id;
                    }
                }

                // Fall back to job owner if page owner not found
                if (!$pageOwnerId) {
                    $jobOwnerId = $job->user_id ?? $job->user ?? null;
                    if ($jobOwnerId) {
                        $pageOwnerId = (string) $jobOwnerId;
                    }
                }

                // Only send notification if we have a valid recipient and it's not the same as applicant
                if ($pageOwnerId && (string) $pageOwnerId !== (string) $userId) {
                    $notificationData = [
                        'notifier_id' => (string) $userId,
                        'recipient_id' => $pageOwnerId,
                        'type' => 'applied_to_job',
                        'time' => time(),
                    ];

                    if (Schema::hasColumn('Wo_Notifications', 'url')) {
                        // Link to SPA jobs detail page: /jobs/{id}
                        // Frontend converts this by replacing "index.php?link1=" with "/"
                        $notificationData['url'] = "index.php?link1=jobs/{$job->id}";
                    }

                    if (Schema::hasColumn('Wo_Notifications', 'seen')) {
                        $notificationData['seen'] = 0;
                    }

                    DB::table('Wo_Notifications')->insert($notificationData);
                }
            }
        } catch (\Exception $e) {
            // Do not break application flow if notification fails
        }

        return response()->json([
            'ok' => true,
            'message' => 'Application submitted successfully',
            'data' => [
                'id' => $appId,
            ],
        ], 201);
    }

    public function search(Request $request): JsonResponse
    {
        $term = $request->query('term', '');
        if (empty($term)) {
            return response()->json([
                'data' => [
                    'jobs' => [],
                    'companies' => [],
                ],
            ]);
        }

        $like = '%' . str_replace('%', '\\%', $term) . '%';

        // Search jobs
        $jobs = Job::where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                  ->orWhere('description', 'like', $like);
                // Note: company column doesn't exist in Wo_Job table
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (Job $job) {
                $image = null;
                if (!empty($job->attributes['image'])) {
                    $img = $job->attributes['image'];
                    $image = str_starts_with($img, 'http') ? $img : asset('storage/' . $img);
                }
                $rawCategory = $job->category ?? null;
                $catId = $rawCategory !== null && $rawCategory !== '' ? (int) $rawCategory : null;
                $catName = null;
                if ($rawCategory !== null && $rawCategory !== '') {
                    $categoryMaps = $this->categoryMap();
                    if ($catId && isset($categoryMaps['by_id'][$catId])) {
                        $catName = $categoryMaps['by_id'][$catId];
                    } elseif (isset($categoryMaps['by_lang_key'][(string) $rawCategory])) {
                        $catName = $categoryMaps['by_lang_key'][(string) $rawCategory];
                    }
                }

                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'image' => $image,
                    'location' => $job->location,
                    'min_salary' => $job->minimum ?? 0,
                    'max_salary' => $job->maximum ?? 0,
                    'salary_period' => $job->attributes['salary_date'] ?? 'per_month',
                    'currency' => $job->attributes['currency'] ?? '₹',
                    'job_type' => $job->attributes['job_type'] ?? 'full-time',
                    'category_id' => $catId,
                    'category_name' => $catName,
                    'result_type' => 'job',
                    'created_at' => $job->time ? date('c', $job->time_as_timestamp) : null,
                ];
            });

        // Note: Wo_JobApplications table doesn't exist
        // Return empty applications data
        $applications = collect([]);

        $allResults = collect()
            ->merge($jobs)
            ->merge($applications);

        return response()->json([
            'data' => [
                'jobs' => $jobs,
                'applications' => $applications,
                'total' => $allResults->count(),
            ],
        ]);
    }

    public function myApplications(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $query = JobApplication::where('user_id', (string) $userId)
            ->orderByDesc('time');

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (JobApplication $application) {
            return [
                'id' => $application->id,
                'cover_letter' => '', // Default value since column doesn't exist
                'resume_url' => '', // Default value since column doesn't exist
                'status' => 'pending', // Default value since column doesn't exist
                'created_at' => $application->time ? date('c', $application->time_as_timestamp) : null,
                'job' => [
                    'id' => $application->job_id,
                    'title' => 'Job Title', // Since we can't get job details
                ],
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function meta(): JsonResponse
    {
        $catMaps = $this->categoryMap();
        $categories = [];
        foreach ($catMaps['by_id'] as $id => $name) {
            $categories[] = ['id' => $id, 'name' => $name];
        }

        $jobTypes = [
            ['value' => 'full-time', 'label' => 'Full Time'],
            ['value' => 'part-time', 'label' => 'Part Time'],
            ['value' => 'contract', 'label' => 'Contract'],
            ['value' => 'freelance', 'label' => 'Freelance'],
            ['value' => 'internship', 'label' => 'Internship'],
        ];

        return response()->json([
            'data' => [
                'categories' => $categories,
                'job_types' => $jobTypes,
            ],
        ]);
    }

    private function extractJobQuestions(Job $job): array
    {
        $questions = [];
        $attrs = $job->getAttributes();

        for ($i = 1; $i <= 3; $i++) {
            $numWord = ['one', 'two', 'three'][$i - 1];
            $text = $attrs["question_{$numWord}"] ?? '';
            if (empty($text)) {
                continue;
            }
            $type = $attrs["question_{$numWord}_type"] ?? 'free_text_question';
            $answers = $attrs["question_{$numWord}_answers"] ?? '';
            $options = [];
            if ($type === 'multiple_choice_question' && !empty($answers)) {
                $decoded = json_decode($answers, true);
                $options = is_array($decoded) ? $decoded : explode(',', $answers);
            }
            $questions[] = [
                'number' => $i,
                'text' => $text,
                'type' => $type,
                'options' => $options,
            ];
        }

        return $questions;
    }

    /**
     * Resolve job category IDs to their English labels using the WoWonder schema:
     * Wo_Job.category (id) -> Wo_Job_Categories.id -> Wo_Job_Categories.lang_key -> Wo_Langs.id -> Wo_Langs.english
     */
    private function categoryMap(): array
    {
        /**
         * Returns:
         * [
         *   'by_id' => [ job_category_id => english_label, ... ],
         *   'by_lang_key' => [ lang_key => english_label, ... ],
         * ]
         */
        static $maps = null;

        if ($maps === null) {
            $maps = [
                'by_id' => [],
                'by_lang_key' => [],
            ];

            if (
                Schema::hasTable('Wo_Job_Categories') &&
                Schema::hasTable('Wo_Langs') &&
                Schema::hasColumn('Wo_Job_Categories', 'lang_key') &&
                Schema::hasColumn('Wo_Langs', 'id') &&
                Schema::hasColumn('Wo_Langs', 'english')
            ) {
                $rows = DB::table('Wo_Job_Categories as jc')
                    ->join('Wo_Langs as l', 'jc.lang_key', '=', 'l.id')
                    ->select('jc.id', 'jc.lang_key', 'l.english')
                    ->get();

                foreach ($rows as $row) {
                    $maps['by_id'][(int) $row->id] = $row->english;
                    if (!empty($row->lang_key)) {
                        $maps['by_lang_key'][(string) $row->lang_key] = $row->english;
                    }
                }
            }
        }

        return $maps;
    }
}
