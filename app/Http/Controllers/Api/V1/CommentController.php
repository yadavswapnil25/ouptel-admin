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

        // Comment replies are not supported in this simplified version
        // Only basic comments are supported

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
                ->where('post_id', $post->post_id)
                ->orderBy('time', 'desc');

            $comments = $query->paginate($perPage, ['*'], 'page', $page);

            $formattedComments = $comments->map(function ($comment) use ($tokenUserId) {
                return $this->formatCommentData($comment, $tokenUserId);
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

            // Soft delete the comment
            $comment->update(['active' => 0]);

            // Update parent comment's reply count if this was a reply
            if ($comment->parent_id > 0) {
                Comment::where('id', $comment->parent_id)->decrement('replies');
            }

            // Clean up associated files
            if ($comment->c_file) {
                Storage::delete($comment->c_file);
            }
            if ($comment->record) {
                Storage::delete($comment->record);
            }

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
            'parent_id' => $comment->parent_id,
            'text' => $comment->text,
            'comment_type' => $comment->comment_type,
            'has_file' => $comment->has_file,
            'file_url' => $comment->file_url,
            'c_file_name' => $comment->c_file_name,
            'c_file_type' => $comment->c_file_type,
            'is_reply' => $comment->is_reply,
            'has_replies' => $comment->has_replies,
            'is_active' => $comment->is_active,
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
            'replies_count' => $comment->replies,
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
     * 
     * @param Comment $comment
     * @param int $reactionType
     * @return void
     */
    private function incrementCommentReactionCount(Comment $comment, int $reactionType): void
    {
        $field = match($reactionType) {
            1 => 'reaction_like_count',
            2 => 'reaction_love_count',
            3 => 'reaction_haha_count',
            4 => 'reaction_wow_count',
            5 => 'reaction_sad_count',
            6 => 'reaction_angry_count',
            default => null,
        };

        if ($field) {
            $comment->increment($field);
        }
    }

    /**
     * Decrement comment reaction count
     * 
     * @param Comment $comment
     * @param int $reactionType
     * @return void
     */
    private function decrementCommentReactionCount(Comment $comment, int $reactionType): void
    {
        $field = match($reactionType) {
            1 => 'reaction_like_count',
            2 => 'reaction_love_count',
            3 => 'reaction_haha_count',
            4 => 'reaction_wow_count',
            5 => 'reaction_sad_count',
            6 => 'reaction_angry_count',
            default => null,
        };

        if ($field) {
            $comment->decrement($field);
        }
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
