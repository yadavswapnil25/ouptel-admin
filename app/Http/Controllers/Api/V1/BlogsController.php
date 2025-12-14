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

    // 5.1 Get My Articles
    public function getMyArticles(Request $request): JsonResponse
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

        $perPage = (int) ($request->query('per_page', 25));
        $perPage = max(1, min($perPage, 50));

        $query = Article::query()
            ->where('user', $userId)
            ->orderByDesc('id');

        // Filter by active status if provided
        if ($request->has('active')) {
            $active = $request->boolean('active');
            $query->where('active', $active ? '1' : '0');
        }

        // Filter by category if provided
        if ($request->filled('category')) {
            $query->where('category', (int) $request->query('category'));
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Article $article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'description' => $article->description,
                'content' => $article->content,
                'category' => $article->category,
                'thumbnail' => $article->thumbnail_url,
                'tags' => $article->tags ? explode(',', $article->tags) : [],
                'posted' => $article->posted,
                'posted_at' => $article->posted_date,
                'active' => (bool) $article->active,
                'views' => $article->views_count,
                'shares' => $article->shares_count,
                'comments' => $article->comments_count,
                'reactions' => $article->reactions_count,
                'url' => $article->url,
            ];
        });

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'api_version' => '1.0',
            'blogs' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    // 5.2 Create Article
    public function createArticle(Request $request): JsonResponse
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

        // Validate required fields
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'content' => ['required', 'string'],
            'category' => ['required', 'integer'],
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        // Create the article
        $article = new Article();
        $article->user = (string) $userId;
        $article->title = $validated['title'];
        $article->description = $validated['description'];
        $article->content = $validated['content'];
        $article->category = $validated['category'];
        $article->thumbnail = $validated['thumbnail'] ?? '';
        $article->tags = $validated['tags'] ?? '';
        $article->posted = time();
        $article->active = $validated['active'] ?? true;
        $article->view = 0;
        $article->shared = 0;
        $article->save();

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'api_version' => '1.0',
            'message' => 'Article created successfully',
            'data' => [
                'id' => $article->id,
                'title' => $article->title,
                'description' => $article->description,
                'content' => $article->content,
                'category' => $article->category,
                'thumbnail' => $article->thumbnail_url,
                'tags' => $article->tags ? explode(',', $article->tags) : [],
                'posted' => $article->posted,
                'posted_at' => $article->posted_date,
                'active' => (bool) $article->active,
                'url' => $article->url,
            ],
        ], 201);
    }

    // 5.3 Update My Article
    public function updateArticle(Request $request, $id): JsonResponse
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

        // Find the article
        $article = Article::find($id);
        if (!$article) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Article not found',
                ],
            ], 400);
        }

        // Check if user owns the article
        if ($article->user != $userId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 7,
                    'error_text' => 'You are not the article owner',
                ],
            ], 400);
        }

        // Validate fields
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'content' => ['sometimes', 'required', 'string'],
            'category' => ['sometimes', 'required', 'integer'],
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        // Update the article
        if (isset($validated['title'])) {
            $article->title = $validated['title'];
        }
        if (isset($validated['description'])) {
            $article->description = $validated['description'];
        }
        if (isset($validated['content'])) {
            $article->content = $validated['content'];
        }
        if (isset($validated['category'])) {
            $article->category = $validated['category'];
        }
        if (isset($validated['thumbnail'])) {
            $article->thumbnail = $validated['thumbnail'] ?? '';
        }
        if (isset($validated['tags'])) {
            $article->tags = $validated['tags'] ?? '';
        }
        if (isset($validated['active'])) {
            $article->active = $validated['active'];
        }
        $article->save();

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'api_version' => '1.0',
            'message' => 'Article updated successfully',
            'data' => [
                'id' => $article->id,
                'title' => $article->title,
                'description' => $article->description,
                'content' => $article->content,
                'category' => $article->category,
                'thumbnail' => $article->thumbnail_url,
                'tags' => $article->tags ? explode(',', $article->tags) : [],
                'posted' => $article->posted,
                'posted_at' => $article->posted_date,
                'active' => (bool) $article->active,
                'url' => $article->url,
            ],
        ]);
    }

    // 5.4 Delete My Article
    public function deleteArticle(Request $request, $id): JsonResponse
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

        // Find the article
        $article = Article::find($id);
        if (!$article) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Article not found',
                ],
            ], 400);
        }

        // Check if user owns the article
        if ($article->user != $userId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 7,
                    'error_text' => 'You are not the article owner',
                ],
            ], 400);
        }

        // Delete related data
        DB::table('Wo_Blog_Comments')->where('blog_id', $id)->delete();
        DB::table('Wo_Blog_Comment_Replies')->where('blog_id', $id)->delete();
        DB::table('Wo_Blog_Reaction')->where('blog_id', $id)->delete();

        // Delete the article
        $article->delete();

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'api_version' => '1.0',
            'message' => 'Article deleted successfully',
        ]);
    }
}


