<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Article;
use App\Models\BlogCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Get blog categories with metadata
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function categories(Request $request): JsonResponse
    {
        $categories = BlogCategory::query()
            ->orderBy('id')
            ->get()
            ->map(function ($category) {
                // Get article count for this category
                $articleCount = Article::where('category', $category->id)
                    ->where('active', '1')
                    ->count();

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'lang_key' => $category->lang_key ?? '',
                    'articles_count' => $articleCount,
                ];
            });

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'api_version' => '1.0',
            'data' => [
                'categories' => $categories,
                'total_categories' => $categories->count(),
            ],
        ]);
    }

    /**
     * Get blog/article by ID
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, $id): JsonResponse
    {
        // Get authentication (optional)
        $tokenUserId = null;
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        }

        // Build query similar to index method - show active articles to everyone
        // If authenticated, also show inactive articles if user is the owner
        $query = Article::query()->where('id', $id);
        
        // If not authenticated, only show active articles (matching index behavior)
        if (!$tokenUserId) {
            $query->where('active', 1);
        } else {
            // If authenticated, show active articles OR inactive articles owned by user
            $query->where(function($q) use ($tokenUserId) {
                $q->where('active', 1)
                  ->orWhere(function($q2) use ($tokenUserId) {
                      $q2->where('active', '!=', 1)
                         ->where('user', (string) $tokenUserId);
                  });
            });
        }

        // Find the article
        $article = $query->first();
        
        if (!$article) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Article not found',
                ],
            ], 404);
        }

        // Get raw user value from database for comparison
        $rawUserId = DB::table('Wo_Blog')->where('id', $id)->value('user');

        // Increment view count (optional, can be controlled by parameter)
        if ($request->boolean('increment_view', true)) {
            $article->increment('view');
        }

        // Get category information
        $category = null;
        if ($article->category) {
            $categoryModel = BlogCategory::find($article->category);
            if ($categoryModel) {
                $category = [
                    'id' => $categoryModel->id,
                    'name' => $categoryModel->name,
                ];
            }
        }

        // Get user information
        // Use optional() to safely access user relationship, or fetch directly if needed
        $user = $article->user;
        $userData = null;
        
        // Check if user is an object (relationship loaded) or if we need to fetch it
        if (is_object($user) && isset($user->user_id)) {
            $userData = [
                'user_id' => $user->user_id,
                'username' => $user->username ?? 'Unknown',
                'name' => $user->name ?? $user->username ?? 'Unknown User',
                'avatar' => $user->avatar ?? '',
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'verified' => (bool) ($user->verified ?? false),
            ];
        } elseif ($rawUserId) {
            // If relationship didn't load, fetch user directly from database
            $userFromDb = DB::table('Wo_Users')->where('user_id', $rawUserId)->first();
            if ($userFromDb) {
                $userData = [
                    'user_id' => $userFromDb->user_id,
                    'username' => $userFromDb->username ?? 'Unknown',
                    'name' => $userFromDb->name ?? $userFromDb->username ?? 'Unknown User',
                    'avatar' => $userFromDb->avatar ?? '',
                    'avatar_url' => $userFromDb->avatar ? asset('storage/' . $userFromDb->avatar) : null,
                    'verified' => (bool) ($userFromDb->verified ?? false),
                ];
            }
        }

        // Check if current user has reacted (if authenticated)
        $isLiked = false;
        if ($tokenUserId && \Illuminate\Support\Facades\Schema::hasTable('Wo_Blog_Reaction')) {
            $isLiked = DB::table('Wo_Blog_Reaction')
                ->where('blog_id', $id)
                ->where('user_id', $tokenUserId)
                ->exists();
        }

        // Format response
        $response = [
            'api_status' => 200,
            'api_text' => 'success',
            'api_version' => '1.0',
            'data' => [
                'id' => $article->id,
                'title' => $article->title,
                'description' => $article->description,
                'content' => $article->content,
                'excerpt' => $article->excerpt,
                'category' => $category,
                'category_id' => $article->category,
                'thumbnail' => $article->thumbnail,
                'thumbnail_url' => $article->thumbnail_url,
                'tags' => $article->tags ? explode(',', $article->tags) : [],
                'posted' => $article->posted,
                'posted_at' => $article->posted_date,
                'active' => (bool) ($article->active == '1' || $article->active == 1),
                'status_text' => $article->status_text,
                'views' => $article->views_count,
                'shares' => $article->shares_count,
                'comments' => $article->comments_count,
                'reactions' => $article->reactions_count,
                'url' => $article->url,
                'is_liked' => $isLiked,
                'is_owner' => $tokenUserId && (string) $rawUserId == (string) $tokenUserId,
                'author' => $userData,
            ],
        ];

        return response()->json($response);
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

        // Delete related data (with error handling)
        try {
            // Delete blog comments
            if (Schema::hasTable('Wo_BlogComments')) {
                DB::table('Wo_BlogComments')->where('blog_id', $id)->delete();
            }
            
            // Delete blog comment replies
            if (Schema::hasTable('Wo_BlogCommentReplies')) {
                DB::table('Wo_BlogCommentReplies')->where('blog_id', $id)->delete();
            }
            
            // Delete blog reactions
            if (Schema::hasTable('Wo_Blog_Reaction')) {
                DB::table('Wo_Blog_Reaction')->where('blog_id', $id)->delete();
            }
        } catch (\Exception $e) {
            // Log error but continue with article deletion
            Log::warning('Error deleting related blog data: ' . $e->getMessage(), [
                'blog_id' => $id,
                'error' => $e->getMessage()
            ]);
        }

        // Delete the article
        try {
            $article->delete();
        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 8,
                    'error_text' => 'Failed to delete article: ' . $e->getMessage(),
                ],
            ], 500);
        }

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'api_version' => '1.0',
            'message' => 'Article deleted successfully',
        ]);
    }
}


