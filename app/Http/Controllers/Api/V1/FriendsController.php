<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Friend;
use App\Models\FriendRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FriendsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $type = $request->query('type', 'all');
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));
        $page = (int) ($request->query('page', 1));
        $page = max(1, $page);

        try {
            // Check if Wo_Followers table exists
            if (!Schema::hasTable('Wo_Followers')) {
                return response()->json([
                    'ok' => true,
                    'data' => [],
                    'meta' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                    ],
                ]);
            }

            // Get friends (mutual followers - users who both follow each other with active = '1')
            // WoWonder logic: Friends = users where current user follows them AND they follow current user back
            
            // Step 1: Get IDs of users that current user is following (active = '1')
            $followingIds = DB::table('Wo_Followers')
                ->where('follower_id', $tokenUserId)
                ->whereIn('active', ['1', 1])
                ->pluck('following_id')
                ->toArray();

            if (empty($followingIds)) {
                return response()->json([
                    'ok' => true,
                    'data' => [],
                    'meta' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                    ],
                ]);
            }

            // Step 2: Get IDs of users who follow current user back (mutual = friends)
            $friendIds = DB::table('Wo_Followers')
                ->where('following_id', $tokenUserId)
                ->whereIn('follower_id', $followingIds)
                ->whereIn('active', ['1', 1])
                ->pluck('follower_id')
                ->toArray();

            if (empty($friendIds)) {
                return response()->json([
                    'ok' => true,
                    'data' => [],
                    'meta' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                    ],
                ]);
            }

            // Get total count
            $total = count($friendIds);

            // Get paginated friend user data
            $offset = ($page - 1) * $perPage;
            $paginatedFriendIds = array_slice($friendIds, $offset, $perPage);

            $friendsData = DB::table('Wo_Users')
                ->whereIn('user_id', $paginatedFriendIds)
                ->whereIn('active', ['1', 1])
                ->orderByDesc('lastseen')
                ->get();

            // Format friends data
            $data = [];
            foreach ($friendsData as $friend) {
                // Get mutual friends count
                $mutualFriendsCount = $this->getMutualFriendsCount($tokenUserId, $friend->user_id, $friendIds);

                $data[] = [
                    'user_id' => $friend->user_id,
                    'username' => $friend->username ?? 'Unknown',
                    'name' => $this->getUserName($friend),
                    'first_name' => $friend->first_name ?? '',
                    'last_name' => $friend->last_name ?? '',
                    'email' => $friend->email ?? '',
                    'avatar' => $friend->avatar ?? '',
                    'avatar_url' => $friend->avatar ? asset('storage/' . $friend->avatar) : null,
                    'cover' => $friend->cover ?? '',
                    'cover_url' => $friend->cover ? asset('storage/' . $friend->cover) : null,
                    'verified' => (bool) ($friend->verified ?? false),
                    'is_following' => true, // They are mutual friends
                    'is_following_me' => true, // They are mutual friends
                    'is_friend' => true,
                    'mutual_friends_count' => $mutualFriendsCount,
                    'lastseen' => $friend->lastseen ?? time(),
                    'lastseen_time_text' => $this->getTimeElapsedString($friend->lastseen ?? time()),
                ];
            }

            // Calculate pagination metadata
            $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

            return response()->json([
                'ok' => true,
                'data' => $data,
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $perPage, $total),
                    'has_more' => $page < $lastPage,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to fetch friends: ' . $e->getMessage(),
                'data' => [],
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ], 500);
        }
    }

    /**
     * Get mutual friends count between two users
     * 
     * @param string $userId1
     * @param string $userId2
     * @param array $user1FriendIds Optional pre-fetched friend IDs for user1
     * @return int
     */
    private function getMutualFriendsCount(string $userId1, string $userId2, array $user1FriendIds = []): int
    {
        // Get user2's friends (mutual followers)
        $user2FollowingIds = DB::table('Wo_Followers')
            ->where('follower_id', $userId2)
            ->whereIn('active', ['1', 1])
            ->pluck('following_id')
            ->toArray();

        if (empty($user2FollowingIds)) {
            return 0;
        }

        $user2FriendIds = DB::table('Wo_Followers')
            ->where('following_id', $userId2)
            ->whereIn('follower_id', $user2FollowingIds)
            ->whereIn('active', ['1', 1])
            ->pluck('follower_id')
            ->toArray();

        // If user1 friend IDs not provided, fetch them
        if (empty($user1FriendIds)) {
            $user1FollowingIds = DB::table('Wo_Followers')
                ->where('follower_id', $userId1)
                ->whereIn('active', ['1', 1])
                ->pluck('following_id')
                ->toArray();

            $user1FriendIds = DB::table('Wo_Followers')
                ->where('following_id', $userId1)
                ->whereIn('follower_id', $user1FollowingIds)
                ->whereIn('active', ['1', 1])
                ->pluck('follower_id')
                ->toArray();
        }

        // Count mutual friends (excluding user2 from the count)
        $mutualFriends = array_intersect($user1FriendIds, $user2FriendIds);
        $mutualFriends = array_diff($mutualFriends, [$userId2]); // Exclude user2 from count

        return count($mutualFriends);
    }

    public function search(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $term = $request->query('term', '');
        if (empty($term)) {
            return response()->json([
                'data' => [
                    'users' => [],
                    'total' => 0,
                ],
            ]);
        }

        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));
        $page = (int) ($request->query('page', 1));
        $page = max(1, $page);

        try {
            // Check if Wo_Users table exists
            if (!Schema::hasTable('Wo_Users')) {
                return response()->json([
                    'data' => [
                        'users' => [],
                        'total' => 0,
                    ],
                ]);
            }

            // Get blocked users to exclude from search
            $blockedIds = [$tokenUserId]; // Exclude self
            if (Schema::hasTable('Wo_Blocks')) {
                $blocked = DB::table('Wo_Blocks')
                    ->where('blocker', $tokenUserId)
                    ->pluck('blocked')
                    ->toArray();
                $blockedIds = array_merge($blockedIds, $blocked);
                
                // Also exclude users who blocked the current user
                $blockedBy = DB::table('Wo_Blocks')
                    ->where('blocked', $tokenUserId)
                    ->pluck('blocker')
                    ->toArray();
                $blockedIds = array_merge($blockedIds, $blockedBy);
            }
            $blockedIds = array_unique($blockedIds);

            // Search users by username, name, first_name, and last_name
            $query = DB::table('Wo_Users')
                ->where('active', '1')
                ->where('user_id', '!=', $tokenUserId);

            // Exclude blocked users
            if (!empty($blockedIds)) {
                $query->whereNotIn('user_id', $blockedIds);
            }

            // Search in username, first_name, and last_name
            $searchTerm = '%' . $term . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('username', 'LIKE', $searchTerm)
                  ->orWhere('first_name', 'LIKE', $searchTerm)
                  ->orWhere('last_name', 'LIKE', $searchTerm)
                  ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", [$searchTerm]);
            });

            // Get total count
            $total = $query->count();

            // Apply pagination
            $offset = ($page - 1) * $perPage;
            $usersData = $query->orderByDesc('lastseen')
                ->orderByDesc('user_id')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            // Format users
            $users = [];
            foreach ($usersData as $user) {
                // Check if user is following current user
                $isFollowing = $this->isFollowing($tokenUserId, $user->user_id);
                $isFollowingMe = $this->isFollowing($user->user_id, $tokenUserId);
                $isFriend = $this->isFriend($tokenUserId, $user->user_id);

                // Get mutual friends count
                $mutualFriendsCount = 0;
                if (Schema::hasTable('Wo_Followers')) {
                    $currentUserFollowing = DB::table('Wo_Followers')
                        ->where('follower_id', $tokenUserId)
                        ->where('active', '1')
                        ->pluck('following_id')
                        ->toArray();
                    
                    $userFollowing = DB::table('Wo_Followers')
                        ->where('follower_id', $user->user_id)
                        ->where('active', '1')
                        ->pluck('following_id')
                        ->toArray();
                    
                    $mutualFriendsCount = count(array_intersect($currentUserFollowing, $userFollowing));
                }

                $users[] = [
                    'user_id' => $user->user_id,
                    'username' => $user->username ?? 'Unknown',
                    'name' => $this->getUserName($user),
                    'first_name' => $user->first_name ?? '',
                    'last_name' => $user->last_name ?? '',
                    'email' => $user->email ?? '',
                    'avatar' => $user->avatar ?? '',
                    'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'cover' => $user->cover ?? '',
                    'cover_url' => $user->cover ? asset('storage/' . $user->cover) : null,
                    'verified' => (bool) ($user->verified ?? false),
                    'is_following' => $isFollowing,
                    'is_following_me' => $isFollowingMe,
                    'is_friend' => $isFriend,
                    'mutual_friends_count' => $mutualFriendsCount,
                    'lastseen' => $user->lastseen ?? time(),
                    'lastseen_time_text' => $this->getTimeElapsedString($user->lastseen ?? time()),
                ];
            }

            // Calculate pagination metadata
            $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

            return response()->json([
                'data' => [
                    'users' => $users,
                    'total' => $total,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'last_page' => $lastPage,
                        'from' => $total > 0 ? $offset + 1 : 0,
                        'to' => min($offset + $perPage, $total),
                        'has_more' => $page < $lastPage,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to search users: ' . $e->getMessage(),
                'data' => [
                    'users' => [],
                    'total' => 0,
                ],
            ], 500);
        }
    }

    public function requests(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));
        $page = (int) ($request->query('page', 1));
        $page = max(1, $page);

        try {
            // Check if Wo_Followers table exists
            if (!Schema::hasTable('Wo_Followers')) {
                return response()->json([
                    'ok' => true,
                    'data' => [],
                    'meta' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                        'from' => 0,
                        'to' => 0,
                        'has_more' => false,
                    ],
                ]);
            }

            // Friend requests are stored in Wo_Followers table with active = '0' or 0
            // following_id = current user (who receives the request)
            // follower_id = user who sent the request
            // Handle both string '0' and integer 0 for active field
            $query = DB::table('Wo_Followers')
                ->where('following_id', $tokenUserId) // Requests received by current user
                ->where(function($q) {
                    // Check for both string '0' and integer 0
                    $q->where('active', '=', '0')
                      ->orWhere('active', '=', 0);
                }); // Pending requests

            // Get total count
            $total = $query->count();

            // Get paginated results
            $offset = ($page - 1) * $perPage;
            $requests = $query->orderByDesc('time')
                ->orderByDesc('id') // Secondary sort for consistency
                ->offset($offset)
                ->limit($perPage)
                ->get();

            // Format the requests with user data
            $data = [];
            foreach ($requests as $requestItem) {
                $requesterId = $requestItem->follower_id; // User who sent the request
                
                // Get requester user data
                // Handle both string '1' and integer 1 for active field
                $requester = DB::table('Wo_Users')
                    ->where('user_id', $requesterId)
                    ->whereIn('active', ['1', 1])
                    ->first();

                if ($requester) {
                    // Get mutual friends count
                    $mutualFriendsCount = 0;
                    if (Schema::hasTable('Wo_Followers')) {
                        // Get current user's following
                        $currentUserFollowing = DB::table('Wo_Followers')
                            ->where('follower_id', $tokenUserId)
                            ->where('active', '1')
                            ->pluck('following_id')
                            ->toArray();
                        
                        // Get requester's following
                        $requesterFollowing = DB::table('Wo_Followers')
                            ->where('follower_id', $requesterId)
                            ->where('active', '1')
                            ->pluck('following_id')
                            ->toArray();
                        
                        // Count mutual
                        $mutualFriendsCount = count(array_intersect($currentUserFollowing, $requesterFollowing));
                    }

                    $data[] = [
                        'id' => $requesterId,
                        'user_id' => $requesterId,
                        'username' => $requester->username ?? 'Unknown',
                        'name' => $this->getUserName($requester),
                        'first_name' => $requester->first_name ?? '',
                        'last_name' => $requester->last_name ?? '',
                        'email' => $requester->email ?? '',
                        'avatar' => $requester->avatar ?? '',
                        'avatar_url' => $requester->avatar ? asset('storage/' . $requester->avatar) : null,
                        'cover' => $requester->cover ?? '',
                        'cover_url' => $requester->cover ? asset('storage/' . $requester->cover) : null,
                        'verified' => (bool) ($requester->verified ?? false),
                        'mutual_friends_count' => $mutualFriendsCount,
                        'request_time' => $requestItem->time ?? time(),
                        'request_time_text' => $this->getTimeElapsedString($requestItem->time ?? time()),
                    ];
                }
            }

            // Calculate pagination metadata
            $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

            return response()->json([
                'ok' => true,
                'data' => $data,
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $perPage, $total),
                    'has_more' => $page < $lastPage,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to fetch friend requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user name from user object (handles different column structures)
     * 
     * @param object $user
     * @return string
     */
    private function getUserName($user): string
    {
        // Try name column first
        if (isset($user->name) && !empty($user->name)) {
            return $user->name;
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

    public function sendRequest(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        // Validate user_id parameter (matching old API: follow-user.php)
        $validated = $request->validate([
            'user_id' => ['required', 'string'],
        ]);

        $recipientId = $validated['user_id'];

        // Check if user is trying to follow themselves
        if ($tokenUserId == $recipientId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 3,
                    'error_text' => 'Cannot follow yourself'
                ]
            ], 400);
        }

        // Check if recipient user exists
        $recipientData = DB::table('Wo_Users')
            ->where('user_id', $recipientId)
            ->first();

        if (empty($recipientData)) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Recipient user not found'
                ]
            ], 404);
        }

        try {
            DB::beginTransaction();

            $followMessage = 'invalid';

            // Check if already following or has pending request (matching old API logic)
            // Handle both string and integer values for active field
            $isFollowing = DB::table('Wo_Followers')
                ->where('follower_id', $tokenUserId)
                ->where('following_id', $recipientId)
                ->whereIn('active', ['1', 1])
                ->exists();

            $isFollowRequested = DB::table('Wo_Followers')
                ->where('follower_id', $tokenUserId)
                ->where('following_id', $recipientId)
                ->whereIn('active', ['0', 0])
                ->exists();

            // If already following or has pending request, unfollow/remove request
            if ($isFollowing || $isFollowRequested) {
                $deleted = DB::table('Wo_Followers')
                    ->where('follower_id', $tokenUserId)
                    ->where('following_id', $recipientId)
                    ->delete();

                if ($deleted) {
                    $followMessage = 'unfollowed';
                    // Update follow counts
                    $this->updateFollowCounts($tokenUserId, $recipientId);
                }
            } else {
                // Friend requests should ALWAYS require approval (pending state)
                // This creates a proper "friend request" system where users must accept requests
                // All requests are stored as pending (active = 0) until recipient accepts
                $requiresApproval = true;

                // Register follow (matching old API: Wo_RegisterFollow)
                // Always use 0 for pending requests
                $activeValue = 0; // Always pending until accepted
                
                $followData = [
                    'follower_id' => $tokenUserId,
                    'following_id' => $recipientId,
                    'active' => $activeValue, // 0 = pending, 1 = accepted
                    'time' => time(),
                ];

                DB::table('Wo_Followers')->insert($followData);

                // Update follow counts
                $this->updateFollowCounts($tokenUserId, $recipientId);

                // Send notification for follow/follow request
                $this->sendFollowNotification($tokenUserId, $recipientId, $requiresApproval);

                // Check if it's a request or direct follow
                if ($requiresApproval) {
                    $followMessage = 'requested';
                } else {
                    $followMessage = 'followed';
                }
            }

            DB::commit();

            // Return response matching old API format
            return response()->json([
                'api_status' => 200,
                'follow_status' => $followMessage,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 500,
                    'error_text' => 'Failed to process follow request: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    public function acceptRequest(Request $request, $id): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        // $id is the follower_id (the person who sent the request)
        // $tokenUserId is the following_id (the person accepting the request)
        // Matching old API: Wo_AcceptFollowRequest($recipient_id, $wo['user']['user_id'])
        $followerId = $id; // The person who sent the request
        $followingId = $tokenUserId; // The person accepting

        // Check if follower user exists (matching old API validation)
        $followerData = DB::table('Wo_Users')
            ->where('user_id', $followerId)
            ->first();

        if (empty($followerData)) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Recipient user not found'
                ]
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Find pending follow request (matching old API: Wo_AcceptFollowRequest)
            // follower_id = person who sent request, following_id = person accepting
            $followRequest = DB::table('Wo_Followers')
                ->where('follower_id', $followerId)
                ->where('following_id', $followingId)
                ->where('active', '0') // Pending request
                ->first();

            if (!$followRequest) {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 6,
                        'error_text' => 'Follow request not found'
                    ]
                ], 404);
            }

            // Accept the request by setting active = '1' (matching old API logic)
            $updated = DB::table('Wo_Followers')
                ->where('follower_id', $followerId)
                ->where('following_id', $followingId)
                ->where('active', '0')
                ->update(['active' => '1']);

            if ($updated) {
                // Update follow counts
                $this->updateFollowCounts($followerId, $followingId);

                DB::commit();

                // Return response matching old API format
                return response()->json([
                    'api_status' => 200
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 500,
                        'error_text' => 'Failed to accept follow request'
                    ]
                ], 500);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 500,
                    'error_text' => 'Failed to accept follow request: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    public function declineRequest(Request $request, $id): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        // $id is the follower_id (the person who sent the request)
        // $tokenUserId is the following_id (the person declining the request)
        // Matching old API: Wo_DeleteFollowRequest($recipient_id, $wo['user']['user_id'])
        $followerId = $id; // The person who sent the request
        $followingId = $tokenUserId; // The person declining

        // Check if follower user exists (matching old API validation)
        $followerData = DB::table('Wo_Users')
            ->where('user_id', $followerId)
            ->first();

        if (empty($followerData)) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Recipient user not found'
                ]
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Find pending follow request (matching old API: Wo_DeleteFollowRequest)
            // follower_id = person who sent request, following_id = person declining
            $followRequest = DB::table('Wo_Followers')
                ->where('follower_id', $followerId)
                ->where('following_id', $followingId)
                ->where('active', '0') // Pending request
                ->first();

            if (!$followRequest) {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 6,
                        'error_text' => 'Follow request not found'
                    ]
                ], 404);
            }

            // Decline the request by deleting it (matching old API logic)
            $deleted = DB::table('Wo_Followers')
                ->where('follower_id', $followerId)
                ->where('following_id', $followingId)
                ->where('active', '0')
                ->delete();

            if ($deleted) {
                DB::commit();

                // Return response matching old API format
                return response()->json([
                    'api_status' => 200
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 500,
                        'error_text' => 'Failed to decline follow request'
                    ]
                ], 500);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 500,
                    'error_text' => 'Failed to decline follow request: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    public function removeFriend(Request $request, $id): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        // $id is the following_id (the friend to remove)
        // $tokenUserId is the follower_id (the current user)
        // Matching old API: Wo_DeleteFollow($recipient_id, $wo['user']['user_id'])
        $followingId = $id; // The friend to remove
        $followerId = $tokenUserId; // The current user

        // Check if user is trying to remove themselves
        if ($followerId == $followingId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 3,
                    'error_text' => 'Cannot remove yourself'
                ]
            ], 400);
        }

        // Check if friend user exists (matching old API validation)
        $friendData = DB::table('Wo_Users')
            ->where('user_id', $followingId)
            ->first();

        if (empty($friendData)) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'User not found'
                ]
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Check if following relationship exists (matching old API: Wo_IsFollowing or Wo_IsFollowRequested)
            $isFollowing = DB::table('Wo_Followers')
                ->where('follower_id', $followerId)
                ->where('following_id', $followingId)
                ->where('active', '1')
                ->exists();

            $isFollowRequested = DB::table('Wo_Followers')
                ->where('follower_id', $followerId)
                ->where('following_id', $followingId)
                ->where('active', '0')
                ->exists();

            if (!$isFollowing && !$isFollowRequested) {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 6,
                        'error_text' => 'Not following this user'
                    ]
                ], 404);
            }

            // Delete follow relationship (matching old API: Wo_DeleteFollow)
            $deleted = DB::table('Wo_Followers')
                ->where('follower_id', $followerId)
                ->where('following_id', $followingId)
                ->delete();

            if ($deleted) {
                // Update follow counts
                $this->updateFollowCounts($followerId, $followingId);

                DB::commit();

                // Return response matching old API format
                return response()->json([
                    'api_status' => 200,
                    'message' => 'Friend removed successfully'
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 500,
                        'error_text' => 'Failed to remove friend'
                    ]
                ], 500);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 500,
                    'error_text' => 'Failed to remove friend: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    public function blockUser(Request $request, $id): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'No session sent.'
                ]
            ], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'Session id is wrong.'
                ]
            ], 401);
        }

        // $id is the recipient_id (the user to block)
        // $tokenUserId is the current user (blocker)
        // Matching old API: Wo_RegisterBlock($recipient_id)
        $recipientId = $id; // The user to block
        $blockerId = $tokenUserId; // The current user

        // Check if user is trying to block themselves
        if ($blockerId == $recipientId) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'Cannot block yourself.'
                ]
            ], 400);
        }

        // Check if recipient user exists (matching old API validation)
        $recipientData = DB::table('Wo_Users')
            ->where('user_id', $recipientId)
            ->first();

        if (empty($recipientData)) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'User Profile is not exists.'
                ]
            ], 404);
        }

        // Check if recipient is admin (cannot block admins) - matching old API: Wo_IsAdmin
        if ($recipientData->admin == '1' || $recipientData->admin == 1) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'Cannot block admin users.'
                ]
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Check if already blocked (matching old API: Wo_IsBlocked)
            $isBlocked = DB::table('Wo_Blocks')
                ->where('blocker', $blockerId)
                ->where('blocked', $recipientId)
                ->exists();

            $blocked = '';

            if (!$isBlocked) {
                // Block user (matching old API: Wo_RegisterBlock)
                DB::table('Wo_Blocks')->insert([
                    'blocker' => $blockerId,
                    'blocked' => $recipientId
                ]);

                // Remove following relationships (both ways) - matching old API behavior
                DB::table('Wo_Followers')
                    ->where(function($query) use ($blockerId, $recipientId) {
                        $query->where('follower_id', $blockerId)
                              ->where('following_id', $recipientId);
                    })
                    ->orWhere(function($query) use ($blockerId, $recipientId) {
                        $query->where('follower_id', $recipientId)
                              ->where('following_id', $blockerId);
                    })
                    ->delete();

                // Remove friend relationship if exists (if Wo_Friends table exists)
                if (Schema::hasTable('Wo_Friends')) {
                    DB::table('Wo_Friends')
                        ->where(function($query) use ($blockerId, $recipientId) {
                            $query->where('from_id', $blockerId)
                                  ->where('to_id', $recipientId);
                        })
                        ->orWhere(function($query) use ($blockerId, $recipientId) {
                            $query->where('from_id', $recipientId)
                                  ->where('to_id', $blockerId);
                        })
                        ->delete();
                }

                $blocked = 'blocked';
            } else {
                // Already blocked
                $blocked = 'already_blocked';
            }

            DB::commit();

            // Return response matching old API format
            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'blocked' => $blocked
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '500',
                    'error_text' => 'Failed to block user: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    public function unblockUser(Request $request, $id): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'No session sent.'
                ]
            ], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'Session id is wrong.'
                ]
            ], 401);
        }

        // $id is the recipient_id (the user to unblock)
        // $tokenUserId is the current user (blocker)
        // Matching old API: Wo_RemoveBlock($recipient_id)
        $recipientId = $id; // The user to unblock
        $blockerId = $tokenUserId; // The current user

        // Check if recipient user exists (matching old API validation)
        $recipientData = DB::table('Wo_Users')
            ->where('user_id', $recipientId)
            ->first();

        if (empty($recipientData)) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'User Profile is not exists.'
                ]
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Check if blocked (matching old API: Wo_IsBlocked)
            $isBlocked = DB::table('Wo_Blocks')
                ->where('blocker', $blockerId)
                ->where('blocked', $recipientId)
                ->exists();

            $blocked = '';

            if ($isBlocked) {
                // Unblock user (matching old API: Wo_RemoveBlock)
                $deleted = DB::table('Wo_Blocks')
                    ->where('blocker', $blockerId)
                    ->where('blocked', $recipientId)
                    ->delete();

                if ($deleted) {
                    $blocked = 'unblocked';
                }
            } else {
                // Not blocked
                $blocked = 'not_blocked';
            }

            DB::commit();

            // Return response matching old API format
            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'blocked' => $blocked
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '500',
                    'error_text' => 'Failed to unblock user: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    public function suggested(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        // Get limit (matching old API structure)
        $limit = (int) ($request->input('limit', $request->query('limit', 12)));
        $limit = max(1, min($limit, 50));

        // Get suggested users (matching old API: Wo_UserSug function)
        $suggestedUsers = $this->getUserSuggestions($tokenUserId, $limit);

        // Format users (matching old API response format)
        $users = [];
        foreach ($suggestedUsers as $user) {
            $users[] = [
                'user_id' => $user->user_id,
                'username' => $user->username ?? 'Unknown',
                'name' => $user->name ?? $user->username ?? 'Unknown User',
                'email' => $user->email ?? '',
                'avatar' => $user->avatar ?? '',
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'cover' => $user->cover ?? '',
                'cover_url' => $user->cover ? asset('storage/' . $user->cover) : null,
                'verified' => (bool) ($user->verified ?? false),
                'lastseen' => $user->lastseen ?? time(),
                'lastseen_time_text' => $this->getTimeElapsedString($user->lastseen ?? time()),
                'is_following' => $this->isFollowing($tokenUserId, $user->user_id),
                'is_following_me' => $this->isFollowing($user->user_id, $tokenUserId),
                'is_friend' => $this->isFriend($tokenUserId, $user->user_id),
            ];
        }

        $responseData = [
            'api_status' => 200,
            'suggestions' => $users
        ];

        // Handle contacts-based suggestions (matching old API)
        if ($request->filled('contacts')) {
            $contacts = $request->input('contacts');
            if (is_string($contacts)) {
                $contacts = json_decode($contacts, true);
            }
            
            if (is_array($contacts) && !empty($contacts)) {
                $contactsUsers = $this->getContactsSuggestions($tokenUserId, $contacts, $limit);
                $contactsFormatted = [];
                foreach ($contactsUsers as $user) {
                    $contactsFormatted[] = [
                        'user_id' => $user->user_id,
                        'username' => $user->username ?? 'Unknown',
                        'name' => $user->name ?? $user->username ?? 'Unknown User',
                        'email' => $user->email ?? '',
                        'avatar' => $user->avatar ?? '',
                        'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                        'cover' => $user->cover ?? '',
                        'cover_url' => $user->cover ? asset('storage/' . $user->cover) : null,
                        'verified' => (bool) ($user->verified ?? false),
                        'lastseen' => $user->lastseen ?? time(),
                        'lastseen_time_text' => $this->getTimeElapsedString($user->lastseen ?? time()),
                        'is_following' => $this->isFollowing($tokenUserId, $user->user_id),
                        'is_following_me' => $this->isFollowing($user->user_id, $tokenUserId),
                        'is_friend' => $this->isFriend($tokenUserId, $user->user_id),
                    ];
                }
                $responseData['contacts_suggestions'] = $contactsFormatted;
            }
        }

        return response()->json($responseData);
    }

    /**
     * Update sidebar users (mimics old API: requests.php?f=update_sidebar_users)
     * This is the same as suggested() but matches the old API endpoint name
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateSidebarUsers(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
        return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        // Get limit (matching old API structure)
        $limit = (int) ($request->input('limit', $request->query('limit', 12)));
        $limit = max(1, min($limit, 50));

        // Get suggested users (matching old API: Wo_UserSug function)
        $suggestedUsers = $this->getUserSuggestions($tokenUserId, $limit);

        // Format users (matching old API response format)
        $users = [];
        foreach ($suggestedUsers as $user) {
            $users[] = [
                'user_id' => $user->user_id,
                'username' => $user->username ?? 'Unknown',
                'name' => $user->name ?? $user->username ?? 'Unknown User',
                'email' => $user->email ?? '',
                'avatar' => $user->avatar ?? '',
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'cover' => $user->cover ?? '',
                'cover_url' => $user->cover ? asset('storage/' . $user->cover) : null,
                'verified' => (bool) ($user->verified ?? false),
                'lastseen' => $user->lastseen ?? time(),
                'lastseen_time_text' => $this->getTimeElapsedString($user->lastseen ?? time()),
                'is_following' => $this->isFollowing($tokenUserId, $user->user_id),
                'is_following_me' => $this->isFollowing($user->user_id, $tokenUserId),
                'is_friend' => $this->isFriend($tokenUserId, $user->user_id),
            ];
        }

        $responseData = [
            'api_status' => 200,
            'suggestions' => $users
        ];

        // Handle contacts-based suggestions (matching old API)
        if ($request->filled('contacts')) {
            $contacts = $request->input('contacts');
            if (is_string($contacts)) {
                $contacts = json_decode($contacts, true);
            }
            
            if (is_array($contacts) && !empty($contacts)) {
                $contactsUsers = $this->getContactsSuggestions($tokenUserId, $contacts, $limit);
                $contactsFormatted = [];
                foreach ($contactsUsers as $user) {
                    $contactsFormatted[] = [
                        'user_id' => $user->user_id,
                        'username' => $user->username ?? 'Unknown',
                        'name' => $user->name ?? $user->username ?? 'Unknown User',
                        'email' => $user->email ?? '',
                        'avatar' => $user->avatar ?? '',
                        'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                        'cover' => $user->cover ?? '',
                        'cover_url' => $user->cover ? asset('storage/' . $user->cover) : null,
                        'verified' => (bool) ($user->verified ?? false),
                        'lastseen' => $user->lastseen ?? time(),
                        'lastseen_time_text' => $this->getTimeElapsedString($user->lastseen ?? time()),
                        'is_following' => $this->isFollowing($tokenUserId, $user->user_id),
                        'is_following_me' => $this->isFollowing($user->user_id, $tokenUserId),
                        'is_friend' => $this->isFriend($tokenUserId, $user->user_id),
                    ];
                }
                $responseData['contacts_suggestions'] = $contactsFormatted;
            }
        }

        return response()->json($responseData);
    }

    /**
     * Get user suggestions (mimics Wo_UserSug function)
     * 
     * @param string $userId
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    private function getUserSuggestions(string $userId, int $limit)
    {
        // Get users that:
        // 1. Are not the current user
        // 2. Are not already friends/following
        // 3. Are not blocked
        // 4. Are active
        // 5. Have mutual friends (if possible) or are recently active

        $friendIds = [$userId];

        // Get user's following/friends (use Wo_Followers if Wo_Friends doesn't exist)
        if (Schema::hasTable('Wo_Friends')) {
            $userFriends = DB::table('Wo_Friends')
                ->where(function($q) use ($userId) {
                    $q->where('from_id', $userId)
                      ->orWhere('to_id', $userId);
                })
                ->get();

            foreach ($userFriends as $friend) {
                if ($friend->from_id != $userId) {
                    $friendIds[] = $friend->from_id;
                }
                if ($friend->to_id != $userId) {
                    $friendIds[] = $friend->to_id;
                }
            }
        } elseif (Schema::hasTable('Wo_Followers')) {
            // Use Wo_Followers table (following relationships)
            $following = DB::table('Wo_Followers')
                ->where('follower_id', $userId)
                ->where('active', 1)
                ->pluck('following_id')
                ->toArray();
            
            $followers = DB::table('Wo_Followers')
                ->where('following_id', $userId)
                ->where('active', 1)
                ->pluck('follower_id')
                ->toArray();
            
            $friendIds = array_merge($friendIds, $following, $followers);
        }

        $friendIds = array_unique($friendIds);

        // Get blocked users
        $blockedIds = [];
        if (Schema::hasTable('Wo_Blocks')) {
            $blockedUsers = DB::table('Wo_Blocks')
                ->where(function($q) use ($userId) {
                    $q->where('blocker', $userId)
                      ->orWhere('blocked', $userId);
                })
                ->get();

            foreach ($blockedUsers as $block) {
                if ($block->blocker != $userId) {
                    $blockedIds[] = $block->blocker;
                }
                if ($block->blocked != $userId) {
                    $blockedIds[] = $block->blocked;
                }
            }
        }
        $blockedIds = array_unique($blockedIds);

        // Exclude friends and blocked users
        $excludeIds = array_unique(array_merge($friendIds, $blockedIds));

        // Get suggested users (prioritize users with mutual friends or recent activity)
        $suggested = DB::table('Wo_Users')
            ->where('active', '1')
            ->where('user_id', '!=', $userId);

        if (!empty($excludeIds)) {
            $suggested->whereNotIn('user_id', $excludeIds);
        }

        $suggested = $suggested->orderByDesc('lastseen')
            ->orderByDesc('user_id')
            ->limit($limit)
            ->get();

        return $suggested;
    }

    /**
     * Get contacts-based suggestions (mimics Wo_UserContactsAPP function)
     * 
     * @param string $userId
     * @param array $contacts
     * @param int $limit
     * @return array
     */
    private function getContactsSuggestions(string $userId, array $contacts, int $limit): array
    {
        if (empty($contacts)) {
            return [];
        }

        $suggestedUsers = [];
        
        foreach ($contacts as $contact) {
            if (!isset($contact['Value']) || empty($contact['Value'])) {
                continue;
            }

            $phoneNumber = $contact['Value'];
            // Clean phone number
            $phoneNumber = str_replace([' ', '+', '-'], '', $phoneNumber);

            // Find users with matching phone number
            $userQuery = DB::table('Wo_Users')
                ->where('active', '1')
                ->where('phone_number', 'like', '%' . $phoneNumber . '%')
                ->where('user_id', '!=', $userId);

            // Exclude blocked users if table exists
            if (Schema::hasTable('Wo_Blocks')) {
                $userQuery->whereNotIn('user_id', function($query) use ($userId) {
                    $query->select('blocked')
                        ->from('Wo_Blocks')
                        ->where('blocker', $userId);
                })
                ->whereNotIn('user_id', function($query) use ($userId) {
                    $query->select('blocker')
                        ->from('Wo_Blocks')
                        ->where('blocked', $userId);
                });
            }

            // Exclude already following if table exists
            if (Schema::hasTable('Wo_Followers')) {
                $userQuery->whereNotIn('user_id', function($query) use ($userId) {
                    $query->select('following_id')
                        ->from('Wo_Followers')
                        ->where('follower_id', $userId);
                });
            }

            $user = $userQuery->first();

            if ($user && !in_array($user->user_id, array_column($suggestedUsers, 'user_id'))) {
                $suggestedUsers[] = $user;
                if (count($suggestedUsers) >= $limit) {
                    break;
                }
            }
        }

        return $suggestedUsers;
    }

    /**
     * Check if user is following another user
     * 
     * @param string $followerId
     * @param string $followingId
     * @return bool
     */
    private function isFollowing(string $followerId, string $followingId): bool
    {
        return DB::table('Wo_Followers')
            ->where('follower_id', $followerId)
            ->where('following_id', $followingId)
            ->exists();
    }

    /**
     * Check if two users are friends
     * 
     * @param string $userId1
     * @param string $userId2
     * @return bool
     */
    private function isFriend(string $userId1, string $userId2): bool
    {
        // Check Wo_Friends table first
        if (Schema::hasTable('Wo_Friends')) {
            return DB::table('Wo_Friends')
                ->where(function($q) use ($userId1, $userId2) {
                    $q->where('from_id', $userId1)
                      ->where('to_id', $userId2);
                })
                ->orWhere(function($q) use ($userId1, $userId2) {
                    $q->where('from_id', $userId2)
                      ->where('to_id', $userId1);
                })
                ->exists();
        }
        
        // Fallback to Wo_Followers (mutual following = friends)
        if (Schema::hasTable('Wo_Followers')) {
            $user1FollowingUser2 = DB::table('Wo_Followers')
                ->where('follower_id', $userId1)
                ->where('following_id', $userId2)
                ->where('active', 1)
                ->exists();
            
            $user2FollowingUser1 = DB::table('Wo_Followers')
                ->where('follower_id', $userId2)
                ->where('following_id', $userId1)
                ->where('active', 1)
                ->exists();
            
            // Both following each other = friends
            return $user1FollowingUser2 && $user2FollowingUser1;
        }
        
        return false;
    }

    /**
     * Get time elapsed string (mimics Wo_Time_Elapsed_String function)
     * 
     * @param int $timestamp
     * @return string
     */
    private function getTimeElapsedString(int $timestamp): string
    {
        $time = time() - $timestamp;
        
        if ($time < 60) return 'Just now';
        if ($time < 3600) return floor($time / 60) . ' minutes ago';
        if ($time < 86400) return floor($time / 3600) . ' hours ago';
        if ($time < 2592000) return floor($time / 86400) . ' days ago';
        if ($time < 31536000) return floor($time / 2592000) . ' months ago';
        return floor($time / 31536000) . ' years ago';
    }

    /**
     * Update follow counts for both users
     * 
     * @param string $followerId
     * @param string $followingId
     * @return void
     */
    private function updateFollowCounts(string $followerId, string $followingId): void
    {
        // Update following count for follower
        $followingCount = DB::table('Wo_Followers')
            ->where('follower_id', $followerId)
            ->where('active', '1')
            ->count();

        // Update followers count for following user
        $followersCount = DB::table('Wo_Followers')
            ->where('following_id', $followingId)
            ->where('active', '1')
            ->count();

        // Update user records (if these columns exist)
        // Note: These columns might not exist in Wo_Users table
        try {
            DB::table('Wo_Users')->where('user_id', $followerId)->update(['following' => $followingCount]);
            DB::table('Wo_Users')->where('user_id', $followingId)->update(['followers' => $followersCount]);
        } catch (\Exception $e) {
            // Columns don't exist, skip update
            // Silently fail if columns don't exist
        }
    }

    /**
     * Send follow notification
     * 
     * @param string $followerId
     * @param string $followingId
     * @param bool $requiresApproval
     * @return void
     */
    private function sendFollowNotification(string $followerId, string $followingId, bool $requiresApproval): void
    {
        try {
            // Get follower user data
            $follower = DB::table('Wo_Users')->where('user_id', $followerId)->first();
            if (!$follower) {
                return;
            }

            // Check if notifications table exists
            if (!Schema::hasTable('Wo_Notifications')) {
                return;
            }

            // Determine notification type
            $notificationType = $requiresApproval ? 'follow_request' : 'following';

            // Check if notification already exists to avoid duplicates
            $existingNotification = DB::table('Wo_Notifications')
                ->where('notifier_id', $followerId)
                ->where('recipient_id', $followingId)
                ->where('type', $notificationType)
                ->where('seen', 0)
                ->first();

            if (!$existingNotification) {
                // Create notification record
                $notificationData = [
                    'notifier_id' => $followerId,
                    'recipient_id' => $followingId,
                    'type' => $notificationType,
                    'text' => '',
                    'url' => 'index.php?link1=timeline&u=' . ($follower->username ?? ''),
                    'seen' => 0,
                ];

                // Only add time column if it exists
                if (Schema::hasColumn('Wo_Notifications', 'time')) {
                    $notificationData['time'] = time();
                }

                DB::table('Wo_Notifications')->insert($notificationData);
            }
        } catch (\Exception $e) {
            // Silently fail if notification creation fails
        }
    }
}
