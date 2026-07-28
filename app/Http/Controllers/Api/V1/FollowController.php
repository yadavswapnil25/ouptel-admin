<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class FollowController extends Controller
{
    /**
     * Follow a user (mimics WoWonder requests.php?f=follow_user&following_id={userId})
     * 
     * @param Request $request
     * @param int $followingId
     * @return JsonResponse
     */
    public function followUser(Request $request, int $followingId): JsonResponse
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
            'hash' => 'nullable|string', // For compatibility with WoWonder hash system
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user is trying to follow themselves
        if ($tokenUserId == $followingId) {
            return response()->json(['ok' => false, 'message' => 'Cannot follow yourself'], 400);
        }

        // Check if target user exists
        $targetUser = User::where('user_id', $followingId)->first();
        if (!$targetUser) {
            return response()->json(['ok' => false, 'message' => 'User not found'], 404);
        }

        // Check if target user is active
        if ($targetUser->active === '0' || $targetUser->active === '2') {
            return response()->json(['ok' => false, 'message' => 'Cannot follow this user'], 403);
        }

        try {
            DB::beginTransaction();

            // Check if already following
            $existingFollow = DB::table('Wo_Followers')
                ->where('follower_id', $tokenUserId)
                ->where('following_id', $followingId)
                ->where('active', '1')
                ->first();

            if ($existingFollow) {
                return response()->json(['ok' => false, 'message' => 'Already following this user'], 409);
            }

            // Check if there's a pending follow request
            $pendingFollow = DB::table('Wo_Followers')
                ->where('follower_id', $tokenUserId)
                ->where('following_id', $followingId)
                ->where('active', '0')
                ->first();

            if ($pendingFollow) {
                return response()->json(['ok' => false, 'message' => 'Follow request already pending'], 409);
            }

            // Check target user's follow privacy settings
            $requiresApproval = $this->requiresFollowApproval($targetUser, $tokenUserId);

            // Create follow relationship
            $followData = [
                'follower_id' => $tokenUserId,
                'following_id' => $followingId,
                'active' => $requiresApproval ? '0' : '1', // 0 = pending, 1 = accepted
                'time' => time(),
            ];

            DB::table('Wo_Followers')->insert($followData);

            // Update follow counts
            $this->updateFollowCounts($tokenUserId, $followingId);

            // Send notification
            $this->sendFollowNotification($tokenUserId, $followingId, $requiresApproval);

            DB::commit();

            $displayName = trim(($targetUser->first_name ?? '') . ' ' . ($targetUser->last_name ?? ''));
            if ($displayName === '') {
                $displayName = $targetUser->full_name ?: ($targetUser->username ?: 'User');
            }

            $message = $requiresApproval
                ? "Follow request sent to {$displayName}"
                : "{$displayName} followed successfully";
            $status = $requiresApproval ? 'pending' : 'following';

            return response()->json([
                'ok' => true,
                'message' => $message,
                'data' => [
                    'follower_id' => $tokenUserId,
                    'following_id' => $followingId,
                    'status' => $status,
                    'requires_approval' => $requiresApproval,
                    'followed_at' => date('c'),
                    'display_name' => $displayName,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Follow user failed: ' . $e->getMessage(), [
                'follower_id' => $tokenUserId,
                'following_id' => $followingId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Failed to follow user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unfollow a user
     * 
     * @param Request $request
     * @param int $followingId
     * @return JsonResponse
     */
    public function unfollowUser(Request $request, int $followingId): JsonResponse
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

        try {
            DB::beginTransaction();

            // Check if following relationship exists
            $follow = DB::table('Wo_Followers')
                ->where('follower_id', $tokenUserId)
                ->where('following_id', $followingId)
                ->first();

            if (!$follow) {
                return response()->json(['ok' => false, 'message' => 'Not following this user'], 404);
            }

            // Remove follow relationship
            DB::table('Wo_Followers')
                ->where('follower_id', $tokenUserId)
                ->where('following_id', $followingId)
                ->delete();

            // Update follow counts
            $this->updateFollowCounts($tokenUserId, $followingId);

            DB::commit();

            $targetUser = User::where('user_id', $followingId)->first();
            $displayName = '';
            if ($targetUser) {
                $displayName = trim(($targetUser->first_name ?? '') . ' ' . ($targetUser->last_name ?? ''));
                if ($displayName === '') {
                    $displayName = $targetUser->full_name ?: ($targetUser->username ?: 'User');
                }
            }
            if ($displayName === '') {
                $displayName = 'User';
            }

            return response()->json([
                'ok' => true,
                'message' => "{$displayName} unfollowed successfully",
                'data' => [
                    'follower_id' => $tokenUserId,
                    'following_id' => $followingId,
                    'status' => 'unfollowed',
                    'unfollowed_at' => date('c'),
                    'display_name' => $displayName,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'ok' => false,
                'message' => 'Failed to unfollow user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's followers
     * 
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     */
    public function getFollowers(Request $request, int $userId): JsonResponse
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

        // Check if user exists
        $user = User::where('user_id', $userId)->first();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'User not found'], 404);
        }

        try {
            $perPage = $request->input('per_page', 20);
            $perPage = max(1, min($perPage, 100));

            // Get followers with user details
            $followers = DB::table('Wo_Followers as f')
                ->join('Wo_Users as u', 'f.follower_id', '=', 'u.user_id')
                ->where('f.following_id', $userId)
                ->where('f.active', '1')
                ->where('u.active', '1')
                ->select('u.user_id', 'u.username', 'u.first_name', 'u.last_name', 'u.avatar', 'u.verified', 'f.time as followed_at')
                ->orderBy('f.time', 'desc')
                ->paginate($perPage);

            $followerIds = $followers->getCollection()->pluck('user_id')->map(fn ($id) => (string) $id)->all();
            $iFollowSet = [];
            $iPendingSet = [];
            $followsMeSet = [];
            $viewingOwnList = (string) $userId === (string) $tokenUserId;

            if (!empty($followerIds)) {
                $iFollowIds = DB::table('Wo_Followers')
                    ->where('follower_id', $tokenUserId)
                    ->whereIn('following_id', $followerIds)
                    ->where('active', '1')
                    ->pluck('following_id')
                    ->map(fn ($id) => (string) $id)
                    ->all();
                $iFollowSet = array_fill_keys($iFollowIds, true);

                $iPendingIds = DB::table('Wo_Followers')
                    ->where('follower_id', $tokenUserId)
                    ->whereIn('following_id', $followerIds)
                    ->where('active', '0')
                    ->pluck('following_id')
                    ->map(fn ($id) => (string) $id)
                    ->all();
                $iPendingSet = array_fill_keys($iPendingIds, true);

                if ($viewingOwnList) {
                    // Everyone on your followers list follows you by definition.
                    $followsMeSet = array_fill_keys($followerIds, true);
                } else {
                    $followsMeIds = DB::table('Wo_Followers')
                        ->where('following_id', $tokenUserId)
                        ->whereIn('follower_id', $followerIds)
                        ->where('active', '1')
                        ->pluck('follower_id')
                        ->map(fn ($id) => (string) $id)
                        ->all();
                    $followsMeSet = array_fill_keys($followsMeIds, true);
                }
            }

            $formattedFollowers = $followers->map(function ($follower) use ($tokenUserId, $iFollowSet, $iPendingSet, $followsMeSet) {
                $fullName = trim(($follower->first_name ?? '') . ' ' . ($follower->last_name ?? '')) ?: $follower->username;
                $fid = (string) $follower->user_id;
                $isMe = $fid === (string) $tokenUserId;
                $isFollowing = !$isMe && isset($iFollowSet[$fid]);
                $isPending = !$isMe && isset($iPendingSet[$fid]);
                $isFollowingMe = !$isMe && isset($followsMeSet[$fid]);

                return [
                    'user_id' => $follower->user_id,
                    'username' => $follower->username,
                    'name' => $fullName,
                    'first_name' => $follower->first_name ?? '',
                    'last_name' => $follower->last_name ?? '',
                    'avatar_url' => $follower->avatar ? asset('storage/' . $follower->avatar) : null,
                    'verified' => $follower->verified === '1',
                    'followed_at' => date('c', $follower->followed_at),
                    // 0 = not following, 1 = following, 2 = pending (auth → this user)
                    'is_following' => $isFollowing ? 1 : ($isPending ? 2 : 0),
                    'is_following_me' => $isFollowingMe,
                    'mutual_follow' => $isFollowing && $isFollowingMe,
                    'can_follow_back' => $isFollowingMe && !$isFollowing && !$isPending && !$isMe,
                    'is_me' => $isMe,
                ];
            });

            return response()->json([
                'ok' => true,
                'data' => [
                    'followers' => $formattedFollowers,
                    'pagination' => [
                        'current_page' => $followers->currentPage(),
                        'last_page' => $followers->lastPage(),
                        'per_page' => $followers->perPage(),
                        'total' => $followers->total(),
                        'has_more' => $followers->hasMorePages(),
                    ],
                    'user_id' => $userId,
                    'total_followers' => $followers->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to get followers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get users that a user is following
     * 
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     */
    public function getFollowing(Request $request, int $userId): JsonResponse
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

        // Check if user exists
        $user = User::where('user_id', $userId)->first();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'User not found'], 404);
        }

        try {
            $perPage = $request->input('per_page', 20);
            $perPage = max(1, min($perPage, 100));

            // Get following with user details
            $following = DB::table('Wo_Followers as f')
                ->join('Wo_Users as u', 'f.following_id', '=', 'u.user_id')
                ->where('f.follower_id', $userId)
                ->where('f.active', '1')
                ->where('u.active', '1')
                ->select('u.user_id', 'u.username', 'u.first_name', 'u.last_name', 'u.avatar', 'u.verified', 'f.time as followed_at')
                ->orderBy('f.time', 'desc')
                ->paginate($perPage);

            $formattedFollowing = $following->map(function ($followed) use ($tokenUserId) {
                $fullName = trim(($followed->first_name ?? '') . ' ' . ($followed->last_name ?? '')) ?: $followed->username;
                $fid = (string) $followed->user_id;
                $isMe = $fid === (string) $tokenUserId;
                $isFollowing = !$isMe && $this->isFollowing((string) $tokenUserId, $fid);
                $isPending = !$isMe && $this->isFollowPending((string) $tokenUserId, $fid);
                $isFollowingMe = !$isMe && $this->isFollowing($fid, (string) $tokenUserId);

                return [
                    'user_id' => $followed->user_id,
                    'username' => $followed->username,
                    'name' => $fullName,
                    'first_name' => $followed->first_name ?? '',
                    'last_name' => $followed->last_name ?? '',
                    'avatar_url' => $followed->avatar ? asset('storage/' . $followed->avatar) : null,
                    'verified' => $followed->verified === '1',
                    'followed_at' => date('c', $followed->followed_at),
                    'is_following' => $isFollowing ? 1 : ($isPending ? 2 : 0),
                    'is_following_me' => $isFollowingMe,
                    'mutual_follow' => $isFollowing && $isFollowingMe,
                    'can_follow_back' => $isFollowingMe && !$isFollowing && !$isPending && !$isMe,
                    'is_me' => $isMe,
                ];
            });

            return response()->json([
                'ok' => true,
                'data' => [
                    'following' => $formattedFollowing,
                    'pagination' => [
                        'current_page' => $following->currentPage(),
                        'last_page' => $following->lastPage(),
                        'per_page' => $following->perPage(),
                        'total' => $following->total(),
                        'has_more' => $following->hasMorePages(),
                    ],
                    'user_id' => $userId,
                    'total_following' => $following->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to get following',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user is following another user
     * 
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     */
    public function checkFollowStatus(Request $request, int $userId): JsonResponse
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

        try {
            // Check if following
            $isFollowing = $this->isFollowing($tokenUserId, $userId);
            $isFollowingMe = $this->isFollowing($userId, $tokenUserId);
            $isPending = $this->isFollowPending($tokenUserId, $userId);

            return response()->json([
                'ok' => true,
                'data' => [
                    'user_id' => $userId,
                    'is_following' => $isFollowing,
                    'is_following_me' => $isFollowingMe,
                    'is_pending' => $isPending,
                    'mutual_follow' => $isFollowing && $isFollowingMe,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to check follow status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get follow requests (for users who require approval)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getFollowRequests(Request $request): JsonResponse
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

        try {
            $perPage = $request->input('per_page', 20);
            $perPage = max(1, min($perPage, 100));

            // Get pending follow requests
            $requests = DB::table('Wo_Followers as f')
                ->join('Wo_Users as u', 'f.follower_id', '=', 'u.user_id')
                ->where('f.following_id', $tokenUserId)
                ->where('f.active', '0')
                ->where('u.active', '1')
                ->select('u.user_id', 'u.username', 'u.first_name', 'u.last_name', 'u.avatar', 'u.verified', 'f.time as requested_at')
                ->orderBy('f.time', 'desc')
                ->paginate($perPage);

            $formattedRequests = $requests->map(function ($request) {
                $fullName = trim(($request->first_name ?? '') . ' ' . ($request->last_name ?? '')) ?: $request->username;
                return [
                    'user_id' => $request->user_id,
                    'username' => $request->username,
                    'name' => $fullName,
                    'avatar_url' => $request->avatar ? asset('storage/' . $request->avatar) : null,
                    'verified' => $request->verified === '1',
                    'requested_at' => date('c', $request->requested_at),
                ];
            });

            return response()->json([
                'ok' => true,
                'data' => [
                    'requests' => $formattedRequests,
                    'pagination' => [
                        'current_page' => $requests->currentPage(),
                        'last_page' => $requests->lastPage(),
                        'per_page' => $requests->perPage(),
                        'total' => $requests->total(),
                        'has_more' => $requests->hasMorePages(),
                    ],
                    'total_requests' => $requests->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to get follow requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept follow request
     * 
     * @param Request $request
     * @param int $followerId
     * @return JsonResponse
     */
    public function acceptFollowRequest(Request $request, int $followerId): JsonResponse
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

        try {
            DB::beginTransaction();

            // Find pending follow request
            $followRequest = DB::table('Wo_Followers')
                ->where('follower_id', $followerId)
                ->where('following_id', $tokenUserId)
                ->where('active', '0')
                ->first();

            if (!$followRequest) {
                return response()->json(['ok' => false, 'message' => 'Follow request not found'], 404);
            }

            // Accept the request
            DB::table('Wo_Followers')
                ->where('follower_id', $followerId)
                ->where('following_id', $tokenUserId)
                ->update(['active' => '1']);

            // Create reverse follow so mutual-follow isFriend() check works
            $reverseExists = DB::table('Wo_Followers')
                ->where('follower_id', $tokenUserId)
                ->where('following_id', $followerId)
                ->exists();
            if ($reverseExists) {
                DB::table('Wo_Followers')
                    ->where('follower_id', $tokenUserId)
                    ->where('following_id', $followerId)
                    ->update(['active' => '1']);
            } else {
                DB::table('Wo_Followers')->insert([
                    'follower_id' => $tokenUserId,
                    'following_id' => $followerId,
                    'active' => '1',
                    'time' => time(),
                ]);
            }

            // Upsert Wo_Friends so profile isFriend() check works
            try {
                if (Schema::hasTable('Wo_Friends')
                    && Schema::hasColumn('Wo_Friends', 'user_id')
                    && Schema::hasColumn('Wo_Friends', 'friend_id')) {
                    $fRow = DB::table('Wo_Friends')
                        ->where(function ($q) use ($followerId, $tokenUserId) {
                            $q->where('user_id', $followerId)->where('friend_id', $tokenUserId);
                        })
                        ->orWhere(function ($q) use ($followerId, $tokenUserId) {
                            $q->where('user_id', $tokenUserId)->where('friend_id', $followerId);
                        })
                        ->first();
                    if ($fRow) {
                        DB::table('Wo_Friends')->where('id', $fRow->id)->update(['status' => '2']);
                    } else {
                        $ins = ['user_id' => $followerId, 'friend_id' => $tokenUserId, 'status' => '2'];
                        if (Schema::hasColumn('Wo_Friends', 'time')) {
                            $ins['time'] = (string) time();
                        }
                        DB::table('Wo_Friends')->insert($ins);
                    }
                }
            } catch (\Exception $e) {
                // best-effort
            }

            // Update follow counts
            $this->updateFollowCounts($followerId, $tokenUserId);

            // Send notification
            $this->sendFollowAcceptedNotification($followerId, $tokenUserId);

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Follow request accepted successfully',
                'data' => [
                    'follower_id' => $followerId,
                    'following_id' => $tokenUserId,
                    'status' => 'accepted',
                    'accepted_at' => date('c'),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'ok' => false,
                'message' => 'Failed to accept follow request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject follow request
     * 
     * @param Request $request
     * @param int $followerId
     * @return JsonResponse
     */
    public function rejectFollowRequest(Request $request, int $followerId): JsonResponse
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

        try {
            DB::beginTransaction();

            // Find pending follow request
            $followRequest = DB::table('Wo_Followers')
                ->where('follower_id', $followerId)
                ->where('following_id', $tokenUserId)
                ->where('active', '0')
                ->first();

            if (!$followRequest) {
                return response()->json(['ok' => false, 'message' => 'Follow request not found'], 404);
            }

            // Reject the request (delete it)
            DB::table('Wo_Followers')
                ->where('follower_id', $followerId)
                ->where('following_id', $tokenUserId)
                ->delete();

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Follow request rejected successfully',
                'data' => [
                    'follower_id' => $followerId,
                    'following_id' => $tokenUserId,
                    'status' => 'rejected',
                    'rejected_at' => date('c'),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'ok' => false,
                'message' => 'Failed to reject follow request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user requires follow approval
     * 
     * @param User $user
     * @param string $followerId
     * @return bool
     */
    private function requiresFollowApproval(User $user, string $followerId): bool
    {
        // Check if user has confirm_followers enabled
        if ($user->confirm_followers === '1') {
            return true;
        }

        // Check if follower privacy is restricted
        if ($user->follow_privacy === '2') { // Only friends can follow
            return !$this->areFriends($user->user_id, $followerId);
        }

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
        // Check if both users follow each other
        $user1FollowsUser2 = DB::table('Wo_Followers')
            ->where('follower_id', $userId1)
            ->where('following_id', $userId2)
            ->where('active', '1')
            ->exists();

        $user2FollowsUser1 = DB::table('Wo_Followers')
            ->where('follower_id', $userId2)
            ->where('following_id', $userId1)
            ->where('active', '1')
            ->exists();

        return $user1FollowsUser2 && $user2FollowsUser1;
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
            ->where('active', '1')
            ->exists();
    }

    /**
     * Check if follow request is pending
     * 
     * @param string $followerId
     * @param string $followingId
     * @return bool
     */
    private function isFollowPending(string $followerId, string $followingId): bool
    {
        return DB::table('Wo_Followers')
            ->where('follower_id', $followerId)
            ->where('following_id', $followingId)
            ->where('active', '0')
            ->exists();
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
            Log::info("Follow count columns don't exist in Wo_Users table");
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
            if (!\Illuminate\Support\Facades\Schema::hasTable('Wo_Notifications')) {
                return;
            }

            // Determine notification type and text
            $notificationType = $requiresApproval ? 'follow_request' : 'following';
            $notificationText = $requiresApproval 
                ? 'sent you a follow request' 
                : 'started following you';

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
                    'time' => time(),
                    'seen' => 0,
                ];

                // Only add time column if it exists
                if (\Illuminate\Support\Facades\Schema::hasColumn('Wo_Notifications', 'time')) {
                    $notificationData['time'] = time();
                }

                DB::table('Wo_Notifications')->insert($notificationData);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the follow operation
            Log::error("Failed to send follow notification", [
                'follower_id' => $followerId,
                'following_id' => $followingId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send follow accepted notification
     * 
     * @param string $followerId
     * @param string $followingId
     * @return void
     */
    private function sendFollowAcceptedNotification(string $followerId, string $followingId): void
    {
        try {
            // Get following user data (the one who accepted)
            $followingUser = DB::table('Wo_Users')->where('user_id', $followingId)->first();
            if (!$followingUser) {
                return;
            }

            // Check if notifications table exists
            if (!\Illuminate\Support\Facades\Schema::hasTable('Wo_Notifications')) {
                return;
            }

            // Check if notification already exists to avoid duplicates
            $existingNotification = DB::table('Wo_Notifications')
                ->where('notifier_id', $followingId)
                ->where('recipient_id', $followerId)
                ->where('type', 'accepted_request')
                ->where('seen', 0)
                ->first();

            if (!$existingNotification) {
                // Create notification record for accepted request
                $notificationData = [
                    'notifier_id' => $followingId,
                    'recipient_id' => $followerId,
                    'type' => 'accepted_request',
                    'text' => '',
                    'url' => 'index.php?link1=timeline&u=' . ($followingUser->username ?? ''),
                    'time' => time(),
                    'seen' => 0,
                ];

                // Only add time column if it exists
                if (\Illuminate\Support\Facades\Schema::hasColumn('Wo_Notifications', 'time')) {
                    $notificationData['time'] = time();
                }

                DB::table('Wo_Notifications')->insert($notificationData);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the accept operation
            Log::error("Failed to send follow accepted notification", [
                'follower_id' => $followerId,
                'following_id' => $followingId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
