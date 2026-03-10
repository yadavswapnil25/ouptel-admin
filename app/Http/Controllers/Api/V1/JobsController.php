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
                $jobType = (string) ($job->attributes['job_type'] ?? 'full_time');
            }

            // Wo_Job.category may store either the job category ID or the lang_key;
            // resolve to the English label via Wo_Job_Categories + Wo_Langs.
            $rawCategory = $job->attributes['category'] ?? null;
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
                'is_applied' => $job->is_applied,
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
            'image' => ['nullable', 'image', 'max:5120'], // up to 5MB
            // Note: company, type, and salary columns don't exist in Wo_Job table
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

        $applicationsData = $applications->getCollection()->map(function (JobApplication $application) {
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
        
        // Check if user has applied (only if authenticated)
        $isApplied = false;
        if ($tokenUserId) {
            $isApplied = $job->isAppliedByUser($tokenUserId);
        }
        
        // Check if user is the owner
        $ownerUserId = $job->attributes['user_id'] ?? $job->attributes['user'] ?? null;
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

        $showRawCategory = $job->attributes['category'] ?? null;
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

        $validated = $request->validate([
            // Note: cover_letter and resume_url columns don't exist in Wo_Job_Apply table
        ]);

        $application = new JobApplication();
        $application->job_id = (string) $id;
        $application->user_id = (string) $userId;
        // Note: cover_letter, resume_url, and status columns don't exist in Wo_Job_Apply table
        $application->time = (string) time();
        $application->save();

        return response()->json([
            'ok' => true,
            'message' => 'Application submitted successfully',
            'data' => [
                'id' => $application->id,
                'cover_letter' => '', // Default value since column doesn't exist
                'resume_url' => '', // Default value since column doesn't exist
                'status' => 'pending', // Default value since column doesn't exist
                'created_at' => $application->time ? date('c', $application->time_as_timestamp) : null,
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
                $rawCategory = $job->attributes['category'] ?? null;
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
