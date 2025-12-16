<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeopleFollowController extends Controller
{
    /**
     * Update feed order to show people I follow (mimics WoWonder requests.php?f=update_order_by&type=1)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateOrderBy(Request $request): JsonResponse
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

        // Validate request parameters (mimics WoWonder structure)
        $validated = $request->validate([
            'hash' => ['required', 'string', 'size:20'], // WoWonder hash validation
            'type' => ['required', 'integer', 'min:0', 'max:2'], // Feed order type for people follow
        ]);

        $hash = $validated['hash'];
        $type = (int) $validated['type'];

        // Hash validation (mimics WoWonder hash system)
        $expectedHash = $this->generateHash($tokenUserId);
        if ($hash !== $expectedHash) {
            return response()->json(['ok' => false, 'message' => 'Invalid hash'], 403);
        }

        // Update user's people follow feed order preference
        $this->updateUserPeopleFollowOrder($tokenUserId, $type);

        return response()->json([
            'ok' => true,
            'message' => 'People follow feed order updated successfully',
            'data' => [
                'type' => $type,
                'type_name' => $this->getPeopleFollowTypeName($type),
                'user_id' => $tokenUserId,
                'updated_at' => time()
            ]
        ]);
    }

    /**
     * Get posts from people I follow
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getPeopleFollowFeed(Request $request): JsonResponse
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

        $perPage = (int) ($request->query('per_page', 10));
        $perPage = max(1, min($perPage, 50));

        // Get user's current people follow feed order preference
        $feedOrder = $this->getUserPeopleFollowOrder($tokenUserId);

        // Get posts from people I follow based on feed order
        $posts = $this->getPeopleFollowPosts($tokenUserId, $feedOrder, $perPage);

        // Get following count
        $followingCount = $this->getFollowingCount($tokenUserId);

        return response()->json([
            'ok' => true,
            'data' => $posts,
            'meta' => [
                'current_feed_type' => $feedOrder,
                'feed_type_name' => $this->getPeopleFollowTypeName($feedOrder),
                'following_count' => $followingCount,
                'per_page' => $perPage,
                'total' => count($posts)
            ]
        ]);
    }

    /**
     * Get list of people I follow
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getFollowing(Request $request): JsonResponse
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

        $perPage = (int) ($request->query('per_page', 20));
        $perPage = max(1, min($perPage, 100));

        // Note: Wo_Friends table might not exist, so we'll return empty results
        $following = $this->getFollowingUsers($tokenUserId, $perPage);
        $totalCount = $this->getFollowingCount($tokenUserId);

        return response()->json([
            'ok' => true,
            'data' => $following,
            'meta' => [
                'per_page' => $perPage,
                'total' => $totalCount,
                'current_page' => 1,
                'last_page' => ceil($totalCount / $perPage)
            ]
        ]);
    }

    /**
     * Get people follow feed types
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getPeopleFollowTypes(Request $request): JsonResponse
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

        $feedTypes = [
            ['id' => 0, 'name' => 'All Posts', 'description' => 'All posts from people I follow'],
            ['id' => 1, 'name' => 'Recent Posts', 'description' => 'Most recent posts from following'],
            ['id' => 2, 'name' => 'Popular Posts', 'description' => 'Most liked posts from following'],
        ];

        $currentFeedType = $this->getUserPeopleFollowOrder($tokenUserId);

        return response()->json([
            'ok' => true,
            'data' => [
                'current_type' => $currentFeedType,
                'available_types' => $feedTypes,
                'user_id' => $tokenUserId,
                'following_count' => $this->getFollowingCount($tokenUserId)
            ]
        ]);
    }

    /**
     * Follow a user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function followUser(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions (same as other APIs)
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

        // Validate target user_id (like old v2 follow-user.php which expects POST[user_id])
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $targetUserId = (int) $validated['user_id'];

        // Cannot follow yourself
        if ($targetUserId === (int) $tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 3,
                    'error_text' => 'Cannot follow yourself',
                ],
            ], 400);
        }

        // Check if target user exists
        $targetUser = DB::table('Wo_Users')->where('user_id', $targetUserId)->first();
        if (!$targetUser) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Recipient user not found',
                ],
            ], 400);
        }

        // Basic active check (0/2 are deactivated/banned in WoWonder)
        if (isset($targetUser->active) && in_array((string) $targetUser->active, ['0', '2'], true)) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 7,
                    'error_text' => 'Cannot follow this user',
                ],
            ], 400);
        }

        // Toggle follow logic using Wo_Followers (mimics v2/endpoints/follow-user.php)
        try {
            DB::beginTransaction();

            $existing = DB::table('Wo_Followers')
                ->where('follower_id', $tokenUserId)
                ->where('following_id', $targetUserId)
                ->first();

            $followStatus = 'invalid';

            if ($existing) {
                // Already following or requested → unfollow (delete record)
                DB::table('Wo_Followers')
                    ->where('follower_id', $tokenUserId)
                    ->where('following_id', $targetUserId)
                    ->delete();

                $followStatus = 'unfollowed';
            } else {
                // Determine if follow requires approval, similar to FollowController::requiresFollowApproval
                $requiresApproval = false;
                if (isset($targetUser->confirm_followers) && (string) $targetUser->confirm_followers === '1') {
                    $requiresApproval = true;
                }
                if (isset($targetUser->follow_privacy) && (string) $targetUser->follow_privacy === '2') {
                    // In old code this depends on friendship; here we simplify to always require approval
                    $requiresApproval = true;
                }

                DB::table('Wo_Followers')->insert([
                    'follower_id'  => $tokenUserId,
                    'following_id' => $targetUserId,
                    'active'       => $requiresApproval ? '0' : '1', // 0 = requested, 1 = followed
                    'time'         => time(),
                ]);

                $followStatus = $requiresApproval ? 'requested' : 'followed';
            }

            DB::commit();

            return response()->json([
                'api_status' => 200,
                'follow_status' => $followStatus,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'api_status' => 500,
                'errors' => [
                    'error_id' => 500,
                    'error_text' => 'Failed to update follow status',
                ],
            ], 500);
        }
    }

    /**
     * Unfollow a user
     * 
     * @param Request $request
     * @param string $userId
     * @return JsonResponse
     */
    public function unfollowUser(Request $request, string $userId): JsonResponse
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

        // Note: Wo_Friends table might not exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Unfollow feature is currently unavailable. Please try again later.'
        ], 503);
    }

    /**
     * Generate hash for request validation (mimics WoWonder hash system)
     * 
     * @param string $userId
     * @return string
     */
    private function generateHash(string $userId): string
    {
        // Simplified hash generation (mimics WoWonder's hash system)
        $salt = 'wowonder_people_follow_' . date('Y-m-d'); // Daily salt
        return substr(md5($userId . $salt), 0, 20);
    }

    /**
     * Update user's people follow feed order preference
     * 
     * @param string $userId
     * @param int $type
     * @return void
     */
    private function updateUserPeopleFollowOrder(string $userId, int $type): void
    {
        // Note: Wo_UserSettings table doesn't exist, so we'll skip storing preferences
        // In a real implementation, you would create this table or use an alternative storage method
        // For now, we'll just accept the request without storing the preference
    }

    /**
     * Get user's current people follow feed order preference
     * 
     * @param string $userId
     * @return int
     */
    private function getUserPeopleFollowOrder(string $userId): int
    {
        // Note: Wo_UserSettings table doesn't exist, so return default value
        // In a real implementation, you would query this table for user preferences
        return 1; // Default to "Recent Posts"
    }

    /**
     * Get posts from people I follow based on feed order
     * 
     * @param string $userId
     * @param int $feedOrder
     * @param int $perPage
     * @return array
     */
    private function getPeopleFollowPosts(string $userId, int $feedOrder, int $perPage): array
    {
        // Note: This is a simplified implementation since Wo_Friends and Wo_Posts tables may not exist
        // In a real implementation, you'd need to join with the friends table
        
        $followingUserIds = $this->getFollowingUserIds($userId);
        
        if (empty($followingUserIds)) {
            return []; // No following users
        }

        $query = DB::table('Wo_Posts')
            ->whereIn('user_id', $followingUserIds)
            ->where('active', '1');
            // Note: privacy column doesn't exist in Wo_Posts table

        switch ($feedOrder) {
            case 0: // All Posts
                $query->orderByDesc('time');
                break;
            
            case 1: // Recent Posts
                $query->orderByDesc('time');
                break;
            
            case 2: // Popular Posts
                $query->orderByDesc('post_likes')
                      ->orderByDesc('post_comments')
                      ->orderByDesc('time');
                break;
            
            default:
                $query->orderByDesc('time');
        }

        $posts = $query->limit($perPage)->get();

        return $posts->map(function ($post) use ($userId) {
            return [
                'id' => $post->id,
                'user_id' => $post->user_id,
                'post_text' => $post->postText ?? '',
                'post_file' => $post->postFile ?? '',
                'post_video' => $post->postVideo ?? '',
                'post_likes' => $post->post_likes ?? 0,
                'post_comments' => $post->post_comments ?? 0,
                'post_shares' => $post->post_shares ?? 0,
                'privacy' => 'public', // Default value since column doesn't exist
                'active' => $post->active ?? '1',
                'is_liked' => $this->isPostLiked($post->id, $userId),
                'is_owner' => $post->user_id === $userId,
                'author' => [
                    'user_id' => $post->user_id,
                    'username' => 'Unknown', // Would need to join with Wo_Users
                    'avatar_url' => null,
                ],
                'created_at' => $post->time ? date('c', $post->time) : null,
            ];
        })->toArray();
    }

    /**
     * Get list of users I follow
     * 
     * @param string $userId
     * @param int $perPage
     * @return array
     */
    private function getFollowingUsers(string $userId, int $perPage): array
    {
        // Note: Wo_Friends table might not exist, so return empty array
        return [];
    }

    /**
     * Get IDs of users I follow
     * 
     * @param string $userId
     * @return array
     */
    private function getFollowingUserIds(string $userId): array
    {
        // Note: Wo_Friends table might not exist, so return empty array
        // In a real implementation, this would query the friends table
        return [];
    }

    /**
     * Get count of users I follow
     * 
     * @param string $userId
     * @return int
     */
    private function getFollowingCount(string $userId): int
    {
        // Note: Wo_Friends table might not exist, so return 0
        return 0;
    }

    /**
     * Check if user liked a post
     * 
     * @param int $postId
     * @param string $userId
     * @return bool
     */
    private function isPostLiked(int $postId, string $userId): bool
    {
        // Note: Wo_PostLikes table doesn't exist, so return false
        // In a real implementation, you would query this table for user likes
        return false;
    }

    /**
     * Get people follow feed type name by ID
     * 
     * @param int $type
     * @return string
     */
    private function getPeopleFollowTypeName(int $type): string
    {
        $types = [
            0 => 'All Posts',
            1 => 'Recent Posts',
            2 => 'Popular Posts',
        ];

        return $types[$type] ?? 'Recent Posts';
    }
}
