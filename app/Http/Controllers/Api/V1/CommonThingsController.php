<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommonThing;
use App\Models\CommonThingCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommonThingsController extends Controller
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

        // Note: Wo_CommonThings table might not exist, so return empty results
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
            'description' => ['required', 'string', 'max:1000'],
            'category_id' => ['nullable', 'string'],
        ]);

        // Note: Wo_CommonThings table doesn't exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Common things feature is currently unavailable. Please try again later.'
        ], 503);
    }

    public function show(Request $request, $id): JsonResponse
    {
        // Note: Wo_CommonThings table doesn't exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Common things feature is currently unavailable. Please try again later.'
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

        // Note: Wo_CommonThings table doesn't exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Common things feature is currently unavailable. Please try again later.'
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

        // Note: Wo_CommonThings table doesn't exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Common things feature is currently unavailable. Please try again later.'
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
                    'common_things' => [],
                    'total' => 0,
                ],
            ]);
        }

        // Note: Wo_CommonThings table might not exist, so return empty results
        $commonThings = [];

        return response()->json([
            'data' => [
                'common_things' => $commonThings,
                'total' => count($commonThings),
            ],
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        // Note: Wo_CommonThingCategories table might not exist, so return hardcoded categories
        $categories = [
            [
                'id' => 1,
                'name' => 'General',
                'description' => 'General common things',
                'icon' => 'fas fa-list',
                'color' => '#007bff',
            ],
            [
                'id' => 2,
                'name' => 'Technology',
                'description' => 'Technology related items',
                'icon' => 'fas fa-laptop',
                'color' => '#28a745',
            ],
            [
                'id' => 3,
                'name' => 'Home & Garden',
                'description' => 'Home and garden items',
                'icon' => 'fas fa-home',
                'color' => '#ffc107',
            ],
            [
                'id' => 4,
                'name' => 'Sports & Fitness',
                'description' => 'Sports and fitness equipment',
                'icon' => 'fas fa-dumbbell',
                'color' => '#dc3545',
            ],
            [
                'id' => 5,
                'name' => 'Books & Media',
                'description' => 'Books and media items',
                'icon' => 'fas fa-book',
                'color' => '#6f42c1',
            ],
        ];

        return response()->json([
            'ok' => true,
            'data' => $categories,
        ]);
    }

    public function myThings(Request $request): JsonResponse
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

        // Note: Wo_CommonThings table might not exist, so return empty results
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

        // Note: Wo_CommonThings table might not exist, so return empty results
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
}
