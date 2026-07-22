<?php

namespace App\Http\Controllers\Api\V1\News;

use App\Models\NewsArticle;
use App\Models\NewsArticleComment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class NewsCommentController extends Controller
{
    /**
     * List comments for a news article (public).
     */
    public function index(Request $request, int $article): JsonResponse
    {
        $newsArticle = NewsArticle::published()->find($article);

        if (!$newsArticle) {
            return response()->json([
                'status' => 'error',
                'message' => 'Article not found',
            ], 404);
        }

        $tokenUserId = $this->resolveTokenUserId($request);

        $perPage = max(1, min((int) $request->query('per_page', 30), 50));

        $paginator = NewsArticleComment::with('user')
            ->where('news_article_id', $newsArticle->id)
            ->orderBy('id', 'asc')
            ->paginate($perPage);

        $comments = $paginator->getCollection()->map(
            fn (NewsArticleComment $comment) => $this->formatComment($comment, $tokenUserId)
        );

        return response()->json([
            'status' => 'success',
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
     * Post a comment (auth required via Bearer / Wo_AppsSessions).
     */
    public function store(Request $request, int $article): JsonResponse
    {
        $tokenUserId = $this->resolveTokenUserId($request);

        if (!$tokenUserId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized — please log in to comment',
            ], 401);
        }

        $newsArticle = NewsArticle::published()->find($article);

        if (!$newsArticle) {
            return response()->json([
                'status' => 'error',
                'message' => 'Article not found',
            ], 404);
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $comment = NewsArticleComment::create([
            'news_article_id' => $newsArticle->id,
            'user_id' => (int) $tokenUserId,
            'text' => trim($validated['text']),
        ]);

        $comment->load('user');

        return response()->json([
            'status' => 'success',
            'message' => 'Comment posted successfully',
            'data' => $this->formatComment($comment, $tokenUserId),
        ], 201);
    }

    /**
     * Delete own comment.
     */
    public function destroy(Request $request, int $comment): JsonResponse
    {
        $tokenUserId = $this->resolveTokenUserId($request);

        if (!$tokenUserId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $commentModel = NewsArticleComment::find($comment);

        if (!$commentModel) {
            return response()->json([
                'status' => 'error',
                'message' => 'Comment not found',
            ], 404);
        }

        if ((string) $commentModel->user_id !== (string) $tokenUserId) {
            return response()->json([
                'status' => 'error',
                'message' => 'You can only delete your own comments',
            ], 403);
        }

        $commentModel->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Comment deleted',
        ]);
    }

    protected function resolveTokenUserId(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);

        return DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
    }

    protected function formatComment(NewsArticleComment $comment, ?string $currentUserId): array
    {
        $user = $comment->user;
        $author = null;

        if ($user instanceof User) {
            $avatarUrl = $user->avatar ? asset('storage/' . $user->avatar) : null;

            $author = [
                'user_id' => $user->user_id,
                'username' => $user->username ?? 'Unknown',
                'name' => $user->name
                    ?? (trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->username ?? 'User')),
                'avatar' => $user->avatar ?? '',
                'avatar_url' => $avatarUrl,
            ];
        }

        return [
            'id' => $comment->id,
            'news_article_id' => $comment->news_article_id,
            'text' => $comment->text,
            'created_at' => optional($comment->created_at)?->toIso8601String(),
            'created_at_human' => optional($comment->created_at)?->diffForHumans(),
            'is_owner' => $currentUserId ? ((string) $comment->user_id === (string) $currentUserId) : false,
            'author' => $author,
        ];
    }
}
