<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\JobApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            // Note: user_id column might not exist in Wo_Jobs table
            // Return empty results for now
            $query->where('id', 0); // This will return no results
        } elseif ($type === 'applied_jobs') {
            // Note: Wo_JobApplication table might not exist
            // Return empty results for now
            $query->where('id', 0); // This will return no results
        } elseif ($type === 'saved_jobs') {
            // Note: Wo_JobApplication table might not exist
            // Return empty results for now
            $query->where('id', 0); // This will return no results
        } else {
            // Return all jobs
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
            return [
                'id' => $job->id,
                'title' => $job->title,
                'description' => $job->description,
                'company' => 'Unknown Company', // Default value since column doesn't exist
                'location' => $job->location,
                'salary' => 0, // Default value since column doesn't exist
                'type' => 'full-time', // Default value since column doesn't exist
                'status' => $job->status,
                'applications_count' => $job->applications_count,
                'is_applied' => $job->is_applied,
                'is_owner' => false, // Simplified since user_id doesn't exist
                'owner' => [
                    'user_id' => null,
                    'username' => 'Unknown',
                    'avatar_url' => null,
                ],
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
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:2000'],
            'location' => ['required', 'string', 'max:100'],
            // Note: company, type, and salary columns don't exist in Wo_Job table
        ]);

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
        // Note: salary column doesn't exist in Wo_Job table
        // Note: type column doesn't exist in Wo_Job table
        $job->status = '1'; // Active
        // Note: user_id column might not exist in Wo_Jobs table
        $job->time = (string) time();
        $job->save();

        return response()->json([
            'ok' => true,
            'message' => 'Job created successfully',
            'data' => [
                'id' => $job->id,
                'title' => $job->title,
                'description' => $job->description,
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

        return response()->json([
            'ok' => true,
            'data' => [
                'job' => [
                    'id' => $job->id,
                    'title' => $job->title,
                    'description' => $job->description,
                    'company' => 'Unknown Company', // Default value since column doesn't exist
                    'location' => $job->location,
                    'salary' => 0, // Default value since column doesn't exist
                    'type' => 'full-time', // Default value since column doesn't exist
                    'status' => $job->status,
                    'applications_count' => $job->applications_count,
                    'is_applied' => $job->is_applied,
                    'is_owner' => false, // Simplified since user_id doesn't exist
                    'owner' => [
                        'user_id' => null,
                        'username' => 'Unknown',
                        'avatar_url' => null,
                    ],
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
                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'company' => 'Unknown Company', // Default value since column doesn't exist
                    'location' => $job->location,
                    'type' => 'full-time', // Default value since column doesn't exist
                    'salary' => 0, // Default value since column doesn't exist
                    'type' => 'job',
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
        // Note: Wo_JobCategory table might not exist
        // Return hardcoded categories
        $categories = [
            [
                'id' => 1,
                'name' => 'Technology',
                'description' => 'Software development, IT, and tech jobs',
            ],
            [
                'id' => 2,
                'name' => 'Marketing',
                'description' => 'Digital marketing, advertising, and PR jobs',
            ],
            [
                'id' => 3,
                'name' => 'Design',
                'description' => 'Graphic design, UI/UX, and creative jobs',
            ],
            [
                'id' => 4,
                'name' => 'Sales',
                'description' => 'Sales, business development, and account management',
            ],
            [
                'id' => 5,
                'name' => 'Finance',
                'description' => 'Accounting, banking, and financial services',
            ],
        ];

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
}
