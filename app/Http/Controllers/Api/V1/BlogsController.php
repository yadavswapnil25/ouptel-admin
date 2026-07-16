<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Article;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogCommentReply;
use App\Models\BlogReaction;
use App\Models\Report;
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

        $tokenUserId = null;
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        }
        $authorId = (string) ($rawUserId ?? $userData['user_id'] ?? '');
        $isLiked = $tokenUserId ? BlogReaction::query()
            ->where('blog_id', $article->id)
            ->where('user_id', $tokenUserId)
            ->whereNull('comment_id')
            ->whereNull('reply_id')
            ->exists() : false;
        $socialFlags = $this->getBlogAuthorSocialFlags($tokenUserId, $authorId, (int) $article->id);
        $isFollowing = $socialFlags['is_following'];
        $followPending = $socialFlags['follow_pending'];
        $isFriend = $socialFlags['is_friend'];
        $friendRequestSent = $socialFlags['friend_request_sent'];
        $isReported = $socialFlags['is_reported'];

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
                'image_url' => $article->thumbnail_url,
                'tags' => $article->tags ? explode(',', $article->tags) : [],
                'posted' => $article->posted,
                'posted_at' => $article->posted_date,
                'active' => (bool) ($article->active == '1' || $article->active == 1),
                'views' => $article->views_count,
                'shares' => $article->shares_count,
                'comments' => $article->comments_count,
                'reactions' => $article->reactions_count,
                'comments_count' => $article->comments_count,
                'reactions_count' => $article->reactions_count,
                'shares_count' => $article->shares_count,
                'url' => $article->url,
                'is_liked' => $isLiked,
                'is_owner' => $tokenUserId ? (string) $rawUserId === (string) $tokenUserId : false,
                'is_following' => $isFollowing,
                'follow_pending' => $followPending,
                'is_friend' => $isFriend,
                'friend_request_sent' => $friendRequestSent,
                'is_reported' => $isReported,
                'author' => $userData,
            ],
        ];

        return response()->json($response);
    }

    public function toggleLike(Request $request, int $blogId): JsonResponse
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $article = Article::find($blogId);
        if (!$article) {
            return response()->json(['ok' => false, 'message' => 'Blog not found'], 404);
        }

        $existing = BlogReaction::query()
            ->where('blog_id', $article->id)
            ->where('user_id', $tokenUserId)
            ->whereNull('comment_id')
            ->whereNull('reply_id')
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
            $message = 'Blog unliked successfully';
        } else {
            BlogReaction::create([
                'user_id' => (int) $tokenUserId,
                'blog_id' => $article->id,
                'comment_id' => null,
                'reply_id' => null,
                'reaction' => 'Like',
            ]);
            $liked = true;
            $message = 'Blog liked successfully';
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'data' => [
                'is_liked' => $liked,
                'reactions_count' => $article->fresh()->reactions_count,
            ],
        ]);
    }

    public function recordShare(Request $request, int $blogId): JsonResponse
    {
        $article = Article::find($blogId);
        if (!$article) {
            return response()->json(['ok' => false, 'message' => 'Blog not found'], 404);
        }

        $article->increment('shared');

        return response()->json([
            'ok' => true,
            'message' => 'Blog shared successfully',
            'data' => [
                'shares_count' => $article->fresh()->shares_count,
            ],
        ]);
    }

    public function reportBlog(Request $request, int $blogId): JsonResponse
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $article = Article::find($blogId);
        if (!$article) {
            return response()->json(['ok' => false, 'message' => 'Blog not found'], 404);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $existing = Report::query()
            ->where('post_id', $article->id)
            ->where('user_id', $tokenUserId)
            ->where('text', 'blog')
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json([
                'ok' => true,
                'message' => 'Blog report removed successfully',
                'data' => ['is_reported' => false],
            ]);
        }

        Report::create([
            'post_id' => $article->id,
            'comment_id' => 0,
            'profile_id' => 0,
            'page_id' => 0,
            'group_id' => 0,
            'user_id' => $tokenUserId,
            'text' => 'blog',
            'reason' => $validated['reason'],
            'seen' => 0,
            'time' => time(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Blog reported successfully',
            'data' => ['is_reported' => true],
        ]);
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
    /**
     * @return array{
     *     is_following: bool,
     *     follow_pending: bool,
     *     is_friend: bool,
     *     friend_request_sent: bool,
     *     is_reported: bool
     * }
     */
    protected function getBlogAuthorSocialFlags(?string $tokenUserId, string $authorId, int $articleId): array
    {
        $flags = [
            'is_following' => false,
            'follow_pending' => false,
            'is_friend' => false,
            'friend_request_sent' => false,
            'is_reported' => false,
        ];

        if (! $tokenUserId || $authorId === '' || (string) $tokenUserId === $authorId) {
            return $flags;
        }

        if (Schema::hasTable('Wo_Followers')) {
            $flags['is_following'] = DB::table('Wo_Followers')
                ->where('follower_id', $tokenUserId)
                ->where('following_id', $authorId)
                ->whereIn('active', ['1', 1])
                ->exists();

            if (! $flags['is_following']) {
                $flags['follow_pending'] = DB::table('Wo_Followers')
                    ->where('follower_id', $tokenUserId)
                    ->where('following_id', $authorId)
                    ->whereIn('active', ['0', 0])
                    ->exists();
            }
        }

        if (Schema::hasTable('Wo_Friends')) {
            try {
                if (Schema::hasColumn('Wo_Friends', 'user_id')
                    && Schema::hasColumn('Wo_Friends', 'friend_id')
                    && Schema::hasColumn('Wo_Friends', 'status')) {
                    $flags['is_friend'] = DB::table('Wo_Friends')
                        ->where(function ($query) use ($tokenUserId, $authorId) {
                            $query->where('user_id', $tokenUserId)->where('friend_id', $authorId);
                        })
                        ->orWhere(function ($query) use ($tokenUserId, $authorId) {
                            $query->where('user_id', $authorId)->where('friend_id', $tokenUserId);
                        })
                        ->whereIn('status', ['2', 2])
                        ->exists();

                    if (! $flags['is_friend']) {
                        $flags['friend_request_sent'] = DB::table('Wo_Friends')
                            ->where('user_id', $tokenUserId)
                            ->where('friend_id', $authorId)
                            ->whereIn('status', ['0', 0])
                            ->exists();
                    }
                } elseif (Schema::hasColumn('Wo_Friends', 'from_id')
                    && Schema::hasColumn('Wo_Friends', 'to_id')
                    && Schema::hasColumn('Wo_Friends', 'status')) {
                    $flags['is_friend'] = DB::table('Wo_Friends')
                        ->where(function ($query) use ($tokenUserId, $authorId) {
                            $query->where('from_id', $tokenUserId)->where('to_id', $authorId);
                        })
                        ->orWhere(function ($query) use ($tokenUserId, $authorId) {
                            $query->where('from_id', $authorId)->where('to_id', $tokenUserId);
                        })
                        ->whereIn('status', ['2', 2])
                        ->exists();

                    if (! $flags['is_friend']) {
                        $flags['friend_request_sent'] = DB::table('Wo_Friends')
                            ->where('from_id', $tokenUserId)
                            ->where('to_id', $authorId)
                            ->whereIn('status', ['0', 0])
                            ->exists();
                    }
                }
            } catch (\Throwable $e) {
                // Ignore schema mismatches and continue loading the blog.
            }
        }

        try {
            $flags['is_reported'] = Report::query()
                ->where('post_id', $articleId)
                ->where('user_id', $tokenUserId)
                ->where('text', 'blog')
                ->exists();
        } catch (\Throwable $e) {
            $flags['is_reported'] = false;
        }

        return $flags;
    }

    protected function formatBlogComment(BlogComment $comment, ?string $currentUserId, bool $includeReplies = true): array
    {
        $user = $comment->user;
        $author = null;
        if ($user instanceof User) {
            // Return the raw asset URL without a file_exists check so the browser
            // can attempt to load it. If the file is missing the browser fires
            // onError and the Avatar component falls back to coloured initials.
            // Returning null (when avatar is empty) lets Avatar show initials
            // immediately without any failed image request.
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

        // Create the article and publish it immediately.
        $article = new Article();
        $article->user = (string) $userId;
        $article->title = $validated['title'];
        $article->description = $validated['description'];
        $article->content = $validated['content'];
        $article->category = $validated['category'];
        $article->thumbnail = $thumbnailPath;
        $article->tags = $validated['tags'] ?? '';
        $article->posted = time();
        $article->active = '1';
        $article->view = 0;
        $article->shared = 0;
        $article->save();

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'api_version' => '1.0',
            'message' => 'Blog published successfully.',
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


