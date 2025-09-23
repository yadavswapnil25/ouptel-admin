<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class MemoriesController extends BaseController
{
    public function index(Request $request): JsonResponse
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

        // Target date month-day. Allow override via ?date=YYYY-MM-DD
        $dateParam = $request->query('date');
        $target = $dateParam ? date('m-d', strtotime($dateParam)) : date('m-d');

        // Posts by user on the same month-day from previous years
        $query = Post::query()
            ->where('user_id', $userId)
            ->where('active', 1)
            ->whereRaw("DATE_FORMAT(FROM_UNIXTIME(`time`), '%m-%d') = ?", [$target])
            ->whereRaw("FROM_UNIXTIME(`time`, '%Y') < ?", [date('Y')])
            ->orderByDesc('time');

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Post $post) {
            return [
                'id' => $post->id,
                'post_id' => $post->post_id,
                'text' => $post->postTextPreview,
                'image' => $post->postImageUrl,
                'created_at' => $post->time ? date('c', (int) $post->time) : null,
                'year' => $post->time ? (int) date('Y', (int) $post->time) : null,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'month_day' => $target,
            ],
        ]);
    }
}


