<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class PopularPostsController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $likesSub = DB::table('Wo_Likes')
            ->selectRaw('post_id, COUNT(*) as likes_count')
            ->groupBy('post_id');

        $query = Post::query()
            ->leftJoinSub($likesSub, 'likes_agg', function ($join) {
                $join->on('likes_agg.post_id', '=', 'Wo_Posts.id');
            })
            ->where('Wo_Posts.active', 1)
            ->orderByDesc(DB::raw('COALESCE(likes_agg.likes_count, 0)'))
            ->orderByDesc('Wo_Posts.id')
            ->select('Wo_Posts.*', DB::raw('COALESCE(likes_agg.likes_count, 0) as likes_count'));

        // Optional: scope to logged-in user via token (for "my most liked")
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
            if ($userId && $request->boolean('only_my', false)) {
                $query->where('Wo_Posts.user_id', $userId);
            }
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Post $post) {
            return [
                'id' => $post->id,
                'post_id' => $post->post_id,
                'text' => $post->postTextPreview,
                'image' => $post->postImageUrl,
                'likes' => (int) ($post->likes_count ?? 0),
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


