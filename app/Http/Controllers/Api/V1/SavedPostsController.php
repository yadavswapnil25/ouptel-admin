<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class SavedPostsController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
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

        $savedPostIds = DB::table('Wo_SavedPosts')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->pluck('post_id');

        $query = Post::query()
            ->whereIn('id', $savedPostIds)
            ->where('active', 1)
            ->orderByDesc('id');

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Post $post) {
            return [
                'id' => $post->id,
                'post_id' => $post->post_id,
                'type' => $post->post_type,
                'text' => $post->postTextPreview,
                'image' => $post->postImageUrl,
                'created_at' => $post->time ? date('c', (int) $post->time) : null,
                'user' => [
                    'user_id' => optional($post->user)->user_id,
                    'username' => optional($post->user)->username,
                    'avatar_url' => optional($post->user)->avatar_url,
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
}


