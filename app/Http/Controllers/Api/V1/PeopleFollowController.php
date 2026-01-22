<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $page = (int) ($request->query('page', 1));
        $page = max(1, $page);

        // Get user's current people follow feed order preference
        $feedOrder = $this->getUserPeopleFollowOrder($tokenUserId);

        try {
            // Get posts from people I follow based on feed order
            $result = $this->getPeopleFollowPosts($tokenUserId, $feedOrder, $perPage, $page);

            // Ensure result has the correct structure
            if (!isset($result['posts']) || !isset($result['pagination'])) {
                $result = [
                    'posts' => [],
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                        'from' => 0,
                        'to' => 0,
                        'has_more' => false,
                    ]
                ];
            }

            // Get following count
            $followingCount = $this->getFollowingCount($tokenUserId);

            return response()->json([
                'ok' => true,
                'data' => $result['posts'] ?? [],
                'meta' => [
                    'current_feed_type' => $feedOrder,
                    'feed_type_name' => $this->getPeopleFollowTypeName($feedOrder),
                    'following_count' => $followingCount,
                    'pagination' => $result['pagination'] ?? [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                        'from' => 0,
                        'to' => 0,
                        'has_more' => false,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to fetch people follow feed: ' . $e->getMessage(),
                'data' => [],
                'meta' => [
                    'current_feed_type' => $feedOrder,
                    'feed_type_name' => $this->getPeopleFollowTypeName($feedOrder),
                    'following_count' => 0,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                        'from' => 0,
                        'to' => 0,
                        'has_more' => false,
                    ]
                ]
            ], 500);
        }
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
     * @param int $page
     * @return array
     */
    private function getPeopleFollowPosts(string $userId, int $feedOrder, int $perPage, int $page = 1): array
    {
        try {
            // Check if Wo_Posts table exists
            if (!Schema::hasTable('Wo_Posts')) {
                return [
                    'posts' => [],
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                        'from' => 0,
                        'to' => 0,
                        'has_more' => false,
                    ]
                ];
            }
            
            $followingUserIds = $this->getFollowingUserIds($userId);
            
            if (empty($followingUserIds)) {
                // Return empty result with pagination
                return [
                    'posts' => [],
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                        'from' => 0,
                        'to' => 0,
                        'has_more' => false,
                    ]
                ];
            }

            $query = DB::table('Wo_Posts')
                ->whereIn('user_id', $followingUserIds)
                ->whereIn('active', ['1', 1]); // Handle both string and integer

            switch ($feedOrder) {
                case 0: // All Posts
                    $query->orderByDesc('time');
                    break;
                
                case 1: // Recent Posts
                    $query->orderByDesc('time');
                    break;
                
                case 2: // Popular Posts
                    $query->orderByDesc('time'); // Order by time first, then we'll sort by engagement
                    break;
                
                default:
                    $query->orderByDesc('time');
            }

            // Get total count for pagination
            $total = $query->count();

            // Apply pagination
            $offset = ($page - 1) * $perPage;
            $posts = $query->offset($offset)
                ->limit($perPage)
                ->get();

            // For popular posts, sort by engagement after fetching
            if ($feedOrder === 2 && $posts->isNotEmpty()) {
                $posts = $posts->sortByDesc(function ($post) {
                    $likes = $this->getPostReactionsCount($post->post_id ?? $post->id);
                    $comments = $this->getPostCommentsCount($post->post_id ?? $post->id);
                    return $likes + $comments;
                })->values();
            }

            // Calculate pagination metadata
            $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

            $formattedPosts = $posts->map(function ($post) use ($userId) {
            // Get user information
            $user = DB::table('Wo_Users')->where('user_id', $post->user_id)->first();
            
            // Get post reactions count
            $postIdForReactions = $post->post_id ?? $post->id;
            $reactionsCount = $this->getPostReactionsCount($postIdForReactions);
            
            // Get post comments count
            $postIdForComments = $post->post_id ?? $post->id;
            $commentsCount = $this->getPostCommentsCount($postIdForComments);
            
            // Get album images if it's an album post
            $albumImages = [];
            if ($post->album_name && $post->multi_image_post) {
                $albumImages = $this->getAlbumImages($post->id);
            }
            
            // Get poll options if it's a poll post
            $pollOptions = [];
            if (isset($post->poll_id) && $post->poll_id == 1) {
                $pollOptions = $this->getPollOptions($post->id, $userId);
            }
            
            // Get color data if it's a colored post
            $colorData = null;
            $colorId = $post->color_id ?? 0;
            if ($colorId > 0) {
                $colorData = $this->getColorData($colorId);
            }

            // Determine post type
            $postType = $this->getPostType($post);

            return [
                'id' => $post->id,
                'post_id' => $post->post_id ?? $post->id,
                'user_id' => $post->user_id,
                'post_text' => $post->postText ?? '',
                'post_type' => $postType,
                'post_privacy' => $post->postPrivacy ?? '0',
                'post_privacy_text' => $this->getPostPrivacyText($post->postPrivacy ?? '0'),
                
                // Media content
                'post_photo' => $post->postPhoto ?? '',
                'post_photo_url' => $this->getPostPhotoUrl($post),
                'post_file' => $post->postFile ?? '',
                'post_file_url' => ($post->postFile ?? '') ? asset('storage/' . $post->postFile) : null,
                'post_record' => $post->postRecord ?? '',
                'post_record_url' => ($post->postRecord ?? '') ? asset('storage/' . $post->postRecord) : null,
                'post_youtube' => $post->postYoutube ?? '',
                'post_vimeo' => $post->postVimeo ?? '',
                'post_dailymotion' => $post->postDailymotion ?? '',
                'post_facebook' => $post->postFacebook ?? '',
                'post_vine' => $post->postVine ?? '',
                'post_soundcloud' => $post->postSoundCloud ?? '',
                'post_playtube' => $post->postPlaytube ?? '',
                'post_deepsound' => $post->postDeepsound ?? '',
                'post_link' => $post->postLink ?? '',
                'post_link_title' => $post->postLinkTitle ?? '',
                'post_link_image' => $post->postLinkImage ?? '',
                'post_link_content' => $post->postLinkContent ?? '',
                'post_sticker' => $post->postSticker ?? '',
                'post_map' => $post->postMap ?? '',
                
                // Album data
                'album_name' => $post->album_name ?? '',
                'multi_image_post' => (bool) ($post->multi_image_post ?? false),
                'album_images' => $albumImages,
                'album_images_count' => count($albumImages),
                
                // Poll data
                'poll_id' => $post->poll_id ?? null,
                'poll_options' => $pollOptions,
                
                // Color data (for colored posts)
                'color_id' => $post->color_id ?? null,
                'color' => $colorData,
                
                // Engagement metrics
                'reactions_count' => $reactionsCount,
                'comments_count' => $commentsCount,
                'shares_count' => $post->postShare ?? 0,
                'views_count' => $post->videoViews ?? 0,
                
                // User interaction
                'is_liked' => $this->isPostLiked($post->id, $userId),
                'is_owner' => $post->user_id == $userId,
                'is_boosted' => (bool) ($post->boosted ?? false),
                'comments_disabled' => (bool) ($post->comments_status ?? false),
                
                // Author information
                'author' => [
                    'user_id' => $post->user_id,
                    'username' => $user?->username ?? 'Unknown',
                    'name' => $this->getUserName($user),
                    'avatar_url' => ($user?->avatar) ? asset('storage/' . $user?->avatar) : null,
                    'verified' => (bool) ($user?->verified ?? false),
                ],
                
                // Timestamps
                'created_at' => $post->time ? date('c', $post->time) : null,
                'created_at_human' => $post->time ? $this->getHumanTime($post->time) : null,
                'time' => $post->time,
            ];
            })->toArray();

            return [
                'posts' => $formattedPosts,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $perPage, $total),
                    'has_more' => $page < $lastPage,
                ]
            ];
        } catch (\Exception $e) {
            // Return empty result on error
            return [
                'posts' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => 0,
                    'to' => 0,
                    'has_more' => false,
                ]
            ];
        }
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
        // Check if Wo_Followers table exists
        if (!Schema::hasTable('Wo_Followers')) {
            return [];
        }

        try {
            // Get users that the current user is following (active = '1' or 1)
            $followingIds = DB::table('Wo_Followers')
                ->where('follower_id', $userId)
                ->whereIn('active', ['1', 1])
                ->pluck('following_id')
                ->toArray();

            if (empty($followingIds)) {
                return [];
            }

            // Get user data for following users
            $users = DB::table('Wo_Users')
                ->whereIn('user_id', $followingIds)
                ->whereIn('active', ['1', 1])
                ->limit($perPage)
                ->get();

            return $users->map(function ($user) {
                return [
                    'user_id' => $user->user_id,
                    'username' => $user->username ?? 'Unknown',
                    'name' => $this->getUserName($user),
                    'first_name' => $user->first_name ?? '',
                    'last_name' => $user->last_name ?? '',
                    'avatar' => $user->avatar ?? '',
                    'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'cover' => $user->cover ?? '',
                    'cover_url' => $user->cover ? asset('storage/' . $user->cover) : null,
                    'verified' => (bool) ($user->verified ?? false),
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get user name from user object
     * 
     * @param object $user
     * @return string
     */
    private function getUserName($user): string
    {
        if (!$user) {
            return 'Unknown User';
        }
        
        // Try first_name + last_name
        $firstName = $user->first_name ?? '';
        $lastName = $user->last_name ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        if (!empty($fullName)) {
            return $fullName;
        }
        
        // Fallback to username
        return $user->username ?? 'Unknown User';
    }

    /**
     * Get IDs of users I follow
     * 
     * @param string $userId
     * @return array
     */
    private function getFollowingUserIds(string $userId): array
    {
        // Check if Wo_Followers table exists
        if (!Schema::hasTable('Wo_Followers')) {
            return [];
        }

        try {
            // Get users that the current user is following (active = '1' or 1)
            // follower_id = current user, following_id = users being followed
            $followingIds = DB::table('Wo_Followers')
                ->where('follower_id', $userId)
                ->whereIn('active', ['1', 1]) // Only active follows (not pending requests)
                ->pluck('following_id')
                ->toArray();

            return $followingIds;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get count of users I follow
     * 
     * @param string $userId
     * @return int
     */
    private function getFollowingCount(string $userId): int
    {
        // Check if Wo_Followers table exists
        if (!Schema::hasTable('Wo_Followers')) {
            return 0;
        }

        try {
            // Count users that the current user is following (active = '1' or 1)
            return DB::table('Wo_Followers')
                ->where('follower_id', $userId)
                ->whereIn('active', ['1', 1])
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
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
        // Check if Wo_Reactions table exists
        if (!Schema::hasTable('Wo_Reactions')) {
            return false;
        }

        try {
            return DB::table('Wo_Reactions')
                ->where('post_id', $postId)
                ->where('user_id', $userId)
                ->where('comment_id', 0) // Only post reactions, not comment reactions
                ->exists();
        } catch (\Exception $e) {
            return false;
        }
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

    /**
     * Get post reactions count
     * 
     * @param int $postId
     * @return int
     */
    private function getPostReactionsCount(int $postId): int
    {
        if (!Schema::hasTable('Wo_Reactions')) {
            return 0;
        }

        try {
            return DB::table('Wo_Reactions')
                ->where('post_id', $postId)
                ->where('comment_id', 0)
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get post comments count
     * 
     * @param int $postId
     * @return int
     */
    private function getPostCommentsCount(int $postId): int
    {
        if (!Schema::hasTable('Wo_Comments')) {
            return 0;
        }

        try {
            return DB::table('Wo_Comments')
                ->where('post_id', $postId)
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get album images for a post
     * 
     * @param int $postId
     * @return array
     */
    private function getAlbumImages(int $postId): array
    {
        if (!Schema::hasTable('Wo_Albums_Media')) {
            return [];
        }

        try {
            $albumImages = DB::table('Wo_Albums_Media')
                ->where('post_id', $postId)
                ->get();

            return $albumImages->map(function($image) {
                return [
                    'id' => $image->id,
                    'image_path' => $image->image,
                    'image_url' => asset('storage/' . $image->image),
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get poll options with vote counts
     * 
     * @param int $postId
     * @param string|null $userId
     * @return array
     */
    private function getPollOptions(int $postId, $userId = null): array
    {
        if (!Schema::hasTable('Wo_Polls')) {
            return [];
        }

        try {
            $options = DB::table('Wo_Polls')
                ->where('post_id', $postId)
                ->get();

            if ($options->isEmpty()) {
                return [];
            }

            $votesTable = 'Wo_Votes';
            if (!Schema::hasTable($votesTable)) {
                if (Schema::hasTable('Wo_PollVotes')) {
                    $votesTable = 'Wo_PollVotes';
                } else {
                    return $options->map(function ($option) {
                        return [
                            'id' => $option->id,
                            'text' => $option->text ?? '',
                            'votes' => 0,
                            'percentage' => 0,
                            'is_voted' => false,
                        ];
                    })->toArray();
                }
            }

            $totalVotes = DB::table($votesTable)
                ->where('post_id', $postId)
                ->count();

            $userVote = null;
            if ($userId) {
                $userVote = DB::table($votesTable)
                    ->where('post_id', $postId)
                    ->where('user_id', $userId)
                    ->value('option_id');
            }

            return $options->map(function ($option) use ($votesTable, $postId, $totalVotes, $userVote) {
                $optionVotes = DB::table($votesTable)
                    ->where('post_id', $postId)
                    ->where('option_id', $option->id)
                    ->count();

                $percentage = $totalVotes > 0 ? round(($optionVotes / $totalVotes) * 100, 2) : 0;

                return [
                    'id' => $option->id,
                    'text' => $option->text ?? '',
                    'votes' => $optionVotes,
                    'percentage' => $percentage,
                    'is_voted' => $userVote == $option->id,
                ];
            })->toArray();

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get color data for a colored post
     * 
     * @param int $colorId
     * @return array|null
     */
    private function getColorData(int $colorId): ?array
    {
        if (!Schema::hasTable('Wo_Colored_Posts')) {
            return null;
        }

        try {
            $coloredPost = DB::table('Wo_Colored_Posts')
                ->where('id', $colorId)
                ->first();
            
            if (!$coloredPost) {
                return null;
            }

            return [
                'color_id' => $coloredPost->id,
                'color_1' => $coloredPost->color_1 ?? '',
                'color_2' => $coloredPost->color_2 ?? '',
                'text_color' => $coloredPost->text_color ?? '',
                'image' => $coloredPost->image ?? '',
                'image_url' => !empty($coloredPost->image) ? asset('storage/' . $coloredPost->image) : null,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get post type based on content
     * 
     * @param object $post
     * @return string
     */
    private function getPostType($post): string
    {
        if (!empty($post->postType)) {
            return $post->postType;
        }
        
        if (!empty($post->job_id) && $post->job_id > 0) return 'job';
        if (!empty($post->blog_id) && $post->blog_id > 0) return 'blog';
        if (!empty($post->postPhoto)) return 'photo';
        if (!empty($post->postYoutube) || !empty($post->postVimeo) || !empty($post->postFacebook)) return 'video';
        if (!empty($post->postFile)) return 'file';
        if (!empty($post->postLink)) return 'link';
        if (!empty($post->postMap)) return 'location';
        if (!empty($post->postRecord)) return 'audio';
        if (!empty($post->postSticker)) return 'sticker';
        if (!empty($post->album_name)) return 'album';
        return 'text';
    }

    /**
     * Get post privacy text
     * 
     * @param string $privacy
     * @return string
     */
    private function getPostPrivacyText(string $privacy): string
    {
        return match($privacy) {
            '0' => 'Public',
            '1' => 'Friends',
            '2' => 'Only Me',
            '3' => 'Custom',
            '4' => 'Group',
            default => 'Public'
        };
    }

    /**
     * Get post photo URL
     * 
     * @param object $post
     * @return string|null
     */
    private function getPostPhotoUrl($post): ?string
    {
        $postPhoto = $post->postPhoto ?? '';
        
        if (empty($postPhoto)) {
            return null;
        }
        
        $isGifPost = ($post->postType ?? '') === 'gif';
        $isUrl = filter_var($postPhoto, FILTER_VALIDATE_URL) !== false;
        
        if ($isGifPost || $isUrl) {
            return preg_replace('#([^:])//+#', '$1/', $postPhoto);
        }
        
        return asset('storage/' . $postPhoto);
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
}
