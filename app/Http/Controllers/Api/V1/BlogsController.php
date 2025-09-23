<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Article;
use App\Models\BlogCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class BlogsController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $query = Article::query()
            ->where('active', 1)
            ->orderByDesc('id');

        if ($request->filled('category')) {
            $query->where('category', (int) $request->query('category'));
        }

        // Text search by term (title, description, content, tags)
        $term = $request->query('term', $request->query('q'));
        if (!empty($term)) {
            $like = '%' . str_replace('%', '\\%', $term) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                  ->orWhere('description', 'like', $like)
                  ->orWhere('content', 'like', $like)
                  ->orWhere('tags', 'like', $like);
            });
        }

        if ($request->boolean('only_my', false)) {
            $authHeader = $request->header('Authorization');
            if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
                return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
            }
            $token = substr($authHeader, 7);
            $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
            if (!$userId) {
                return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
            }
            $query->where('user', $userId);
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Article $article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'thumbnail' => $article->thumbnail_url,
                'category' => $article->category,
                'posted_at' => $article->posted_date,
                'views' => $article->views_count,
                'shares' => $article->shares_count,
                'comments' => $article->comments_count,
                'reactions' => $article->reactions_count,
                'url' => $article->url,
                'user' => [
                    'user_id' => optional($article->user)->user_id,
                    'username' => optional($article->user)->username,
                    'avatar_url' => optional($article->user)->avatar_url,
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
        $categories = BlogCategory::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]);

        return response()->json([
            'ok' => true,
            'data' => [
                'categories' => $categories,
            ],
        ]);
    }
}


