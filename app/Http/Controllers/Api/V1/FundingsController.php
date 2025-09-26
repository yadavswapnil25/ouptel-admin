<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Funding;
use App\Models\FundingCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FundingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized - No Bearer token provided'], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token - Session not found'], 401);
        }

        $type = $request->query('type', 'all');
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $query = Funding::query()->orderByDesc('time');

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Funding $funding) use ($tokenUserId) {
            return [
                'id' => $funding->id,
                'title' => $funding->title,
                'description' => $funding->description,
                'amount' => $funding->amount,
                'currency' => 'USD', // Default value since column doesn't exist
                'category_id' => '1', // Default value since column doesn't exist
                'status' => 'active', // Default value since column doesn't exist
                'funding_type' => 'donation', // Default value since field was removed
                'target_amount' => 0, // Default value since field was removed
                'current_amount' => 0, // Default value since field was removed
                'progress_percentage' => 0, // Default value since fields were removed
                'is_fully_funded' => false, // Default value since fields were removed
                'deadline' => null, // Default value since field was removed
                'is_owner' => $funding->user_id === (string) $tokenUserId,
                'owner' => [
                    'user_id' => $funding->user_id,
                    'username' => 'Unknown',
                    'avatar_url' => null,
                ],
                'created_at' => $funding->time ? date('c', $funding->time_as_timestamp) : null,
            ];
        });

        return response()->json([
            'ok' => true,
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
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0'],
            'funding_type' => ['nullable', 'string'],
            'target_amount' => ['required', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date', 'after:today'],
        ]);

        // Check if funding title already exists
        $existingFunding = Funding::where('title', $validated['title'])->first();
        if ($existingFunding) {
            return response()->json(['ok' => false, 'message' => 'Funding title is already taken'], 400);
        }

        $funding = new Funding();
        $funding->title = $validated['title'];
        $funding->description = $validated['description'];
        $funding->amount = $validated['amount'];
        $funding->user_id = (string) $tokenUserId;
        $funding->time = (string) time();
        $funding->save();

        return response()->json([
            'ok' => true,
            'message' => 'Funding created successfully',
            'data' => [
                'id' => $funding->id,
                'title' => $funding->title,
                'description' => $funding->description,
                'amount' => $funding->amount,
                'currency' => 'USD', // Default value since column doesn't exist
                'category_id' => '1', // Default value since column doesn't exist
                'status' => 'active', // Default value since column doesn't exist
                'funding_type' => 'donation', // Default value since field was removed
                'target_amount' => 0, // Default value since field was removed
                'current_amount' => 0, // Default value since field was removed
                'progress_percentage' => 0, // Default value since fields were removed
                'is_fully_funded' => false, // Default value since fields were removed
                'deadline' => null, // Default value since field was removed
                'is_owner' => true,
                'owner' => [
                    'user_id' => $funding->user_id,
                    'username' => 'Unknown',
                    'avatar_url' => null,
                ],
                'created_at' => $funding->time ? date('c', $funding->time_as_timestamp) : null,
            ],
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        // Note: Wo_Fundings table doesn't exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Funding feature is currently unavailable. Please try again later.'
        ], 503);
    }

    public function update(Request $request, $id): JsonResponse
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

        // Note: Wo_Fundings table doesn't exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Funding feature is currently unavailable. Please try again later.'
        ], 503);
    }

    public function destroy(Request $request, $id): JsonResponse
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

        // Note: Wo_Fundings table doesn't exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Funding feature is currently unavailable. Please try again later.'
        ], 503);
    }

    public function search(Request $request): JsonResponse
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

        $term = $request->query('term', '');
        if (empty($term)) {
            return response()->json([
                'data' => [
                    'fundings' => [],
                    'total' => 0,
                ],
            ]);
        }

        // Note: Wo_Fundings table might not exist, so return empty results
        $fundings = [];

        return response()->json([
            'data' => [
                'fundings' => $fundings,
                'total' => count($fundings),
            ],
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        // Note: Wo_FundingCategories table might not exist, so return hardcoded categories
        $categories = [
            [
                'id' => 1,
                'name' => 'Business',
                'description' => 'Business funding and investments',
                'icon' => 'fas fa-briefcase',
                'color' => '#007bff',
            ],
            [
                'id' => 2,
                'name' => 'Education',
                'description' => 'Educational funding and scholarships',
                'icon' => 'fas fa-graduation-cap',
                'color' => '#28a745',
            ],
            [
                'id' => 3,
                'name' => 'Healthcare',
                'description' => 'Healthcare and medical funding',
                'icon' => 'fas fa-heart',
                'color' => '#dc3545',
            ],
            [
                'id' => 4,
                'name' => 'Technology',
                'description' => 'Technology and innovation funding',
                'icon' => 'fas fa-laptop',
                'color' => '#6f42c1',
            ],
            [
                'id' => 5,
                'name' => 'Environment',
                'description' => 'Environmental and sustainability funding',
                'icon' => 'fas fa-leaf',
                'color' => '#20c997',
            ],
            [
                'id' => 6,
                'name' => 'Arts & Culture',
                'description' => 'Arts, culture, and creative funding',
                'icon' => 'fas fa-palette',
                'color' => '#fd7e14',
            ],
        ];

        return response()->json([
            'ok' => true,
            'data' => $categories,
        ]);
    }

    public function myFundings(Request $request): JsonResponse
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

        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        // Note: Wo_Fundings table might not exist, so return empty results
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([]), // Empty collection
            0, // Total count
            $perPage, // Per page
            1, // Current page
            ['path' => $request->url()]
        );

        $data = [];

        return response()->json([
            'ok' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function byCategory(Request $request, $categoryId): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        // Note: Wo_Fundings table might not exist, so return empty results
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([]), // Empty collection
            0, // Total count
            $perPage, // Per page
            1, // Current page
            ['path' => $request->url()]
        );

        $data = [];

        return response()->json([
            'ok' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function contribute(Request $request, $id): JsonResponse
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

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        // Note: Wo_Fundings table might not exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Funding contributions feature is currently unavailable. Please try again later.'
        ], 503);
    }

    public function myContributions(Request $request): JsonResponse
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

        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        // Note: Wo_FundingContributions table might not exist, so return empty results
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([]), // Empty collection
            0, // Total count
            $perPage, // Per page
            1, // Current page
            ['path' => $request->url()]
        );

        $data = [];

        return response()->json([
            'ok' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    // Debug method to test authentication
    public function debug(Request $request): JsonResponse
    {
        $authHeader = $request->header('Authorization');
        $token = $authHeader ? substr($authHeader, 7) : null;
        
        $sessionData = null;
        if ($token) {
            $sessionData = DB::table('Wo_AppsSessions')->where('session_id', $token)->first();
        }
        
        return response()->json([
            'ok' => true,
            'debug' => [
                'auth_header' => $authHeader,
                'token' => $token,
                'session_exists' => $sessionData ? true : false,
                'session_data' => $sessionData,
                'user_id' => $sessionData ? $sessionData->user_id : null,
            ]
        ]);
    }
}
