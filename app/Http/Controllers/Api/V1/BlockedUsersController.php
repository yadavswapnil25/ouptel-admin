<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BlockedUsersController extends Controller
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
        'password_reset_code',
        'social_login',
        'wallet',
        'balance',
        'ref_user_id',
        'referrer',
        'admin',
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
        'new_email',
        'new_phone',
        'info_file',
    ];

    /**
     * Get blocked users list (mimics WoWonder get_blocked_users.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getBlockedUsers(Request $request): JsonResponse
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
            // Get blocked users
            $blockedUsers = DB::table('Wo_Blocks')
                ->join('Wo_Users', 'Wo_Blocks.blocked', '=', 'Wo_Users.user_id')
                ->where('Wo_Blocks.blocker', $tokenUserId)
                ->select('Wo_Users.*')
                ->get();

            $formattedUsers = [];
            foreach ($blockedUsers as $user) {
                $userData = (array) $user;

                // Remove sensitive fields
                foreach ($this->nonAllowed as $field) {
                    unset($userData[$field]);
                }

                // Add formatted fields
                $userData['profile_picture'] = $user->avatar ?? '';
                $userData['cover_picture'] = $user->cover ?? '';
                $userData['avatar_url'] = $user->avatar ? asset('storage/' . $user->avatar) : asset('images/default-avatar.png');
                $userData['cover_url'] = $user->cover ? asset('storage/' . $user->cover) : asset('images/default-cover.jpg');
                $userData['gender_text'] = $user->gender === 'male' ? 'Male' : 'Female';
                $userData['lastseen_time_text'] = $this->timeElapsedString($user->lastseen ?? time());
                $userData['lastseen'] = ($user->lastseen ?? 0) > (time() - 60) ? 'on' : 'off';
                $userData['lastseen_unix_time'] = $user->lastseen ?? 0;
                $userData['url'] = url('/') . '/' . $user->username;

                $formattedUsers[] = $userData;
            }

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'blocked_users' => $formattedUsers,
                'total_blocked' => count($formattedUsers)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to get blocked users: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Block or unblock a user (mimics WoWonder block_user.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function blockUser(Request $request): JsonResponse
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

        // Validate input
        $validator = Validator::make($request->all(), [
            'recipient_id' => 'required|integer',
            'block_type' => 'required|in:block,un-block',
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
            $recipientId = $request->input('recipient_id');
            $blockType = $request->input('block_type');

            // Check if recipient exists
            $recipient = User::where('user_id', $recipientId)->first();
            if (!$recipient) {
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

            // Cannot block yourself
            if ($recipientId == $tokenUserId) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '8',
                        'error_text' => 'Cannot block yourself.'
                    ]
                ], 422);
            }

            // Check if recipient is admin (cannot block admins)
            if ($recipient->admin == 1) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '9',
                        'error_text' => 'Cannot block admin users.'
                    ]
                ], 403);
            }

            $blocked = '';

            // Check if already blocked
            $isBlocked = DB::table('Wo_Blocks')
                ->where('blocker', $tokenUserId)
                ->where('blocked', $recipientId)
                ->exists();

            if ($blockType === 'block' && !$isBlocked) {
                // Block user
                DB::table('Wo_Blocks')->insert([
                    'blocker' => $tokenUserId,
                    'blocked' => $recipientId
                ]);

                // Remove following relationships (both ways)
                DB::table('Wo_Followers')
                    ->where(function($query) use ($tokenUserId, $recipientId) {
                        $query->where('follower_id', $tokenUserId)->where('following_id', $recipientId);
                    })
                    ->orWhere(function($query) use ($tokenUserId, $recipientId) {
                        $query->where('follower_id', $recipientId)->where('following_id', $tokenUserId);
                    })
                    ->delete();

                // Remove friend relationship if exists
                DB::table('Wo_Friends')
                    ->where(function($query) use ($tokenUserId, $recipientId) {
                        $query->where('user_id', $tokenUserId)->where('friend_id', $recipientId);
                    })
                    ->orWhere(function($query) use ($tokenUserId, $recipientId) {
                        $query->where('user_id', $recipientId)->where('friend_id', $tokenUserId);
                    })
                    ->delete();

                $blocked = 'blocked';

            } elseif ($blockType === 'un-block' && $isBlocked) {
                // Unblock user
                DB::table('Wo_Blocks')
                    ->where('blocker', $tokenUserId)
                    ->where('blocked', $recipientId)
                    ->delete();

                $blocked = 'unblocked';
            } else {
                // No action needed
                $blocked = $isBlocked ? 'already_blocked' : 'not_blocked';
            }

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'blocked' => $blocked,
                'message' => $blocked === 'blocked' ? 'User blocked successfully' : 
                            ($blocked === 'unblocked' ? 'User unblocked successfully' : 'No action taken')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '10',
                    'error_text' => 'Failed to block/unblock user: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Check if a user is blocked
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function checkBlockStatus(Request $request): JsonResponse
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

        // Validate input
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
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
            $userId = $request->input('user_id');

            // Check if blocked (either direction)
            $isBlocked = DB::table('Wo_Blocks')
                ->where(function($query) use ($tokenUserId, $userId) {
                    $query->where('blocker', $tokenUserId)->where('blocked', $userId);
                })
                ->orWhere(function($query) use ($tokenUserId, $userId) {
                    $query->where('blocker', $userId)->where('blocked', $tokenUserId);
                })
                ->exists();

            // Check who blocked whom
            $iBlockedThem = DB::table('Wo_Blocks')
                ->where('blocker', $tokenUserId)
                ->where('blocked', $userId)
                ->exists();

            $theyBlockedMe = DB::table('Wo_Blocks')
                ->where('blocker', $userId)
                ->where('blocked', $tokenUserId)
                ->exists();

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'is_blocked' => $isBlocked ? 1 : 0,
                'i_blocked_them' => $iBlockedThem ? 1 : 0,
                'they_blocked_me' => $theyBlockedMe ? 1 : 0
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to check block status: ' . $e->getMessage()
                ]
            ], 500);
        }
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
}

