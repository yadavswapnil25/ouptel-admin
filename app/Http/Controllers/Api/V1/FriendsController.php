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

        // Note: Wo_Friends table doesn't exist, so return empty results
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([]), // Empty collection
            0, // Total count
            $perPage, // Per page
            1, // Current page
            ['path' => $request->url()]
        );

        $data = [];

        return response()->json([
            'ok' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
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

        // Note: Wo_Users table might not exist, so return empty results
        $users = [];

        return response()->json([
            'data' => [
                'users' => $users,
                'total' => count($users),
            ],
        ]);
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

        // Note: Wo_FriendRequests table doesn't exist, so return empty results
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([]), // Empty collection
            0, // Total count
            $perPage, // Per page
            1, // Current page
            ['path' => $request->url()]
        );

        $data = [];

        return response()->json([
            'ok' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function sendRequest(Request $request): JsonResponse
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

        $validated = $request->validate([
            'user_id' => ['required', 'string'],
        ]);

        // Note: Wo_Friends and Wo_FriendRequests tables don't exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Friend requests feature is currently unavailable. Please try again later.'
        ], 503);
    }

    public function acceptRequest(Request $request, $id): JsonResponse
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

        // Note: Wo_FriendRequests and Wo_Friends tables might not exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Friend requests feature is currently unavailable. Please try again later.'
        ], 503);
    }

    public function declineRequest(Request $request, $id): JsonResponse
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

        // Note: Wo_FriendRequests table might not exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Friend requests feature is currently unavailable. Please try again later.'
        ], 503);
    }

    public function removeFriend(Request $request, $id): JsonResponse
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

        // Note: Wo_Friends table might not exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Friends feature is currently unavailable. Please try again later.'
        ], 503);
    }

    public function blockUser(Request $request, $id): JsonResponse
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

        // Note: Wo_Friends table might not exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Blocking feature is currently unavailable. Please try again later.'
        ], 503);
    }

    public function unblockUser(Request $request, $id): JsonResponse
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

        // Note: Wo_Friends table might not exist, so return error
        return response()->json([
            'ok' => false,
            'message' => 'Unblocking feature is currently unavailable. Please try again later.'
        ], 503);
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
}
