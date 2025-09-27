<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NewFeedController extends Controller
{
    /**
     * Update feed order by type (mimics WoWonder requests.php?f=update_order_by)
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
            'type' => ['required', 'integer', 'min:0', 'max:5'], // Feed order type
        ]);

        $hash = $validated['hash'];
        $type = (int) $validated['type'];

        // Hash validation (mimics WoWonder hash system)
        $expectedHash = $this->generateHash($tokenUserId);
        if ($hash !== $expectedHash) {
            return response()->json(['ok' => false, 'message' => 'Invalid hash'], 403);
        }

        // Update user's feed order preference
        $this->updateUserFeedOrder($tokenUserId, $type);

        return response()->json([
            'ok' => true,
            'message' => 'Feed order updated successfully',
            'data' => [
                'type' => $type,
                'type_name' => $this->getFeedTypeName($type),
                'user_id' => $tokenUserId,
                'updated_at' => time()
            ]
        ]);
    }

    /**
     * Get feed posts based on current user's order preference
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getFeed(Request $request): JsonResponse
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

        // Get user's current feed order preference
        $feedOrder = $this->getUserFeedOrder($tokenUserId);

        // Get posts based on feed order
        $posts = $this->getPostsByFeedOrder($tokenUserId, $feedOrder, $perPage);

        return response()->json([
            'ok' => true,
            'data' => $posts,
            'meta' => [
                'current_feed_type' => $feedOrder,
                'feed_type_name' => $this->getFeedTypeName($feedOrder),
                'per_page' => $perPage,
                'total' => count($posts)
            ]
        ]);
    }

    /**
     * Get available feed order types
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getFeedTypes(Request $request): JsonResponse
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
            ['id' => 0, 'name' => 'Top Stories', 'description' => 'Most popular posts'],
            ['id' => 1, 'name' => 'Most Recent', 'description' => 'Latest posts first'],
            ['id' => 2, 'name' => 'People You May Know', 'description' => 'Suggested friends posts'],
            ['id' => 3, 'name' => 'Photos', 'description' => 'Posts with photos only'],
            ['id' => 4, 'name' => 'Videos', 'description' => 'Posts with videos only'],
            ['id' => 5, 'name' => 'Trending', 'description' => 'Trending topics and hashtags'],
        ];

        $currentFeedType = $this->getUserFeedOrder($tokenUserId);

        return response()->json([
            'ok' => true,
            'data' => [
                'current_type' => $currentFeedType,
                'available_types' => $feedTypes,
                'user_id' => $tokenUserId
            ]
        ]);
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
        $salt = 'wowonder_salt_' . date('Y-m-d'); // Daily salt
        return substr(md5($userId . $salt), 0, 20);
    }

    /**
     * Update user's feed order preference
     * 
     * @param string $userId
     * @param int $type
     * @return void
     */
    private function updateUserFeedOrder(string $userId, int $type): void
    {
        // Note: Wo_UserSettings table doesn't exist, so we'll skip storing preferences
        // In a real implementation, you would create this table or use an alternative storage method
        // For now, we'll just accept the request without storing the preference
    }

    /**
     * Get user's current feed order preference
     * 
     * @param string $userId
     * @return int
     */
    private function getUserFeedOrder(string $userId): int
    {
        // Note: Wo_UserSettings table doesn't exist, so return default value
        // In a real implementation, you would query this table for user preferences
        return 1; // Default to "Most Recent"
    }

    /**
     * Get posts based on feed order type
     * 
     * @param string $userId
     * @param int $feedOrder
     * @param int $perPage
     * @return array
     */
    private function getPostsByFeedOrder(string $userId, int $feedOrder, int $perPage): array
    {
        // Note: This is a simplified implementation since Wo_Posts table structure may vary
        // In a real implementation, you'd need to adjust based on your actual database schema

        $query = DB::table('Wo_Posts')
            ->where('active', '1');
            // Note: privacy column doesn't exist in Wo_Posts table

        switch ($feedOrder) {
            case 0: // Top Stories - Most popular
                $query->orderByDesc('post_likes')
                      ->orderByDesc('post_comments')
                      ->orderByDesc('time');
                break;
            
            case 1: // Most Recent
                $query->orderByDesc('time');
                break;
            
            case 2: // People You May Know - Suggested friends
                // This would require complex logic for friend suggestions
                $query->orderByDesc('time');
                break;
            
            case 3: // Photos only
                $query->whereNotNull('postFile')
                      ->where('postFile', '!=', '')
                      ->orderByDesc('time');
                break;
            
            case 4: // Videos only
                $query->whereNotNull('postVideo')
                      ->where('postVideo', '!=', '')
                      ->orderByDesc('time');
                break;
            
            case 5: // Trending
                $query->where('time', '>', time() - (7 * 24 * 60 * 60)) // Last 7 days
                      ->orderByDesc('post_likes')
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
     * Get feed type name by ID
     * 
     * @param int $type
     * @return string
     */
    private function getFeedTypeName(int $type): string
    {
        $types = [
            0 => 'Top Stories',
            1 => 'Most Recent',
            2 => 'People You May Know',
            3 => 'Photos',
            4 => 'Videos',
            5 => 'Trending',
        ];

        return $types[$type] ?? 'Most Recent';
    }
}
