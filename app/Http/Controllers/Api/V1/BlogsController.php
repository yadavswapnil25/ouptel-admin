<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Article;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogCommentReply;
use App\Models\BlogReaction;
use App\Models\User;
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
            ->where('active', '1')
            ->orderByDesc('id');

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
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
        // TEMPORARY: allow fetching any article by ID, regardless of active/user
        // (no authentication or visibility restrictions)
        $article = Article::query()->where('id', $id)->first();
        
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
                // Stored avatars live under storage/, so prefix it here
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
                    // Same storage/ prefix for direct DB fetch
                    'avatar_url' => $userFromDb->avatar ? asset('storage/' . $userFromDb->avatar) : null,
                    'verified' => (bool) ($userFromDb->verified ?? false),
                ];
            }
        }

        // Optional auth: used only for ownership / reactions
        $tokenUserId = null;
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        }

        // Build reactions summary (WoWonder-style)
        $reactionsData = $this->buildBlogReactionsSummary((int) $article->id, $tokenUserId);
        $isLiked = $reactionsData['user_reaction'] === 'like';

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
                'views' => $article->views_count,
                'shares' => $article->shares_count,
                'comments' => $article->comments_count,
                'reactions' => $reactionsData['summary']['total'],
                'reactions_breakdown' => $reactionsData['summary'],
                'user_reaction' => $reactionsData['user_reaction'],
                'url' => $article->url,
                'is_liked' => $isLiked,
                'is_owner' => $tokenUserId
                    ? ((string) $rawUserId === (string) $tokenUserId)
                    : false,
                'author' => $userData,
            ],
        ];

        return response()->json($response);
    }

    /**
     * Get comments for a blog article (WoWonder-style, with replies preview).
     */
    public function getBlogComments(Request $request, int $blogId): JsonResponse
    {
        // Comments are public; auth is only needed to mark ownership flags
        $tokenUserId = null;
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        }

        $article = Article::find($blogId);
        if (!$article) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Article not found',
                ],
            ], 404);
        }

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 50));

        $query = BlogComment::with('user', 'replies.user')
            ->where('blog_id', $blogId)
            ->orderBy('id', 'asc');

        $paginator = $query->paginate($perPage);

        $comments = $paginator->getCollection()->map(function (BlogComment $comment) use ($tokenUserId) {
            return $this->formatBlogComment($comment, $tokenUserId, true);
        });

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'data' => [
                'comments' => $comments,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Toggle/set reaction on a blog article (WoWonder-style).
     * Each user can have at most one reaction per blog.
     */
    public function reactToBlog(Request $request, int $blogId): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 401,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized',
                ],
            ], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json([
                'api_status' => 401,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token',
                ],
            ], 401);
        }

        $article = Article::find($blogId);
        if (!$article) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Article not found',
                ],
            ], 404);
        }

        // Allowed reaction types (WoWonder-style)
        $validReactions = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];

        $reaction = strtolower((string) $request->input('reaction', 'like'));
        if (!in_array($reaction, $validReactions, true)) {
            return response()->json([
                'api_status' => 422,
                'errors' => [
                    'error_id' => 3,
                    'error_text' => 'Invalid reaction type',
                ],
            ], 422);
        }

        // Find existing reaction by this user on this blog (not on comments/replies)
        // In WoWonder schema, comment_id / reply_id are NOT NULL and use 0 for
        // pure post/blog reactions, so we match that convention here.
        $existing = BlogReaction::query()
            ->where('blog_id', $blogId)
            ->where('user_id', $userId)
            ->where('comment_id', 0)
            ->where('reply_id', 0)
            ->first();

        if ($existing && strtolower((string) $existing->reaction) === $reaction) {
            // Same reaction clicked again -> remove (toggle off)
            $existing->delete();
        } elseif ($existing) {
            // Switch reaction type
            $existing->reaction = $reaction;
            $existing->save();
        } else {
            // New reaction
            BlogReaction::create([
                'user_id' => $userId,
                'blog_id' => $blogId,
                'comment_id' => 0,
                'reply_id' => 0,
                'reaction' => $reaction,
            ]);
        }

        $summary = $this->buildBlogReactionsSummary($blogId, (string) $userId);

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'api_version' => '1.0',
            'data' => [
                'blog_id' => $blogId,
                'summary' => $summary['summary'],
                'user_reaction' => $summary['user_reaction'],
            ],
        ]);
    }

    /**
     * Get reactions summary for a blog (public).
     */
    public function getBlogReactions(Request $request, int $blogId): JsonResponse
    {
        $article = Article::find($blogId);
        if (!$article) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Article not found',
                ],
            ], 404);
        }

        $tokenUserId = null;
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        }

        $summary = $this->buildBlogReactionsSummary($blogId, $tokenUserId);

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'api_version' => '1.0',
            'data' => [
                'blog_id' => $blogId,
                'summary' => $summary['summary'],
                'user_reaction' => $summary['user_reaction'],
            ],
        ]);
    }

    /**
     * Helper to build reactions summary + current user's reaction.
     */
    protected function buildBlogReactionsSummary(int $blogId, ?string $currentUserId): array
    {
        $validReactions = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];

        $baseCounts = [];
        foreach ($validReactions as $type) {
            $baseCounts[$type] = 0;
        }

        $rows = BlogReaction::query()
            ->where('blog_id', $blogId)
            ->where('comment_id', 0)
            ->where('reply_id', 0)
            ->select('reaction', DB::raw('COUNT(*) as count'))
            ->groupBy('reaction')
            ->get();

        foreach ($rows as $row) {
            $type = strtolower((string) $row->reaction);
            if (array_key_exists($type, $baseCounts)) {
                $baseCounts[$type] = (int) $row->count;
            }
        }

        $total = array_sum($baseCounts);

        $userReaction = null;
        if ($currentUserId) {
            $userReaction = BlogReaction::query()
                ->where('blog_id', $blogId)
                ->where('user_id', $currentUserId)
                ->where('comment_id', 0)
                ->where('reply_id', 0)
                ->value('reaction');
            $userReaction = $userReaction ? strtolower((string) $userReaction) : null;
        }

        return [
            'summary' => [
                'total' => $total,
                'counts' => $baseCounts,
            ],
            'user_reaction' => $userReaction,
        ];
    }

    /**
     * Add a new comment to a blog article.
     */
    public function addBlogComment(Request $request, int $blogId): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided',
                ],
            ], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found',
                ],
            ], 401);
        }

        $article = Article::find($blogId);
        if (!$article) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Article not found',
                ],
            ], 404);
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $comment = new BlogComment();
        $comment->user_id = (int) $tokenUserId;
        $comment->blog_id = $article->id;
        $comment->text = trim($validated['text']);
        $comment->save();

        $comment->load('user', 'replies.user');

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'message' => 'Comment posted successfully',
            'data' => $this->formatBlogComment($comment, $tokenUserId, true),
        ], 201);
    }

    /**
     * Toggle/set a reaction on a blog comment (WoWonder-style).
     */
    public function reactToComment(Request $request, int $commentId): JsonResponse
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 401,
                'errors' => ['error_id' => 1, 'error_text' => 'Unauthorized'],
            ], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json([
                'api_status' => 401,
                'errors' => ['error_id' => 2, 'error_text' => 'Invalid token'],
            ], 401);
        }

        $comment = BlogComment::find($commentId);
        if (!$comment) {
            return response()->json([
                'api_status' => 400,
                'errors' => ['error_id' => 4, 'error_text' => 'Comment not found'],
            ], 404);
        }

        $validReactions = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];
        $reaction = strtolower((string) $request->input('reaction', 'like'));
        if (!in_array($reaction, $validReactions, true)) {
            return response()->json([
                'api_status' => 422,
                'errors' => ['error_id' => 3, 'error_text' => 'Invalid reaction type'],
            ], 422);
        }

        // comment_id = commentId, reply_id = 0 for pure comment reactions
        $existing = BlogReaction::query()
            ->where('blog_id', $comment->blog_id)
            ->where('comment_id', $commentId)
            ->where('reply_id', 0)
            ->where('user_id', $userId)
            ->first();

        if ($existing && strtolower((string) $existing->reaction) === $reaction) {
            $existing->delete();
        } elseif ($existing) {
            $existing->reaction = $reaction;
            $existing->save();
        } else {
            BlogReaction::create([
                'user_id' => $userId,
                'blog_id' => $comment->blog_id,
                'comment_id' => $commentId,
                'reply_id' => 0,
                'reaction' => $reaction,
            ]);
        }

        // Rebuild summary for this comment
        $counts = [];
        foreach ($validReactions as $type) {
            $counts[$type] = 0;
        }
        $rows = BlogReaction::query()
            ->where('comment_id', $commentId)
            ->where('reply_id', 0)
            ->select('reaction', DB::raw('COUNT(*) as count'))
            ->groupBy('reaction')
            ->get();
        foreach ($rows as $row) {
            $t = strtolower((string) $row->reaction);
            if (isset($counts[$t])) {
                $counts[$t] = (int) $row->count;
            }
        }
        $total = array_sum($counts);

        $userReaction = BlogReaction::query()
            ->where('comment_id', $commentId)
            ->where('reply_id', 0)
            ->where('user_id', $userId)
            ->value('reaction');
        $userReaction = $userReaction ? strtolower((string) $userReaction) : null;

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'data' => [
                'comment_id' => $commentId,
                'summary' => ['total' => $total, 'counts' => $counts],
                'user_reaction' => $userReaction,
            ],
        ]);
    }

    /**
     * Reply to a blog comment.
     */
    public function replyToBlogComment(Request $request, int $commentId): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided',
                ],
            ], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found',
                ],
            ], 401);
        }

        $parentComment = BlogComment::with('article')->find($commentId);
        if (!$parentComment || !$parentComment->article) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Comment not found',
                ],
            ], 404);
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $reply = new BlogCommentReply();
        $reply->user_id = (int) $tokenUserId;
        $reply->blog_id = $parentComment->blog_id;
        $reply->comm_id = $parentComment->id;
        $reply->text = trim($validated['text']);
        $reply->save();

        $reply->load('user');

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'message' => 'Reply posted successfully',
            'data' => $this->formatBlogReply($reply, $tokenUserId),
        ], 201);
    }

    /**
     * Delete a blog comment (owner or article owner).
     */
    public function deleteBlogComment(Request $request, int $commentId): JsonResponse
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

        $comment = BlogComment::with('article')->find($commentId);
        if (!$comment || !$comment->article) {
            return response()->json(['ok' => false, 'message' => 'Comment not found'], 404);
        }

        $isCommentOwner = (string) $comment->user_id === (string) $tokenUserId;
        $isArticleOwner = (string) $comment->article->user === (string) $tokenUserId;

        if (!$isCommentOwner && !$isArticleOwner) {
            return response()->json(['ok' => false, 'message' => 'Access denied'], 403);
        }

        // Delete replies first
        BlogCommentReply::where('comm_id', $comment->id)->delete();
        $comment->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Comment deleted successfully',
        ]);
    }

    /**
     * Delete a blog comment reply (owner or article/comment owner).
     */
    public function deleteBlogReply(Request $request, int $replyId): JsonResponse
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

        $reply = BlogCommentReply::with('comment.article')->find($replyId);
        if (!$reply || !$reply->comment || !$reply->article) {
            return response()->json(['ok' => false, 'message' => 'Reply not found'], 404);
        }

        $isReplyOwner = (string) $reply->user_id === (string) $tokenUserId;
        $isCommentOwner = (string) $reply->comment->user_id === (string) $tokenUserId;
        $isArticleOwner = (string) $reply->article->user === (string) $tokenUserId;

        if (!$isReplyOwner && !$isCommentOwner && !$isArticleOwner) {
            return response()->json(['ok' => false, 'message' => 'Access denied'], 403);
        }

        $reply->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Reply deleted successfully',
        ]);
    }

    /**
     * Helper: format a blog comment with replies.
     */
    protected function formatBlogComment(BlogComment $comment, ?string $currentUserId, bool $includeReplies = true): array
    {
        $user = $comment->user;
        $author = null;
        if ($user instanceof User) {
            // Comment author avatars are stored under storage/, match the URL
            // pattern used elsewhere in the app.
            $avatarUrl = $user->avatar ? asset('storage/' . $user->avatar) : null;

            $author = [
                'user_id' => $user->user_id,
                'username' => $user->username ?? 'Unknown',
                'name' => $user->name ?? $user->username ?? 'Unknown User',
                'avatar' => $user->avatar ?? '',
                'avatar_url' => $avatarUrl,
            ];
        }

        $replies = [];
        if ($includeReplies) {
            $replies = $comment->replies
                ->sortBy('time')
                ->map(function (BlogCommentReply $reply) use ($currentUserId) {
                    return $this->formatBlogReply($reply, $currentUserId);
                })
                ->values()
                ->all();
        }

        // Build per-comment reaction summary
        $validReactions = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];
        $commentReactionCounts = array_fill_keys($validReactions, 0);
        $commentReactionRows = BlogReaction::query()
            ->where('comment_id', $comment->id)
            ->where('reply_id', 0)
            ->select('reaction', DB::raw('COUNT(*) as count'))
            ->groupBy('reaction')
            ->get();
        foreach ($commentReactionRows as $row) {
            $t = strtolower((string) $row->reaction);
            if (isset($commentReactionCounts[$t])) {
                $commentReactionCounts[$t] = (int) $row->count;
            }
        }
        $commentReactionTotal = array_sum($commentReactionCounts);

        $commentUserReaction = null;
        if ($currentUserId) {
            $r = BlogReaction::query()
                ->where('comment_id', $comment->id)
                ->where('reply_id', 0)
                ->where('user_id', $currentUserId)
                ->value('reaction');
            $commentUserReaction = $r ? strtolower((string) $r) : null;
        }

        return [
            'id' => $comment->id,
            'blog_id' => $comment->blog_id,
            'text' => $comment->text,
            'created_at' => now()->toIso8601String(),
            'created_at_human' => now()->format('Y-m-d H:i:s'),
            'is_owner' => $currentUserId ? ((string) $comment->user_id === (string) $currentUserId) : false,
            'author' => $author,
            'replies_count' => $comment->replies->count(),
            'replies' => $replies,
            'reactions' => [
                'total' => $commentReactionTotal,
                'counts' => $commentReactionCounts,
            ],
            'user_reaction' => $commentUserReaction,
        ];
    }

    /**
     * Helper: format a blog reply.
     */
    protected function formatBlogReply(BlogCommentReply $reply, ?string $currentUserId): array
    {
        $user = $reply->user;
        $author = null;
        if ($user instanceof User) {
            $avatarUrl = $user->avatar ? asset('storage/' . $user->avatar) : null;

            $author = [
                'user_id' => $user->user_id,
                'username' => $user->username ?? 'Unknown',
                'name' => $user->name ?? $user->username ?? 'Unknown User',
                'avatar' => $user->avatar ?? '',
                'avatar_url' => $avatarUrl,
            ];
        }

        return [
            'id' => $reply->id,
            'blog_id' => $reply->blog_id,
            'comment_id' => $reply->comm_id,
            'text' => $reply->text,
            'created_at' => now()->toIso8601String(),
            'created_at_human' => now()->format('Y-m-d H:i:s'),
            'is_owner' => $currentUserId ? ((string) $reply->user_id === (string) $currentUserId) : false,
            'author' => $author,
        ];
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
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'tags' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        // Handle thumbnail file upload
        $thumbnailPath = '';
        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');
            $year = date('Y');
            $month = date('m');
            $dir = "upload/photos/{$year}/{$month}";
            $filename = uniqid('blog_') . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($dir), $filename);
            $thumbnailPath = "{$dir}/{$filename}";
        }

        // Create the article - new articles are pending review by default (active = 0)
        $article = new Article();
        $article->user = (string) $userId;
        $article->title = $validated['title'];
        $article->description = $validated['description'];
        $article->content = $validated['content'];
        $article->category = $validated['category'];
        $article->thumbnail = $thumbnailPath;
        $article->tags = $validated['tags'] ?? '';
        $article->posted = time();
        // Mark as inactive; admin panel can later publish by setting active = 1
        $article->active = '0';
        $article->view = 0;
        $article->shared = 0;
        $article->save();

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'api_version' => '1.0',
            'message' => 'Your blog is under review and will be published after admin approval.',
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
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
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
        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');
            $year = date('Y');
            $month = date('m');
            $dir = "upload/photos/{$year}/{$month}";
            $filename = uniqid('blog_') . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($dir), $filename);
            $article->thumbnail = "{$dir}/{$filename}";
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


