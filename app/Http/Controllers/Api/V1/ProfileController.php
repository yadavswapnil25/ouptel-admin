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

class ProfileController extends Controller
{
    /**
     * Non-allowed user data fields (sensitive information)
     */
    private $nonAllowed = [
        'password',
        'email_code',
        'sms_code',
        'src',
        'ip_address',
        'email_code',
        'sms_code',
        'password_reset_code',
        'social_login',
        'wallet',
        'balance',
        'ref_user_id',
        'referrer',
        'admin',
        'verified',
        'lastseen',
        'showlastseen',
        'androidM_device_id',
        'iosM_device_id',
        'androidN_device_id',
        'iosN_device_id',
        'web_device_id',
        'start_up',
        'start_up_info',
        'startup_follow',
        'startup_image',
        'last_follow_id',
        'last_login_data',
        'two_factor',
        'two_factor_verified',
        'two_factor_method',
        'social_login',
        'new_email',
        'new_phone',
        'info_file',
        'city',
        'state',
        'zip',
        'school_completed',
        'avatar_org',
        'cover_org',
        'cover_full',
        'avatar_full',
        'is_pro',
        'pro_time',
        'pro_type',
        'joined',
        'css_file',
        'timezone',
        'referrer',
        'src',
        'track',
        'curr_time',
        'time',
    ];

    /**
     * Get user profile data (mimics WoWonder get_user_data.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserData(Request $request): JsonResponse
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

        // Validate parameters
        $validator = Validator::make($request->all(), [
            'user_profile_id' => 'nullable|integer',
            'fetch' => 'nullable|string', // Comma-separated: user_data,followers,following,liked_pages,joined_groups,family
            'send_notify' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => $validator->errors()->all()
            ], 422);
        }

        try {
            // Get profile user ID (default to logged-in user)
            $profileUserId = $request->input('user_profile_id', $tokenUserId);
            
            // Get user data
            $user = User::where('user_id', $profileUserId)->first();
            if (!$user) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '6',
                        'error_text' => 'User profile is not exists.'
                    ]
                ], 404);
            }

            // Parse fetch parameters
            $fetch = $request->input('fetch', 'user_data');
            $fetchItems = array_map('trim', explode(',', $fetch));
            $fetchData = array_flip($fetchItems);

            $responseData = [
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0'
            ];

            // Send profile visit notification if requested
            if ($request->input('send_notify') == 1 && $profileUserId != $tokenUserId) {
                $this->sendProfileVisitNotification($tokenUserId, $profileUserId);
            }

            // Fetch user_data
            if (isset($fetchData['user_data'])) {
                $userData = $this->formatUserData($user, $tokenUserId);
                $responseData['user_data'] = $userData;
            }

            // Fetch followers
            if (isset($fetchData['followers'])) {
                $responseData['followers'] = $this->getFollowers($profileUserId, $tokenUserId);
            }

            // Fetch following
            if (isset($fetchData['following'])) {
                $responseData['following'] = $this->getFollowing($profileUserId, $tokenUserId);
            }

            // Fetch liked pages
            if (isset($fetchData['liked_pages'])) {
                $responseData['liked_pages'] = $this->getLikedPages($profileUserId, $tokenUserId);
            }

            // Fetch joined groups
            if (isset($fetchData['joined_groups'])) {
                $responseData['joined_groups'] = $this->getJoinedGroups($profileUserId, $tokenUserId);
            }

            // Fetch family members
            if (isset($fetchData['family'])) {
                $responseData['family'] = $this->getFamilyMembers($profileUserId);
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to get user data: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Format user data with additional metadata
     */
    private function formatUserData(User $user, int $loggedUserId): array
    {
        $userData = $user->toArray();

        // Remove sensitive fields
        foreach ($this->nonAllowed as $field) {
            unset($userData[$field]);
        }

        // Add following/follower status
        $userData['is_following'] = 0;
        $userData['can_follow'] = 0;
        $userData['is_following_me'] = 0;

        // Check if logged user is following this profile
        $isFollowing = DB::table('Wo_Followers')
            ->where('following_id', $user->user_id)
            ->where('follower_id', $loggedUserId)
            ->exists();

        if ($isFollowing) {
            $userData['is_following'] = 1;
            $userData['can_follow'] = 1;
        } else {
            // Check if follow request is pending (table may not exist)
            $isPending = false;
            try {
                $isPending = DB::table('Wo_FollowRequests')
                    ->where('recipient_id', $user->user_id)
                    ->where('user_id', $loggedUserId)
                    ->exists();
            } catch (\Exception $e) {
                // Table doesn't exist, assume no pending requests
                $isPending = false;
            }

            if ($isPending) {
                $userData['is_following'] = 2; // Pending request
                $userData['can_follow'] = 1;
            } else {
                // Check follow privacy
                if ($user->follow_privacy == 1) {
                    // Only followers can send follow request
                    $isFollower = DB::table('Wo_Followers')
                        ->where('following_id', $loggedUserId)
                        ->where('follower_id', $user->user_id)
                        ->exists();
                    if ($isFollower) {
                        $userData['can_follow'] = 1;
                    }
                } else if ($user->follow_privacy == 0) {
                    $userData['can_follow'] = 1;
                }
            }
        }

        // Check if this user is following the logged user
        $isFollowingMe = DB::table('Wo_Followers')
            ->where('following_id', $loggedUserId)
            ->where('follower_id', $user->user_id)
            ->exists();
        $userData['is_following_me'] = $isFollowingMe ? 1 : 0;

        // Add counts
        $userData['post_count'] = DB::table('Wo_Posts')
            ->where('user_id', $user->user_id)
            ->where('active', 1)
            ->count();

        $userData['following_number'] = DB::table('Wo_Followers')
            ->where('follower_id', $user->user_id)
            ->count();

        $userData['followers_number'] = DB::table('Wo_Followers')
            ->where('following_id', $user->user_id)
            ->count();

        // Add formatted fields
        $userData['gender_text'] = $user->gender == 'male' ? 'Male' : 'Female';
        $userData['lastseen_time_text'] = $this->timeElapsedString($user->lastseen ?? time());
        
        // Check if blocked
        $userData['is_blocked'] = $this->isBlocked($user->user_id, $loggedUserId);

        // Check if users are friends
        $userData['is_friend'] = $this->isFriend($user->user_id, $loggedUserId) ? 1 : 0;

        // Add profile and cover URLs
        $userData['avatar_url'] = $user->avatar ? asset('storage/' . $user->avatar) : asset('images/default-avatar.png');
        $userData['cover_url'] = $user->cover ? asset('storage/' . $user->cover) : asset('images/default-cover.jpg');

        return $userData;
    }

    /**
     * Get user followers
     */
    private function getFollowers(int $userId, int $loggedUserId, int $limit = 50): array
    {
        $followers = DB::table('Wo_Followers')
            ->join('Wo_Users', 'Wo_Followers.follower_id', '=', 'Wo_Users.user_id')
            ->where('Wo_Followers.following_id', $userId)
            ->select('Wo_Users.*')
            ->limit($limit)
            ->get()
            ->toArray();

        $result = [];
        foreach ($followers as $follower) {
            $followerData = (array) $follower;
            
            // Remove sensitive fields
            foreach ($this->nonAllowed as $field) {
                unset($followerData[$field]);
            }

            // Check if logged user is following this follower
            $isFollowing = DB::table('Wo_Followers')
                ->where('following_id', $follower->user_id)
                ->where('follower_id', $loggedUserId)
                ->exists();
            $followerData['is_following'] = $isFollowing ? 1 : 0;

            $followerData['avatar_url'] = $follower->avatar ? asset('storage/' . $follower->avatar) : asset('images/default-avatar.png');

            $result[] = $followerData;
        }

        return $result;
    }

    /**
     * Get users following
     */
    private function getFollowing(int $userId, int $loggedUserId, int $limit = 50): array
    {
        $following = DB::table('Wo_Followers')
            ->join('Wo_Users', 'Wo_Followers.following_id', '=', 'Wo_Users.user_id')
            ->where('Wo_Followers.follower_id', $userId)
            ->select('Wo_Users.*')
            ->limit($limit)
            ->get()
            ->toArray();

        $result = [];
        foreach ($following as $follow) {
            $followData = (array) $follow;
            
            // Remove sensitive fields
            foreach ($this->nonAllowed as $field) {
                unset($followData[$field]);
            }

            // Check if logged user is following this user
            $isFollowing = DB::table('Wo_Followers')
                ->where('following_id', $follow->user_id)
                ->where('follower_id', $loggedUserId)
                ->exists();
            $followData['is_following'] = $isFollowing ? 1 : 0;

            $followData['avatar_url'] = $follow->avatar ? asset('storage/' . $follow->avatar) : asset('images/default-avatar.png');

            $result[] = $followData;
        }

        return $result;
    }

    /**
     * Get liked pages
     */
    private function getLikedPages(int $userId, int $loggedUserId, int $limit = 50): array
    {
        try {
            $pages = DB::table('Wo_PageLikes')
                ->join('Wo_Pages', 'Wo_PageLikes.page_id', '=', 'Wo_Pages.page_id')
                ->where('Wo_PageLikes.user_id', $userId)
                ->select('Wo_Pages.*')
                ->limit($limit)
                ->get()
                ->toArray();

            $result = [];
            foreach ($pages as $page) {
                $pageData = (array) $page;
                
                // Check if logged user has liked this page
                $isLiked = DB::table('Wo_PageLikes')
                    ->where('page_id', $page->page_id)
                    ->where('user_id', $loggedUserId)
                    ->exists();
                $pageData['is_liked'] = $isLiked ? 1 : 0;

                $pageData['avatar_url'] = $page->avatar ?? asset('images/default-page.png');

                $result[] = $pageData;
            }

            return $result;
        } catch (\Exception $e) {
            // Table doesn't exist, return empty array
            return [];
        }
    }

    /**
     * Get joined groups
     */
    private function getJoinedGroups(int $userId, int $loggedUserId, int $limit = 50): array
    {
        try {
            $groups = DB::table('Wo_GroupMembers')
                ->join('Wo_Groups', 'Wo_GroupMembers.group_id', '=', 'Wo_Groups.id')
                ->where('Wo_GroupMembers.user_id', $userId)
                ->select('Wo_Groups.*')
                ->limit($limit)
                ->get()
                ->toArray();

            $result = [];
            foreach ($groups as $group) {
                $groupData = (array) $group;
                
                // Check if logged user has joined this group
                $isJoined = DB::table('Wo_GroupMembers')
                    ->where('group_id', $group->id)
                    ->where('user_id', $loggedUserId)
                    ->exists();
                $groupData['is_joined'] = $isJoined ? 1 : 0;

                $groupData['avatar_url'] = $group->avatar ?? asset('images/default-group.png');

                $result[] = $groupData;
            }

            return $result;
        } catch (\Exception $e) {
            // Tables don't exist, return empty array
            return [];
        }
    }

    /**
     * Get family members
     */
    private function getFamilyMembers(int $userId): array
    {
        try {
            $family = DB::table('Wo_Family')
                ->join('Wo_Users', 'Wo_Family.member_id', '=', 'Wo_Users.user_id')
                ->where('Wo_Family.user_id', $userId)
                ->where('Wo_Family.active', 1)
                ->select('Wo_Family.*', 'Wo_Users.*', 'Wo_Family.relationship_type')
                ->get()
                ->toArray();

            $result = [];
            foreach ($family as $member) {
                $memberData = (array) $member;
                
                // Remove sensitive fields
                foreach ($this->nonAllowed as $field) {
                    unset($memberData[$field]);
                }

                $memberData['avatar_url'] = $member->avatar ?? asset('images/default-avatar.png');

                $result[] = $memberData;
            }

            return $result;
        } catch (\Exception $e) {
            // Table doesn't exist, return empty array
            return [];
        }
    }

    /**
     * Send profile visit notification
     */
    private function sendProfileVisitNotification(int $visitorId, int $profileOwnerId): void
    {
        // Check if profile visits are enabled in settings
        try {
            $profileVisitEnabled = DB::table('Wo_Config')->where('name', 'profileVisit')->value('value');
            
            if ($profileVisitEnabled != '1') {
                return;
            }
        } catch (\Exception $e) {
            // Config table doesn't exist, skip notification
            return;
        }

        // Get visitor data
        $visitor = User::where('user_id', $visitorId)->first();
        if (!$visitor || $visitor->visit_privacy == 1) {
            return;
        }

        // Get profile owner data
        $profileOwner = User::where('user_id', $profileOwnerId)->first();
        if (!$profileOwner || $profileOwner->visit_privacy == 1) {
            return;
        }

        // Check if profile owner is pro and has profile visitors feature
        $canNotify = false;
        if ($profileOwner->is_pro == 1) {
            $canNotify = true;
        }

        if (!$canNotify) {
            return;
        }

        // Create notification
        try {
            DB::table('Wo_Notifications')->insert([
                'notifier_id' => $visitorId,
                'recipient_id' => $profileOwnerId,
                'type' => 'visited_profile',
                'url' => 'index.php?link1=timeline&u=' . $visitor->username,
                'time' => time(),
                'seen' => 0
            ]);
        } catch (\Exception $e) {
            // Silently fail if notification insertion fails
        }
    }

    /**
     * Check if user is blocked
     */
    private function isBlocked(int $userId, int $loggedUserId): int
    {
        $blocked = DB::table('Wo_Blocks')
            ->where(function($query) use ($userId, $loggedUserId) {
                $query->where('blocker', $userId)->where('blocked', $loggedUserId);
            })
            ->orWhere(function($query) use ($userId, $loggedUserId) {
                $query->where('blocker', $loggedUserId)->where('blocked', $userId);
            })
            ->exists();

        return $blocked ? 1 : 0;
    }

    /**
     * Check if two users are friends
     * 
     * @param int $userId1
     * @param int $userId2
     * @return bool
     */
    private function isFriend(int $userId1, int $userId2): bool
    {
        // Check Wo_Friends table first (if it exists)
        if (Schema::hasTable('Wo_Friends')) {
            try {
                // Check if friendship exists in either direction
                $isFriend = DB::table('Wo_Friends')
                    ->where(function($q) use ($userId1, $userId2) {
                        $q->where('user_id', $userId1)
                          ->where('friend_id', $userId2);
                    })
                    ->orWhere(function($q) use ($userId1, $userId2) {
                        $q->where('user_id', $userId2)
                          ->where('friend_id', $userId1);
                    })
                    ->where('status', '2') // Status 2 = Accepted friends
                    ->exists();
                
                if ($isFriend) {
                    return true;
                }
                
                // Also check with from_id/to_id structure (alternative table structure)
                $isFriendAlt = DB::table('Wo_Friends')
                    ->where(function($q) use ($userId1, $userId2) {
                        $q->where('from_id', $userId1)
                          ->where('to_id', $userId2);
                    })
                    ->orWhere(function($q) use ($userId1, $userId2) {
                        $q->where('from_id', $userId2)
                          ->where('to_id', $userId1);
                    })
                    ->exists();
                
                return $isFriendAlt;
            } catch (\Exception $e) {
                // Table exists but query failed, fall through to followers check
            }
        }
        
        // Fallback: Check if both users are following each other (mutual following = friends)
        if (Schema::hasTable('Wo_Followers')) {
            try {
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
            } catch (\Exception $e) {
                // Table exists but query failed
            }
        }
        
        return false;
    }

    /**
     * Format time elapsed string
     */
    private function timeElapsedString(int $timestamp): string
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
     * Update data endpoint (mimics WoWonder requests.php?f=update_data)
     * Used for updating session, getting notifications, messages, and loading user posts
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateData(Request $request): JsonResponse
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

        try {
            // Update session time
            DB::table('Wo_AppsSessions')
                ->where('session_id', $token)
                ->update(['time' => time()]);

            $data = [
                'pop' => 0,
                'status' => 200,
                'notifications' => 0,
                'html' => '',
                'messages' => 0,
                'calls' => 0,
                'is_call' => 0,
                'audio_calls' => 0,
                'is_audio_call' => 0,
                'followRequests' => 0,
                'notifications_sound' => '1',
                'count_num' => 0,
            ];

            // Get unread notifications count
            $data['notifications'] = DB::table('Wo_Notifications')
                ->where('recipient_id', $tokenUserId)
                ->where('seen', 0)
                ->whereNotIn('type', ['requested_to_join_group', 'interested_event', 'going_event', 'invited_event', 'forum_reply', 'admin_notification'])
                ->count();

            // Get popup notification (unread, seen_pop = 0, from last 60 seconds, limit 1)
            // This mimics the old Wo_GetNotifications with type_2 = 'popunder'
            try {
                $timepopunder = time() - 60; // Last 60 seconds
                $popupNotification = DB::table('Wo_Notifications')
                    ->where('recipient_id', $tokenUserId)
                    ->where('seen', 0)
                    ->where('seen_pop', 0)
                    ->where('time', '>=', $timepopunder)
                    ->orderBy('time', 'desc')
                    ->first();

                if ($popupNotification) {
                    $notifier = DB::table('Wo_Users')
                        ->where('user_id', $popupNotification->notifier_id)
                        ->first();
                    
                    if ($notifier) {
                        $data['html'] = $this->formatNotificationHtml($popupNotification, $notifier);
                        $data['icon'] = $notifier->avatar ?? '';
                        $data['title'] = $notifier->name ?? '';
                        $data['notification_text'] = $this->getNotificationText($popupNotification->type);
                        $data['url'] = $popupNotification->url ?? '';
                        $data['pop'] = 200;

                        // Mark as seen_pop
                        if (isset($popupNotification->seen_pop) && $popupNotification->seen_pop == 0) {
                            DB::table('Wo_Notifications')
                                ->where('id', $popupNotification->id)
                                ->update(['seen_pop' => time()]);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Column seen_pop might not exist, skip popup notification
                // Just continue without popup notification
            }

            // Get unread messages count
            $data['messages'] = DB::table('Wo_Messages')
                ->where('to_id', $tokenUserId)
                ->where('seen', 0)
                ->where('deleted_one', '!=', $tokenUserId)
                ->where('deleted_two', '!=', $tokenUserId)
                ->count();

            // Check for group chat unread messages (if table exists)
            try {
                $groupChatUnread = DB::table('Wo_GroupChat')
                    ->where('user_id', $tokenUserId)
                    ->where('seen', 0)
                    ->count();
                $data['messages'] += $groupChatUnread;
            } catch (\Exception $e) {
                // Table doesn't exist, skip
            }

            // Check for incoming calls (video)
            try {
                $incomingCall = DB::table('Wo_VideoCalls')
                    ->where('to_id', $tokenUserId)
                    ->where('status', 'calling')
                    ->where('declined', 0)
                    ->orderBy('time', 'desc')
                    ->first();

                if ($incomingCall) {
                    $caller = DB::table('Wo_Users')
                        ->where('user_id', $incomingCall->from_id)
                        ->first();
                    
                    if ($caller) {
                        $data['calls'] = 200;
                        $data['is_call'] = 1;
                        $data['call_id'] = $incomingCall->id;
                        $data['calls_html'] = $this->formatInCallHtml($incomingCall, $caller);
                    }
                }
            } catch (\Exception $e) {
                // Table doesn't exist, skip
            }

            // Check for incoming audio calls
            try {
                $incomingAudioCall = DB::table('Wo_VideoCalls')
                    ->where('to_id', $tokenUserId)
                    ->where('type', 'audio')
                    ->where('status', 'calling')
                    ->where('declined', 0)
                    ->orderBy('time', 'desc')
                    ->first();

                if ($incomingAudioCall) {
                    $caller = DB::table('Wo_Users')
                        ->where('user_id', $incomingAudioCall->from_id)
                        ->first();
                    
                    if ($caller) {
                        $data['audio_calls'] = 200;
                        $data['is_audio_call'] = 1;
                        $data['call_id'] = $incomingAudioCall->id;
                        $data['audio_calls_html'] = $this->formatInCallHtml($incomingAudioCall, $caller, 'audio');
                    }
                }
            } catch (\Exception $e) {
                // Table doesn't exist, skip
            }

            // Get follow requests count
            try {
                $data['followRequests'] = DB::table('Wo_FollowRequests')
                    ->where('recipient_id', $tokenUserId)
                    ->count();
            } catch (\Exception $e) {
                $data['followRequests'] = 0;
            }

            // Get group chat requests count (if table exists)
            try {
                $groupChatRequests = DB::table('Wo_GroupChatRequests')
                    ->where('user_id', $tokenUserId)
                    ->count();
                $data['followRequests'] += $groupChatRequests;
            } catch (\Exception $e) {
                // Table doesn't exist, skip
            }

            // Get user notifications sound setting
            $user = DB::table('Wo_Users')->where('user_id', $tokenUserId)->first();
            if ($user && isset($user->notifications_sound)) {
                $data['notifications_sound'] = $user->notifications_sound ?? '1';
            }

            // Handle posts loading if check_posts=true
            if ($request->input('check_posts') == 'true' || $request->input('check_posts') === true) {
                $userId = $request->input('user_id');
                $beforePostId = $request->input('before_post_id');

                if (!empty($beforePostId) && !empty($userId)) {
                    $posts = $this->getUserPosts($userId, $tokenUserId, $beforePostId, 20);
                    $count = count($posts);
                    
                    $data['count_num'] = $count;
                    if ($count == 1) {
                        $data['count'] = "View {count} more post";
                    } else {
                        $data['count'] = "View {count} more posts";
                    }
                    $data['count'] = str_replace('{count}', $count, $data['count']);
                    $data['posts'] = $posts;
                }
            }

            // Handle hashtag posts if hash_posts=true
            if ($request->input('hash_posts') == 'true' || $request->input('hash_posts') === true) {
                $hashtagName = $request->input('hashtagName');
                $beforePostId = $request->input('before_post_id');

                if (!empty($hashtagName) && !empty($beforePostId)) {
                    $posts = $this->getHashtagPosts($hashtagName, $tokenUserId, $beforePostId, 20);
                    $count = count($posts);
                    
                    $data['count_num'] = $count;
                    if ($count == 1) {
                        $data['count'] = "View {count} more post";
                    } else {
                        $data['count'] = "View {count} more posts";
                    }
                    $data['count'] = str_replace('{count}', $count, $data['count']);
                    $data['posts'] = $posts;
                }
            }

            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to update data: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get user posts with pagination
     */
    private function getUserPosts(int $userId, int $loggedUserId, int $beforePostId, int $limit = 20): array
    {
        $query = DB::table('Wo_Posts')
            ->where('user_id', $userId)
            ->where('active', '1')
            ->where('id', '<', $beforePostId)
            ->orderBy('time', 'desc')
            ->limit($limit);

        $posts = $query->get();

        $result = [];
        foreach ($posts as $post) {
            $postData = $this->formatPostData($post, $loggedUserId);
            $result[] = $postData;
        }

        return $result;
    }

    /**
     * Get hashtag posts with pagination
     */
    private function getHashtagPosts(string $hashtagName, int $loggedUserId, int $beforePostId, int $limit = 20): array
    {
        // Remove # if present
        $hashtagName = ltrim($hashtagName, '#');

        $query = DB::table('Wo_Posts')
            ->where('active', '1')
            ->where('id', '<', $beforePostId)
            ->where(function($q) use ($hashtagName) {
                $q->where('postText', 'LIKE', '%#' . $hashtagName . '%')
                  ->orWhere('postText', 'LIKE', '%# ' . $hashtagName . '%');
            })
            ->orderBy('time', 'desc')
            ->limit($limit);

        $posts = $query->get();

        $result = [];
        foreach ($posts as $post) {
            $postData = $this->formatPostData($post, $loggedUserId);
            $result[] = $postData;
        }

        return $result;
    }

    /**
     * Format post data for response
     */
    private function formatPostData($post, int $loggedUserId): array
    {
        $user = DB::table('Wo_Users')->where('user_id', $post->user_id)->first();
        
        // Get reaction counts
        $reactionsCount = DB::table('Wo_Reactions')
            ->where('post_id', $post->id)
            ->count();

        // Get comments count
        $commentsCount = DB::table('Wo_Comments')
            ->where('post_id', $post->id)
            ->count();

        // Check if logged user liked this post
        $isLiked = DB::table('Wo_Reactions')
            ->where('post_id', $post->id)
            ->where('user_id', $loggedUserId)
            ->exists();

        return [
            'id' => $post->id,
            'post_id' => $post->post_id ?? $post->id,
            'user_id' => $post->user_id,
            'user' => [
                'user_id' => $user->user_id ?? 0,
                'name' => $user->name ?? '',
                'username' => $user->username ?? '',
                'avatar' => $user->avatar ?? '',
            ],
            'postText' => $post->postText ?? '',
            'postType' => $post->postType ?? 'text',
            'postPrivacy' => $post->postPrivacy ?? '0',
            'postPhoto' => $post->postPhoto ?? '',
            'postFile' => $post->postFile ?? '',
            'postYoutube' => $post->postYoutube ?? '',
            'postLink' => $post->postLink ?? '',
            'time' => $post->time ?? time(),
            'reactions_count' => $reactionsCount,
            'comments_count' => $commentsCount,
            'is_liked' => $isLiked ? 1 : 0,
            'is_owner' => ($post->user_id == $loggedUserId) ? 1 : 0,
        ];
    }

    /**
     * Get user timeline/posts (mimics old API: ajax_loading.php?link1=timeline&u=username)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getTimeline(Request $request): JsonResponse
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
                    'error_id' => '5',
                    'error_text' => 'Invalid session.'
                ]
            ], 401);
        }

        // Get username parameter (u)
        $username = $request->input('u', $request->query('u'));
        if (empty($username)) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'Username (u) parameter is required.'
                ]
            ], 400);
        }

        // Get user by username
        $user = DB::table('Wo_Users')->where('username', $username)->first();
        if (!$user) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'User not found.'
                ]
            ], 404);
        }

        // Pagination parameters
        $beforePostId = (int) ($request->input('before_post_id', $request->query('before_post_id', PHP_INT_MAX)));
        $limit = (int) ($request->input('limit', $request->query('limit', 20)));
        $limit = max(1, min($limit, 50));

        // Get user posts
        $posts = $this->getTimelinePosts($user->user_id, $tokenUserId, $beforePostId, $limit);

        // Get user profile data
        $userData = $this->getTimelineUserData($user, $tokenUserId);

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'api_version' => '1.0',
            'user_data' => $userData,
            'posts' => $posts,
            'count' => count($posts),
        ]);
    }

    /**
     * Get timeline posts for a user
     * 
     * @param int $userId
     * @param int $loggedUserId
     * @param int $beforePostId
     * @param int $limit
     * @return array
     */
    private function getTimelinePosts(int $userId, int $loggedUserId, int $beforePostId, int $limit = 20): array
    {
        // Use post_id for reactions query (matching how reactions are stored)
        $query = DB::table('Wo_Posts')
            ->where('user_id', $userId)
            ->where(function($q) {
                // Handle both string '1' and integer 1 for active field
                $q->where('active', '1')
                  ->orWhere('active', 1);
            });

        // Handle pagination
        if ($beforePostId < PHP_INT_MAX) {
            $query->where('id', '<', $beforePostId);
        }

        $query->orderBy('time', 'desc')->limit($limit);
        $posts = $query->get();

        $result = [];
        foreach ($posts as $post) {
            // Use post_id for reactions (matching PostController pattern)
            $postIdForReactions = $post->post_id ?? $post->id;
            
            $user = DB::table('Wo_Users')->where('user_id', $post->user_id)->first();
            
            // Get reaction counts (using post_id)
            $reactionsCount = 0;
            if (\Illuminate\Support\Facades\Schema::hasTable('Wo_Reactions')) {
                try {
                    $reactionsCount = DB::table('Wo_Reactions')
                        ->where('post_id', $postIdForReactions)
                        ->where('comment_id', 0)
                        ->count();
                } catch (\Exception $e) {
                    // Fallback to post_likes column
                    $reactionsCount = (int) ($post->post_likes ?? 0);
                }
            } else {
                $reactionsCount = (int) ($post->post_likes ?? 0);
            }

            // Get comments count
            $commentsCount = 0;
            if (\Illuminate\Support\Facades\Schema::hasTable('Wo_Comments')) {
                try {
                    $commentsCount = DB::table('Wo_Comments')
                        ->where('post_id', $post->id)
                        ->count();
                } catch (\Exception $e) {
                    $commentsCount = (int) ($post->post_comments ?? 0);
                }
            } else {
                $commentsCount = (int) ($post->post_comments ?? 0);
            }

            // Check if logged user liked this post
            $isLiked = false;
            if (\Illuminate\Support\Facades\Schema::hasTable('Wo_Reactions')) {
                try {
                    $isLiked = DB::table('Wo_Reactions')
                        ->where('post_id', $postIdForReactions)
                        ->where('user_id', $loggedUserId)
                        ->where('comment_id', 0)
                        ->exists();
                } catch (\Exception $e) {
                    $isLiked = false;
                }
            }

            $result[] = [
                'id' => $post->id,
                'post_id' => $post->post_id ?? $post->id,
                'user_id' => $post->user_id,
                'user' => [
                    'user_id' => $user->user_id ?? 0,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? $user->username ?? ''),
                    'username' => $user->username ?? '',
                    'avatar' => $user->avatar ?? '',
                    'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'verified' => (bool) ($user->verified ?? false),
                ],
                'postText' => $post->postText ?? '',
                'postType' => $post->postType ?? 'text',
                'postPrivacy' => $post->postPrivacy ?? '0',
                'postPhoto' => $post->postPhoto ?? '',
                'post_photo_url' => $post->postPhoto ? asset('storage/' . $post->postPhoto) : null,
                'postFile' => $post->postFile ?? '',
                'post_file_url' => $post->postFile ? asset('storage/' . $post->postFile) : null,
                'postYoutube' => $post->postYoutube ?? '',
                'postVimeo' => $post->postVimeo ?? '',
                'postLink' => $post->postLink ?? '',
                'postLinkTitle' => $post->postLinkTitle ?? '',
                'postLinkImage' => $post->postLinkImage ?? '',
                'postLinkContent' => $post->postLinkContent ?? '',
                'time' => $post->time ?? time(),
                'created_at' => $post->time ? date('c', $post->time) : null,
                'reactions_count' => $reactionsCount,
                'comments_count' => $commentsCount,
                'shares_count' => (int) ($post->postShare ?? 0),
                'is_liked' => $isLiked ? 1 : 0,
                'is_owner' => ($post->user_id == $loggedUserId) ? 1 : 0,
            ];
        }

        return $result;
    }

    /**
     * Get user data for timeline
     * 
     * @param object $user
     * @param int $loggedUserId
     * @return array
     */
    private function getTimelineUserData($user, int $loggedUserId): array
    {
        // Get user stats
        $postCount = DB::table('Wo_Posts')
            ->where('user_id', $user->user_id)
            ->where('active', '1')
            ->count();

        // Check follow status
        $isFollowing = false;
        if (\Illuminate\Support\Facades\Schema::hasTable('Wo_Followers')) {
            try {
                $isFollowing = DB::table('Wo_Followers')
                    ->where('follower_id', $loggedUserId)
                    ->where('following_id', $user->user_id)
                    ->exists();
            } catch (\Exception $e) {
                $isFollowing = false;
            }
        }

        // Check if viewing own profile
        $isOwner = ($user->user_id == $loggedUserId);

        return [
            'user_id' => $user->user_id,
            'username' => $user->username ?? '',
            'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? $user->username ?? ''),
            'first_name' => $user->first_name ?? '',
            'last_name' => $user->last_name ?? '',
            'email' => $isOwner ? ($user->email ?? '') : '',
            'avatar' => $user->avatar ?? '',
            'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'cover' => $user->cover ?? '',
            'cover_url' => $user->cover ? asset('storage/' . $user->cover) : null,
            'about' => $user->about ?? '',
            'verified' => (bool) ($user->verified ?? false),
            'is_following' => $isFollowing ? 1 : 0,
            'is_owner' => $isOwner ? 1 : 0,
            'post_count' => $postCount,
        ];
    }

    /**
     * Format notification HTML
     */
    private function formatNotificationHtml($notification, $notifier): string
    {
        // Simple HTML format for notification popup
        return '<div class="notification-popup">
            <img src="' . ($notifier->avatar ?? '') . '" alt="' . ($notifier->name ?? '') . '">
            <div>
                <strong>' . ($notifier->name ?? '') . '</strong>
                <p>' . $this->getNotificationText($notification->type) . '</p>
            </div>
        </div>';
    }

    /**
     * Get notification text by type
     */
    private function getNotificationText(string $type): string
    {
        $texts = [
            'liked_post' => 'liked your post',
            'commented_post' => 'commented on your post',
            'shared_post' => 'shared your post',
            'followed_you' => 'started following you',
            'visited_profile' => 'visited your profile',
            'mentioned_you' => 'mentioned you',
            'joined_group' => 'joined your group',
            'accepted_request' => 'accepted your request',
        ];

        return $texts[$type] ?? 'sent you a notification';
    }

    /**
     * Format in-call HTML
     */
    private function formatInCallHtml($call, $caller, string $type = 'video'): string
    {
        $callType = $type == 'audio' ? 'Audio' : 'Video';
        return '<div class="in-call-modal">
            <h3>Incoming ' . $callType . ' Call</h3>
            <img src="' . ($caller->avatar ?? '') . '" alt="' . ($caller->name ?? '') . '">
            <p>' . ($caller->name ?? 'Unknown') . ' is calling you</p>
            <button onclick="acceptCall(' . $call->id . ')">Accept</button>
            <button onclick="declineCall(' . $call->id . ')">Decline</button>
        </div>';
    }
}

