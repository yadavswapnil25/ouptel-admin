<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CommentController extends Controller
{
    /**
     * Register a new comment on a post (mimics WoWonder requests.php?f=posts&s=register_comment)
     * 
     * @param Request $request
     * @param int $postId
     * @return JsonResponse
     */
    public function registerComment(Request $request, int $postId): JsonResponse
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

        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:5000',
            'hash' => 'nullable|string', // For compatibility with WoWonder hash system
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if post exists
        $post = Post::where('id', $postId)->orWhere('post_id', $postId)->first();
        if (!$post) {
            return response()->json(['ok' => false, 'message' => 'Post not found'], 404);
        }

        // Check if user can comment on this post
        if (!$this->canCommentOnPost($post, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Access denied or comments disabled'], 403);
        }

        // Check if user exists
        $user = User::where('user_id', $tokenUserId)->first();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'User not found'], 404);
        }

        try {
            DB::beginTransaction();

            // File uploads are not supported in the simplified version
            // Only basic text comments are supported

            // Prepare comment data - only include fields that exist in Wo_Comments table
            $commentData = [
                'user_id' => $tokenUserId,
                'post_id' => $post->post_id,
                'text' => $request->input('text'),
                'time' => time(),
            ];

            // Handle file uploads if provided
            if ($request->hasFile('image')) {
                $commentData['c_file'] = $this->handleFileUpload($request->file('image'), 'comments', 'image');
            }
            if ($request->hasFile('audio')) {
                $commentData['record'] = $this->handleFileUpload($request->file('audio'), 'comments', 'audio');
            }

            // Create the comment
            $comment = Comment::create($commentData);

            // Reply count updates are not supported in this simplified version

            // Send notifications
            $this->sendCommentNotifications($comment, $post, $tokenUserId);

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Comment posted successfully',
                'data' => [
                    'comment_id' => $comment->id,
                    'post_id' => $post->post_id,
                    'text' => $comment->text,
                    'author' => [
                        'user_id' => $user->user_id,
                        'username' => $user->username,
                        'name' => $user->name,
                        'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    ],
                    'created_at' => date('c', $comment->time),
                    'created_at_human' => date('Y-m-d H:i:s', $comment->time),
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'ok' => false,
                'message' => 'Failed to post comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comments for a post
     * 
     * @param Request $request
     * @param int $postId
     * @return JsonResponse
     */
    public function getComments(Request $request, int $postId): JsonResponse
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

        // Check if post exists
        $post = Post::where('id', $postId)->orWhere('post_id', $postId)->first();
        if (!$post) {
            return response()->json(['ok' => false, 'message' => 'Post not found'], 404);
        }

        // Check if user can view this post
        if (!$this->canViewPost($post, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Access denied'], 403);
        }

        try {
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            $query = Comment::with('user')
                ->where('post_id', $post->post_id);
            
            // Exclude replies (only get top-level comments)
            $hasParentColumn = DB::getSchemaBuilder()->hasColumn('Wo_Comments', 'parent_comment_id');
            if ($hasParentColumn) {
                $query->whereNull('parent_comment_id');
            }
            
            $query->orderBy('time', 'desc');

            $comments = $query->paginate($perPage, ['*'], 'page', $page);

            $includeReplies = $request->input('include_replies', true); // Default to true
            $includeRepliesData = $request->input('include_replies_data', true); // Default to true - include replies in response
            $repliesLimit = (int) ($request->input('replies_limit', 3));
            $repliesLimit = max(1, min($repliesLimit, 10)); // Limit between 1-10
            
            $formattedComments = $comments->map(function ($comment) use ($tokenUserId, $includeReplies, $includeRepliesData, $repliesLimit) {
                $formatted = $this->formatCommentData($comment, $tokenUserId);
                
                // Always include replies count
                $repliesCount = $this->getCommentRepliesCount($comment->id);
                $formatted['replies_count'] = $repliesCount;
                
                // Include replies data if requested (default: true)
                if ($includeReplies && $includeRepliesData && $repliesCount > 0) {
                    // Get first few replies (default: 3)
                    $formatted['replies'] = $this->getCommentRepliesPreview($comment->id, $tokenUserId, $repliesLimit);
                } else {
                    $formatted['replies'] = [];
                }
                
                return $formatted;
            });

            return response()->json([
                'ok' => true,
                'data' => [
                    'comments' => $formattedComments,
                    'pagination' => [
                        'current_page' => $comments->currentPage(),
                        'last_page' => $comments->lastPage(),
                        'per_page' => $comments->perPage(),
                        'total' => $comments->total(),
                        'has_more' => $comments->hasMorePages(),
                    ],
                    'post_id' => $post->post_id,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to get comments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a comment
     * 
     * @param Request $request
     * @param int $commentId
     * @return JsonResponse
     */
    public function updateComment(Request $request, int $commentId): JsonResponse
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

        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if comment exists and belongs to user
        $comment = Comment::where('id', $commentId)
            ->where('user_id', $tokenUserId)
            ->first();

        if (!$comment) {
            return response()->json(['ok' => false, 'message' => 'Comment not found or access denied'], 404);
        }

        try {
            $comment->update([
                'text' => $request->input('text'),
                'time' => time(), // Update timestamp
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Comment updated successfully',
                'data' => $this->formatCommentData($comment, $tokenUserId)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to update comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a comment
     * 
     * @param Request $request
     * @param int $commentId
     * @return JsonResponse
     */
    public function deleteComment(Request $request, int $commentId): JsonResponse
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

        // Check if comment exists and belongs to user or user is post owner
        $comment = Comment::where('id', $commentId)
            ->with('post')
            ->first();

        if (!$comment) {
            return response()->json(['ok' => false, 'message' => 'Comment not found'], 404);
        }

        // Check if user can delete this comment
        if (!$this->canDeleteComment($comment, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Access denied'], 403);
        }

        try {
            DB::beginTransaction();

            // Clean up associated files before deleting
            if ($comment->c_file) {
                Storage::delete($comment->c_file);
            }
            if ($comment->record) {
                Storage::delete($comment->record);
            }

            // Delete the comment (hard delete since active field doesn't exist)
            $comment->delete();

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Comment deleted successfully',
                'data' => [
                    'comment_id' => $comment->id,
                    'deleted_at' => date('c'),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'ok' => false,
                'message' => 'Failed to delete comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reply to a comment
     * 
     * @param Request $request
     * @param int $commentId
     * @return JsonResponse
     */
    public function replyToComment(Request $request, int $commentId): JsonResponse
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

        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:5000',
            'hash' => 'nullable|string', // For compatibility with WoWonder hash system
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if parent comment exists
        $parentComment = Comment::where('id', $commentId)->first();
        if (!$parentComment) {
            return response()->json(['ok' => false, 'message' => 'Comment not found'], 404);
        }

        // Get the post from parent comment
        $post = Post::where('post_id', $parentComment->post_id)->first();
        if (!$post) {
            return response()->json(['ok' => false, 'message' => 'Post not found'], 404);
        }

        // Check if user can comment on this post
        if (!$this->canCommentOnPost($post, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Access denied or comments disabled'], 403);
        }

        // Check if user exists
        $user = User::where('user_id', $tokenUserId)->first();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'User not found'], 404);
        }

        try {
            DB::beginTransaction();

            // Check if Wo_CommentReplies table exists (separate table for replies)
            $hasRepliesTable = DB::getSchemaBuilder()->hasTable('Wo_CommentReplies');
            
            if ($hasRepliesTable) {
                // Use separate replies table
                $replyData = [
                    'user_id' => $tokenUserId,
                    'comment_id' => $commentId,
                    'post_id' => $post->post_id,
                    'text' => $request->input('text'),
                    'time' => time(),
                ];

                // Handle file uploads if provided
                if ($request->hasFile('image')) {
                    $replyData['c_file'] = $this->handleFileUpload($request->file('image'), 'comment_replies', 'image');
                }
                if ($request->hasFile('audio')) {
                    $replyData['record'] = $this->handleFileUpload($request->file('audio'), 'comment_replies', 'audio');
                }

                $replyId = DB::table('Wo_CommentReplies')->insertGetId($replyData);
                $reply = DB::table('Wo_CommentReplies')->where('id', $replyId)->first();
                
                // Format reply data
                $formattedReply = [
                    'id' => $reply->id,
                    'comment_id' => $reply->comment_id,
                    'post_id' => $reply->post_id,
                    'text' => $reply->text,
                    'c_file' => $reply->c_file ?? '',
                    'c_file_url' => ($reply->c_file ?? '') ? asset('storage/' . $reply->c_file) : null,
                    'record' => $reply->record ?? '',
                    'record_url' => ($reply->record ?? '') ? asset('storage/' . $reply->record) : null,
                    'is_reply' => true,
                    'is_owner' => $reply->user_id == $tokenUserId,
                    'author' => [
                        'user_id' => $user->user_id,
                        'username' => $user->username,
                        'name' => $this->getUserName($user),
                        'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    ],
                    'created_at' => date('c', $reply->time),
                    'created_at_human' => $this->getHumanTime($reply->time),
                    'reactions_count' => 0,
                    'total_reactions' => 0,
                ];
            } else {
                // Use same table with parent_comment_id (if column exists) or store in text
                // For now, we'll store it as a regular comment but mark it differently
                // This is a fallback if replies table doesn't exist
                $commentData = [
                    'user_id' => $tokenUserId,
                    'post_id' => $post->post_id,
                    'text' => $request->input('text'),
                    'time' => time(),
                ];

                // Handle file uploads if provided
                if ($request->hasFile('image')) {
                    $commentData['c_file'] = $this->handleFileUpload($request->file('image'), 'comments', 'image');
                }
                if ($request->hasFile('audio')) {
                    $commentData['record'] = $this->handleFileUpload($request->file('audio'), 'comments', 'audio');
                }

                // Check if parent_comment_id column exists
                $hasParentColumn = DB::getSchemaBuilder()->hasColumn('Wo_Comments', 'parent_comment_id');
                if ($hasParentColumn) {
                    $commentData['parent_comment_id'] = $commentId;
                }

                $reply = Comment::create($commentData);
                $formattedReply = $this->formatCommentData($reply, $tokenUserId);
                $formattedReply['is_reply'] = true;
                $formattedReply['parent_comment_id'] = $commentId;
            }

            // Send notifications
            $this->sendReplyNotifications($parentComment, $post, $tokenUserId);

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Reply posted successfully',
                'data' => $formattedReply
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'ok' => false,
                'message' => 'Failed to post reply',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get replies for a comment
     * 
     * @param Request $request
     * @param int $commentId
     * @return JsonResponse
     */
    public function getReplies(Request $request, int $commentId): JsonResponse
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

        // Check if parent comment exists
        $parentComment = Comment::where('id', $commentId)->first();
        if (!$parentComment) {
            return response()->json(['ok' => false, 'message' => 'Comment not found'], 404);
        }

        try {
            $perPage = (int) ($request->input('per_page', 10));
            $perPage = max(1, min($perPage, 50));
            $page = (int) ($request->input('page', 1));
            $page = max(1, $page);

            // Check if Wo_CommentReplies table exists
            $hasRepliesTable = DB::getSchemaBuilder()->hasTable('Wo_CommentReplies');
            
            if ($hasRepliesTable) {
                // Get replies from separate table
                $offset = ($page - 1) * $perPage;
                $replies = DB::table('Wo_CommentReplies')
                    ->where('comment_id', $commentId)
                    ->orderBy('time', 'asc')
                    ->offset($offset)
                    ->limit($perPage)
                    ->get();

                $total = DB::table('Wo_CommentReplies')
                    ->where('comment_id', $commentId)
                    ->count();

                $formattedReplies = $replies->map(function ($reply) use ($tokenUserId) {
                    $user = User::where('user_id', $reply->user_id)->first();
                    return [
                        'id' => $reply->id,
                        'comment_id' => $reply->comment_id,
                        'post_id' => $reply->post_id,
                        'text' => $reply->text,
                        'c_file' => $reply->c_file ?? '',
                        'c_file_url' => ($reply->c_file ?? '') ? asset('storage/' . $reply->c_file) : null,
                        'record' => $reply->record ?? '',
                        'record_url' => ($reply->record ?? '') ? asset('storage/' . $reply->record) : null,
                        'is_reply' => true,
                        'is_owner' => $reply->user_id == $tokenUserId,
                        'author' => [
                            'user_id' => $user?->user_id ?? $reply->user_id,
                            'username' => $user?->username ?? 'Unknown',
                            'name' => $this->getUserName($user),
                            'avatar_url' => ($user?->avatar) ? asset('storage/' . $user->avatar) : null,
                        ],
                        'created_at' => date('c', $reply->time),
                        'created_at_human' => $this->getHumanTime($reply->time),
                        'reactions_count' => $this->getReplyReactionsCount($reply->id),
                        'total_reactions' => $this->getReplyReactionsCount($reply->id),
                        'user_reaction' => $this->getUserReplyReaction($reply->id, $tokenUserId),
                    ];
                });
            } else {
                // Get replies from same table using parent_comment_id
                $hasParentColumn = DB::getSchemaBuilder()->hasColumn('Wo_Comments', 'parent_comment_id');
                
                if ($hasParentColumn) {
                    $query = Comment::where('parent_comment_id', $commentId)
                        ->orderBy('time', 'asc');
                    
                    $replies = $query->paginate($perPage, ['*'], 'page', $page);
                    $total = $replies->total();
                    
                    $formattedReplies = $replies->map(function ($reply) use ($tokenUserId) {
                        $formatted = $this->formatCommentData($reply, $tokenUserId);
                        $formatted['is_reply'] = true;
                        $formatted['parent_comment_id'] = $commentId;
                        return $formatted;
                    });
                } else {
                    // No replies support
                    $formattedReplies = collect([]);
                    $total = 0;
                }
            }

            $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

            return response()->json([
                'ok' => true,
                'data' => [
                    'replies' => $formattedReplies,
                    'pagination' => [
                        'current_page' => $page,
                        'last_page' => $lastPage,
                        'per_page' => $perPage,
                        'total' => $total,
                        'has_more' => $page < $lastPage,
                    ],
                    'comment_id' => $commentId,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to get replies',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reaction count for a reply
     * 
     * @param int $replyId
     * @return int
     */
    private function getReplyReactionsCount(int $replyId): int
    {
        // Check if Wo_PostReactions table has replay_id column
        if (!DB::getSchemaBuilder()->hasTable('Wo_PostReactions')) {
            return 0;
        }

        $hasReplayId = DB::getSchemaBuilder()->hasColumn('Wo_PostReactions', 'replay_id');
        
        if ($hasReplayId) {
            return DB::table('Wo_PostReactions')
                ->where('replay_id', $replyId)
                ->where('post_id', 0)
                ->count();
        }

        return 0;
    }

    /**
     * Get user's reaction for a reply
     * 
     * @param int $replyId
     * @param string $userId
     * @return int|null
     */
    private function getUserReplyReaction(int $replyId, string $userId): ?int
    {
        if (!DB::getSchemaBuilder()->hasTable('Wo_PostReactions')) {
            return null;
        }

        $hasReplayId = DB::getSchemaBuilder()->hasColumn('Wo_PostReactions', 'replay_id');
        
        if ($hasReplayId) {
            $reaction = DB::table('Wo_PostReactions')
                ->where('replay_id', $replyId)
                ->where('user_id', $userId)
                ->where('post_id', 0)
                ->first();
            
            return $reaction ? $reaction->reaction : null;
        }

        return null;
    }

    /**
     * Get user name helper
     * 
     * @param object|null $user
     * @return string
     */
    private function getUserName($user): string
    {
        if (!$user) {
            return 'Unknown User';
        }
        
        if (isset($user->name) && !empty($user->name)) {
            return $user->name;
        }
        
        $firstName = $user->first_name ?? '';
        $lastName = $user->last_name ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        if (!empty($fullName)) {
            return $fullName;
        }
        
        return $user->username ?? 'Unknown User';
    }

    /**
     * Get human readable time
     * 
     * @param int $timestamp
     * @return string
     */
    private function getHumanTime(int $timestamp): string
    {
        $time = time() - $timestamp;
        
        if ($time < 60) return 'Just now';
        if ($time < 3600) return floor($time / 60) . 'm';
        if ($time < 86400) return floor($time / 3600) . 'h';
        if ($time < 2592000) return floor($time / 86400) . 'd';
        if ($time < 31536000) return floor($time / 2592000) . 'mo';
        return floor($time / 31536000) . 'y';
    }

    /**
     * Get replies count for a comment
     * 
     * @param int $commentId
     * @return int
     */
    private function getCommentRepliesCount(int $commentId): int
    {
        // Check if Wo_CommentReplies table exists
        $hasRepliesTable = DB::getSchemaBuilder()->hasTable('Wo_CommentReplies');
        
        if ($hasRepliesTable) {
            return DB::table('Wo_CommentReplies')
                ->where('comment_id', $commentId)
                ->count();
        }
        
        // Check if parent_comment_id column exists in Wo_Comments
        $hasParentColumn = DB::getSchemaBuilder()->hasColumn('Wo_Comments', 'parent_comment_id');
        if ($hasParentColumn) {
            return Comment::where('parent_comment_id', $commentId)->count();
        }
        
        return 0;
    }

    /**
     * Get preview of replies for a comment
     * 
     * @param int $commentId
     * @param string $userId
     * @param int $limit
     * @return array
     */
    private function getCommentRepliesPreview(int $commentId, string $userId, int $limit = 3): array
    {
        // Check if Wo_CommentReplies table exists
        $hasRepliesTable = DB::getSchemaBuilder()->hasTable('Wo_CommentReplies');
        
        if ($hasRepliesTable) {
            $replies = DB::table('Wo_CommentReplies')
                ->where('comment_id', $commentId)
                ->orderBy('time', 'asc')
                ->limit($limit)
                ->get();

            return $replies->map(function ($reply) use ($userId) {
                $user = User::where('user_id', $reply->user_id)->first();
                return [
                    'id' => $reply->id,
                    'text' => $reply->text,
                    'is_owner' => $reply->user_id == $userId,
                    'author' => [
                        'user_id' => $user?->user_id ?? $reply->user_id,
                        'username' => $user?->username ?? 'Unknown',
                        'name' => $this->getUserName($user),
                        'avatar_url' => ($user?->avatar) ? asset('storage/' . $user->avatar) : null,
                    ],
                    'created_at_human' => $this->getHumanTime($reply->time),
                ];
            })->toArray();
        }
        
        // Check if parent_comment_id column exists
        $hasParentColumn = DB::getSchemaBuilder()->hasColumn('Wo_Comments', 'parent_comment_id');
        if ($hasParentColumn) {
            $replies = Comment::where('parent_comment_id', $commentId)
                ->orderBy('time', 'asc')
                ->limit($limit)
                ->get();
            
            return $replies->map(function ($reply) use ($userId) {
                $formatted = $this->formatCommentData($reply, $userId);
                $formatted['is_reply'] = true;
                return $formatted;
            })->toArray();
        }
        
        return [];
    }

    /**
     * Send notifications for new reply
     * 
     * @param Comment $parentComment
     * @param Post $post
     * @param string $userId
     * @return void
     */
    private function sendReplyNotifications(Comment $parentComment, Post $post, string $userId): void
    {
        // In a real implementation, you would:
        // 1. Notify the parent comment author
        // 2. Notify the post author (if different)
        // 3. Send push notifications if enabled
        
        Log::info("Reply created by user {$userId} on comment {$parentComment->id} for post {$post->id}");
    }

    /**
     * Register a reaction on a comment
     * 
     * @param Request $request
     * @param int $commentId
     * @return JsonResponse
     */
    public function registerCommentReaction(Request $request, int $commentId): JsonResponse
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

        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'reaction' => 'required|integer|in:1,2,3,4,5,6', // 1=Like, 2=Love, 3=Haha, 4=Wow, 5=Sad, 6=Angry
            'hash' => 'nullable|string', // For compatibility with WoWonder hash system
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if comment exists
        $comment = Comment::where('id', $commentId)->first();
        if (!$comment) {
            return response()->json(['ok' => false, 'message' => 'Comment not found'], 404);
        }

        $reactionType = $request->input('reaction');

        try {
            DB::beginTransaction();

            // Check if user already reacted to this comment
            $existingReaction = PostReaction::where('comment_id', $commentId)
                ->where('user_id', $tokenUserId)
                ->where('post_id', 0) // Only comment reactions, not post reactions
                ->first();

            if ($existingReaction) {
                if ($existingReaction->reaction == $reactionType) {
                    // User is trying to react with the same reaction - remove it
                    $existingReaction->delete();
                    $action = 'removed';
                    $this->decrementCommentReactionCount($comment, $reactionType);
                } else {
                    // User is changing reaction type
                    $oldReactionType = $existingReaction->reaction;
                    $existingReaction->update(['reaction' => $reactionType]);
                    $this->decrementCommentReactionCount($comment, $oldReactionType);
                    $this->incrementCommentReactionCount($comment, $reactionType);
                    $action = 'updated';
                }
            } else {
                // Create new reaction
                PostReaction::create([
                    'user_id' => $tokenUserId,
                    'post_id' => 0,
                    'comment_id' => $commentId,
                    'replay_id' => 0,
                    'message_id' => 0,
                    'story_id' => 0,
                    'reaction' => $reactionType,
                ]);
                $this->incrementCommentReactionCount($comment, $reactionType);
                $action = 'added';
            }

            // Refresh comment to get updated counts
            $comment->refresh();

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Comment reaction ' . $action . ' successfully',
                'data' => [
                    'comment_id' => $comment->id,
                    'action' => $action,
                    'reaction_type' => $reactionType,
                    'reaction_name' => $this->getReactionName($reactionType),
                    'reaction_icon' => $this->getReactionIcon($reactionType),
                    'reaction_counts' => $comment->reaction_counts,
                    'total_reactions' => $comment->total_reactions,
                    'user_reaction' => $action === 'removed' ? null : $reactionType,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'ok' => false,
                'message' => 'Failed to register comment reaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle file upload
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $directory
     * @param string $type
     * @return string
     */
    private function handleFileUpload($file, string $directory, string $type): string
    {
        if (!$file) return '';

        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, 'public');
        
        return $path ?: '';
    }

    /**
     * Check if user can comment on this post
     * 
     * @param Post $post
     * @param string $userId
     * @return bool
     */
    private function canCommentOnPost(Post $post, string $userId): bool
    {
        // Check if comments are enabled for this post
        if ($post->comments_status == 0) {
            return false;
        }

        // Owner can always comment
        if ($post->user_id == $userId) return true;

        // Check privacy settings
        switch ($post->postPrivacy) {
            case '0': // Public
                return true;
            case '1': // Friends
                return $this->areFriends($post->user_id, $userId);
            case '2': // Only Me
                return false;
            case '3': // Custom
                return $this->isInCustomList($post->user_id, $userId);
            case '4': // Group
                return $this->isGroupMember($post->group_id, $userId);
            default:
                return false;
        }
    }

    /**
     * Check if user can view this post
     * 
     * @param Post $post
     * @param string $userId
     * @return bool
     */
    private function canViewPost(Post $post, string $userId): bool
    {
        // Owner can always view
        if ($post->user_id == $userId) return true;

        switch ($post->postPrivacy) {
            case '0': // Public
                return true;
            case '1': // Friends
                return $this->areFriends($post->user_id, $userId);
            case '2': // Only Me
                return false;
            case '3': // Custom
                return $this->isInCustomList($post->user_id, $userId);
            case '4': // Group
                return $this->isGroupMember($post->group_id, $userId);
            default:
                return false;
        }
    }

    /**
     * Check if user can delete this comment
     * 
     * @param Comment $comment
     * @param string $userId
     * @return bool
     */
    private function canDeleteComment(Comment $comment, string $userId): bool
    {
        // Comment owner can delete
        if ($comment->user_id == $userId) return true;

        // Post owner can delete any comment on their post
        if ($comment->post && $comment->post->user_id == $userId) return true;

        return false;
    }

    /**
     * Check if two users are friends
     * 
     * @param string $userId1
     * @param string $userId2
     * @return bool
     */
    private function areFriends(string $userId1, string $userId2): bool
    {
        // Note: Wo_Friends table might not exist
        // In a real implementation, you would query this table
        return false;
    }

    /**
     * Check if user is in custom privacy list
     * 
     * @param string $postOwnerId
     * @param string $userId
     * @return bool
     */
    private function isInCustomList(string $postOwnerId, string $userId): bool
    {
        // Note: Custom privacy lists would need additional tables
        // For now, return false
        return false;
    }

    /**
     * Check if user is group member
     * 
     * @param int|null $groupId
     * @param string $userId
     * @return bool
     */
    private function isGroupMember(?int $groupId, string $userId): bool
    {
        if (!$groupId) return false;
        
        // Note: Wo_Group_Members table might not exist
        // In a real implementation, you would query this table
        return false;
    }

    /**
     * Format comment data for API response
     * 
     * @param Comment $comment
     * @param string $userId
     * @return array
     */
    private function formatCommentData(Comment $comment, string $userId): array
    {
        return [
            'id' => $comment->id,
            'post_id' => $comment->post_id,
            'page_id' => $comment->page_id ?? 0,
            'text' => $comment->text,
            'comment_type' => $comment->comment_type,
            'has_file' => $comment->has_file,
            'file_url' => $comment->file_url,
            'c_file' => $comment->c_file ?? '',
            'record' => $comment->record ?? '',
            'is_reply' => false, // This is a top-level comment
            'has_replies' => $this->getCommentRepliesCount($comment->id) > 0,
            'is_owner' => $comment->user_id == $userId,
            'author' => [
                'user_id' => $comment->user->user_id ?? $comment->user_id,
                'username' => $comment->user->username ?? 'Unknown',
                'name' => $comment->user->name ?? 'Unknown User',
                'avatar_url' => $comment->user->avatar ? asset('storage/' . $comment->user->avatar) : null,
            ],
            'created_at' => date('c', $comment->time),
            'created_at_human' => $comment->human_time,
            'reaction_counts' => $comment->reaction_counts,
            'total_reactions' => $comment->total_reactions,
            'replies_count' => $this->getCommentRepliesCount($comment->id),
            'user_reaction' => $comment->getUserReaction($userId),
        ];
    }

    /**
     * Send notifications for new comment
     * 
     * @param Comment $comment
     * @param Post $post
     * @param string $userId
     * @return void
     */
    private function sendCommentNotifications(Comment $comment, Post $post, string $userId): void
    {
        // In a real implementation, you would:
        // 1. Get post author
        // 2. Get other commenters on this post
        // 3. Create notifications for them
        // 4. Send push notifications if enabled
        
        // For now, we'll just log the action
        Log::info("Comment created by user {$userId} on post {$post->id} with comment ID {$comment->id}");
    }

    /**
     * Increment comment reaction count
     * Note: Reaction count fields don't exist in Wo_Comments table
     * Counts are calculated from Wo_PostReactions table
     * 
     * @param Comment $comment
     * @param int $reactionType
     * @return void
     */
    private function incrementCommentReactionCount(Comment $comment, int $reactionType): void
    {
        // No-op: Reaction counts are calculated from Wo_PostReactions table
        // No need to maintain denormalized counts in Wo_Comments
    }

    /**
     * Decrement comment reaction count
     * Note: Reaction count fields don't exist in Wo_Comments table
     * Counts are calculated from Wo_PostReactions table
     * 
     * @param Comment $comment
     * @param int $reactionType
     * @return void
     */
    private function decrementCommentReactionCount(Comment $comment, int $reactionType): void
    {
        // No-op: Reaction counts are calculated from Wo_PostReactions table
        // No need to maintain denormalized counts in Wo_Comments
    }

    /**
     * Get reaction name by type
     * 
     * @param int $reactionType
     * @return string
     */
    private function getReactionName(int $reactionType): string
    {
        $reactionNames = [
            1 => 'Like',
            2 => 'Love',
            3 => 'Haha',
            4 => 'Wow',
            5 => 'Sad',
            6 => 'Angry',
        ];

        return $reactionNames[$reactionType] ?? "Reaction {$reactionType}";
    }

    /**
     * Get reaction icon by type
     * 
     * @param int $reactionType
     * @return string
     */
    private function getReactionIcon(int $reactionType): string
    {
        $reactionIcons = [
            1 => '👍',
            2 => '❤️',
            3 => '😂',
            4 => '😮',
            5 => '😢',
            6 => '😠',
        ];

        return $reactionIcons[$reactionType] ?? '👍';
    }
}
