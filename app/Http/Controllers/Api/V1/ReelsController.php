<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class ReelsController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $query = Post::query()
            ->where('active', 1)
            ->where(function ($q) {
                // Uploaded short videos (common extensions) or external playtube link
                $q->where(function ($q1) {
                    $q1->whereNotNull('postFile')
                       ->where('postFile', '!=', '')
                       ->where(function ($q2) {
                           $q2->where('postFile', 'like', '%.mp4')
                              ->orWhere('postFile', 'like', '%.mov')
                              ->orWhere('postFile', 'like', '%.webm');
                       });
                })
                ->orWhere(function ($q3) {
                    $q3->whereNotNull('postPlaytube')
                       ->where('postPlaytube', '!=', '');
                });
            })
            ->orderByDesc('id');

        // Optional: scope to logged-in user via token
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
            if ($userId && $request->boolean('only_my', false)) {
                $query->where('user_id', $userId);
            }
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Post $post) {
            $videoUrl = $post->postFile ?: ($post->postPlaytube ?: null);
            $thumb = $post->postFileThumb ?: $post->postImageUrl;
            return [
                'id' => $post->id,
                'post_id' => $post->post_id,
                'video_url' => $videoUrl,
                'source' => $post->postFile ? 'uploaded' : 'playtube',
                'title' => $post->postLinkTitle ?: $post->postTextPreview,
                'thumbnail' => $thumb,
                'views' => (int) ($post->videoViews ?? 0),
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


