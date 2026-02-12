<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NotificationsController extends Controller
{
    /**
     * Get notifications (mimics old API: requests.php?f=get_notifications / get_notifications.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getNotifications(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 3,
                    'error_text' => 'No user id sent.'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Session id is wrong.'
                ]
            ], 401);
        }

        // Get user data
        $user = DB::table('Wo_Users')->where('user_id', $tokenUserId)->first();
        if (!$user) {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Username is not exists.'
                ]
            ], 404);
        }

        // Get notifications (exclude only specific system types like Wo_GetNotifications remove_notification)
        $notifications = $this->getUserNotifications($tokenUserId);

        // Types to EXCLUDE from the count (same list used in the old WoWonder API)
        $excludedTypes = [
            'requested_to_join_group',
            'interested_event',
            'going_event',
            'invited_event',
            'forum_reply',
            'admin_notification',
        ];

        // Count unread notifications for all other types (likes, comments, follows, etc.)
        $countNotifications = DB::table('Wo_Notifications')
            ->where('recipient_id', $tokenUserId)
            ->where('seen', 0)
            ->whereNotIn('type', $excludedTypes)
            ->count();

        // Count friend requests
        $countFriendRequests = DB::table('Wo_Followers')
            ->where('following_id', $tokenUserId)
            ->where('active', 0)
            ->count();

        // Get friend requests
        $friendRequests = $this->getFriendRequests($tokenUserId);

        // Count unread messages
        $countMessages = DB::table('Wo_Messages')
            ->where('to_id', $tokenUserId)
            ->where('seen', 0)
            ->where('deleted_one', '!=', $tokenUserId)
            ->where('deleted_two', '!=', $tokenUserId)
            ->count();

        // Mark notifications as seen if requested
        if ($request->filled('seen') && $request->input('seen') == 1) {
            $notificationIds = [];
            foreach ($notifications as $notification) {
                if ($notification['seen'] == 0) {
                    $notificationIds[] = $notification['id'];
                }
            }
            if (!empty($notificationIds)) {
                DB::table('Wo_Notifications')
                    ->whereIn('id', $notificationIds)
                    ->update(['seen' => time()]);
            }
        }

        // Format notifications
        $formattedNotifications = [];
        foreach ($notifications as $notification) {
            $formattedNotifications[] = $this->formatNotification($notification, $user);
        }

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'notifications' => $formattedNotifications,
            'count_notifications' => $countNotifications,
            'count_friend_requests' => $countFriendRequests,
            'friend_requests' => $friendRequests,
            'count_messages' => $countMessages,
        ]);
    }

    /**
     * Delete notification (mimics old API: notifications.php?type=delete)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(Request $request): JsonResponse
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

        // Validate request
        if (empty($request->input('id')) || !is_numeric($request->input('id')) || $request->input('id') < 1) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 7,
                    'error_text' => 'id must be numeric and greater than 0'
                ]
            ], 400);
        }

        $notificationId = (int) $request->input('id');

        // Check if notification exists and belongs to user
        $notification = DB::table('Wo_Notifications')
            ->where('id', $notificationId)
            ->where('recipient_id', $tokenUserId)
            ->first();

        if (!$notification) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Notification not found or you do not have permission to delete it'
                ]
            ], 404);
        }

        // Delete notification
        DB::table('Wo_Notifications')->where('id', $notificationId)->delete();

        return response()->json([
            'api_status' => 200,
            'message_data' => 'notification delete successfully'
        ]);
    }

    /**
     * Mark all notifications as seen
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function markAllSeen(Request $request): JsonResponse
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

        try {
            // Mark all unread notifications as seen
            $updated = DB::table('Wo_Notifications')
                ->where('recipient_id', $tokenUserId)
                ->where('seen', 0)
                ->update(['seen' => time()]);

            return response()->json([
                'api_status' => 200,
                'message' => 'All notifications marked as seen',
                'updated_count' => $updated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Failed to mark notifications as seen: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Stop notify from user (mimics old API: stop_notify.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function stopNotify(Request $request): JsonResponse
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

        // Validate request
        if (empty($request->input('user_id')) || !is_numeric($request->input('user_id')) || $request->input('user_id') < 1) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'user_id can not be empty'
                ]
            ], 400);
        }

        $userId = (int) $request->input('user_id');

        // Check if user is following
        $follow = DB::table('Wo_Followers')
            ->where('following_id', $userId)
            ->where('follower_id', $tokenUserId)
            ->first();

        if (!$follow) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'You are not following this user'
                ]
            ], 400);
        }

        // Toggle notify status
        $currentNotify = $follow->notify ?? 1;
        $newNotify = $currentNotify == 1 ? 0 : 1;

        DB::table('Wo_Followers')
            ->where('following_id', $userId)
            ->where('follower_id', $tokenUserId)
            ->update(['notify' => $newNotify]);

        return response()->json([
            'api_status' => 200,
            'code' => $newNotify
        ]);
    }

    /**
     * Get user notifications
     * 
     * @param string $userId
     * @return array
     */
    private function getUserNotifications(string $userId): array
    {
        // Types to EXCLUDE from results (match Wo_GetNotifications remove_notification list)
        $excludedTypes = [
            'requested_to_join_group',
            'interested_event',
            'going_event',
            'invited_event',
            'forum_reply',
            'admin_notification',
        ];

        $notifications = DB::table('Wo_Notifications')
            ->where('recipient_id', $userId)
            ->whereNotIn('type', $excludedTypes)
            ->orderByDesc('time')
            ->limit(50)
            ->get();

        $formatted = [];
        foreach ($notifications as $notification) {
            // Get notifier data
            $notifier = DB::table('Wo_Users')->where('user_id', $notification->notifier_id)->first();
            
            $formatted[] = [
                'id' => $notification->id,
                'notifier_id' => $notification->notifier_id,
                'recipient_id' => $notification->recipient_id,
                'type' => $notification->type,
                'type2' => $notification->type2 ?? '',
                'text' => $notification->text ?? '',
                'url' => $notification->url ?? '',
                'seen' => $notification->seen ?? 0,
                'time' => $notification->time ?? time(),
                'post_id' => $notification->post_id ?? null,
                'page_id' => $notification->page_id ?? null,
                'group_id' => $notification->group_id ?? null,
                'event_id' => $notification->event_id ?? null,
                'notifier' => $notifier ? [
                    'user_id' => $notifier->user_id,
                    'username' => $notifier->username ?? 'Unknown',
                    'name' => $notifier->name ?? $notifier->username ?? 'Unknown User',
                    'avatar' => $notifier->avatar ?? '',
                    'avatar_url' => $notifier->avatar ? asset('storage/' . $notifier->avatar) : null,
                    'verified' => (bool) ($notifier->verified ?? false),
                ] : null,
            ];
        }

        return $formatted;
    }

    /**
     * Get friend requests
     * 
     * @param string $userId
     * @return array
     */
    private function getFriendRequests(string $userId): array
    {
        $requests = DB::table('Wo_Followers')
            ->where('following_id', $userId)
            ->where('active', 0)
            ->orderByDesc('time')
            ->limit(20)
            ->get();

        $formatted = [];
        foreach ($requests as $request) {
            $user = DB::table('Wo_Users')->where('user_id', $request->follower_id)->first();
            if ($user) {
                $formatted[] = [
                    'user_id' => $user->user_id,
                    'username' => $user->username ?? 'Unknown',
                    'name' => $user->name ?? $user->username ?? 'Unknown User',
                    'avatar' => $user->avatar ?? '',
                    'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'verified' => (bool) ($user->verified ?? false),
                ];
            }
        }

        return $formatted;
    }

    /**
     * Format notification with type text and icon
     * 
     * @param array $notification
     * @param object $user
     * @return array
     */
    private function formatNotification(array $notification, object $user): array
    {
        $notification['type_text'] = '';
        $notification['icon'] = '';
        $notification['time_text'] = $this->getTimeElapsedString($notification['time']);
        $notification['time_text_string'] = $this->getTimeElapsedString($notification['time']);

        // Format time text
        $timeToday = time() - 86400;
        if ($notification['time'] < $timeToday) {
            $notification['time_text'] = date('m.d.y', $notification['time']);
        } else {
            $notification['time_text'] = date('H:i', $notification['time']);
        }

        // Determine type text and icon based on notification type
        $type = $notification['type'];
        $type2 = $notification['type2'] ?? '';

        // Map type2 to label
        $type2Label = '';
        if ($type2 == 'post_image') {
            $type2Label = 'photo';
        } elseif (in_array($type2, ['post_youtube', 'post_video'])) {
            $type2Label = 'video';
        } elseif ($type2 == 'post_file') {
            $type2Label = 'file';
        } elseif ($type2 == 'post_soundFile') {
            $type2Label = 'sound';
        } elseif ($type2 == 'post_avatar') {
            $type2Label = 'avatar';
        } elseif ($type2 == 'post_cover') {
            $type2Label = 'cover';
        } else {
            $type2Label = 'post';
        }

        $text = $notification['text'] ?? '';
        $notificationText = !empty($text) ? '"' . $text . '"' : '';

        // Set type text and icon based on notification type
        switch ($type) {
            case 'following':
                $notification['type_text'] = 'started following you';
                $notification['icon'] = 'user-plus';
                break;
            case 'follow_request':
                $notification['type_text'] = 'sent you a follow request';
                $notification['icon'] = 'user-plus';
                break;
            case 'comment_mention':
                $notification['type_text'] = 'mentioned you in a comment';
                $notification['icon'] = 'at';
                break;
            case 'post_mention':
                $notification['type_text'] = 'mentioned you in a post';
                $notification['icon'] = 'at';
                break;
            case 'liked_post':
                $notification['type_text'] = 'liked your ' . $type2Label . ($notificationText ? ' ' . $notificationText : '');
                $notification['icon'] = 'thumbs-up';
                break;
            case 'wondered_post':
            case 'disliked_post':
                $notification['type_text'] = 'wondered your ' . $type2Label . ($notificationText ? ' ' . $notificationText : '');
                $notification['icon'] = 'wonder';
                break;
            case 'share_post':
                $notification['type_text'] = 'shared your ' . $type2Label . ($notificationText ? ' ' . $notificationText : '');
                $notification['icon'] = 'share';
                break;
            case 'comment':
                $notification['type_text'] = 'commented on your ' . $type2Label . ($notificationText ? ' ' . $notificationText : '');
                $notification['icon'] = 'comment';
                break;
            case 'comment_reply':
                $notification['type_text'] = 'replied to your comment' . ($notificationText ? ' ' . $notificationText : '');
                $notification['icon'] = 'comment';
                break;
            case 'comment_reply_mention':
                $notification['type_text'] = 'mentioned you in a comment reply' . ($notificationText ? ' ' . $notificationText : '');
                $notification['icon'] = 'comment';
                break;
            case 'also_replied':
                $notification['type_text'] = 'also replied to the comment' . ($notificationText ? ' ' . $notificationText : '');
                $notification['icon'] = 'comment';
                break;
            case 'liked_comment':
                $notification['type_text'] = 'liked your comment' . ($notificationText ? ' ' . $notificationText : '');
                $notification['icon'] = 'thumbs-up';
                break;
            case 'wondered_comment':
            case 'disliked_comment':
                $notification['type_text'] = 'wondered your comment' . ($notificationText ? ' ' . $notificationText : '');
                $notification['icon'] = 'wonder';
                break;
            case 'liked_reply_comment':
                $notification['type_text'] = 'liked your reply' . ($notificationText ? ' ' . $notificationText : '');
                $notification['icon'] = 'thumbs-up';
                break;
            case 'wondered_reply_comment':
            case 'disliked_reply_comment':
                $notification['type_text'] = 'wondered your reply' . ($notificationText ? ' ' . $notificationText : '');
                $notification['icon'] = 'wonder';
                break;
            case 'profile_wall_post':
                $notification['type_text'] = 'posted on your timeline';
                $notification['icon'] = 'user';
                break;
            case 'visited_profile':
                $notification['type_text'] = 'visited your profile';
                $notification['icon'] = 'eye';
                break;
            case 'liked_page':
                if ($notification['page_id']) {
                    $page = DB::table('Wo_Pages')->where('page_id', $notification['page_id'])->first();
                    $pageName = $page->page_name ?? 'page';
                    $notification['type_text'] = 'liked the page ' . $pageName;
                } else {
                    $notification['type_text'] = 'liked a page';
                }
                $notification['icon'] = 'thumbs-up';
                break;
            case 'joined_group':
                if ($notification['group_id']) {
                    $group = DB::table('Wo_Groups')->where('id', $notification['group_id'])->first();
                    $groupName = $group->group_name ?? 'group';
                    $notification['type_text'] = 'joined the group ' . $groupName;
                } else {
                    $notification['type_text'] = 'joined a group';
                }
                $notification['icon'] = 'users';
                break;
            case 'accepted_invite':
            case 'invited_page':
                $notification['type_text'] = 'invited you to a page';
                $notification['icon'] = 'user-plus';
                break;
            case 'accepted_join_request':
            case 'added_you_to_group':
                $notification['type_text'] = 'added you to a group';
                $notification['icon'] = 'user-plus';
                break;
            case 'accepted_request':
                $notification['type_text'] = 'accepted your follow request';
                $notification['icon'] = 'user-plus';
                break;
            case 'viewed_story':
                $notification['type_text'] = 'viewed your story';
                $notification['icon'] = 'story';
                break;
            default:
                $notification['type_text'] = 'sent you a notification';
                $notification['icon'] = 'bell';
                break;
        }

        // Add unread class
        $notification['unread_class'] = $notification['seen'] == 0 ? ' unread' : '';

        return $notification;
    }

    /**
     * Get time elapsed string
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

