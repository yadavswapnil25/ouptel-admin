<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NotificationSettingsController extends Controller
{
    /**
     * Get notification settings (mimics WoWonder notification settings)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getNotificationSettings(Request $request): JsonResponse
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
            // Get user's notification settings (individual columns)
            $user = User::where('user_id', $tokenUserId)->first();

            if (!$user) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '6',
                        'error_text' => 'User not found.'
                    ]
                ], 404);
            }

            // Get notification settings from individual columns
            $settings = [
                'e_liked' => (int) ($user->e_liked ?? 1),
                'e_shared' => (int) ($user->e_shared ?? 1),
                'e_wondered' => (int) ($user->e_wondered ?? 1),
                'e_commented' => (int) ($user->e_commented ?? 1),
                'e_followed' => (int) ($user->e_followed ?? 1),
                'e_accepted' => (int) ($user->e_accepted ?? 1),
                'e_mentioned' => (int) ($user->e_mentioned ?? 1),
                'e_joined_group' => (int) ($user->e_joined_group ?? 1),
                'e_liked_page' => (int) ($user->e_liked_page ?? 1),
                'e_visited' => (int) ($user->e_visited ?? 1),
                'e_profile_wall_post' => (int) ($user->e_profile_wall_post ?? 1),
                'e_memory' => (int) ($user->e_memory ?? 1),
            ];

            // Add human-readable labels
            $settingsWithLabels = [
                'e_liked' => [
                    'value' => $settings['e_liked'],
                    'enabled' => $settings['e_liked'] === 1,
                    'label' => 'Someone liked your post',
                ],
                'e_shared' => [
                    'value' => $settings['e_shared'],
                    'enabled' => $settings['e_shared'] === 1,
                    'label' => 'Someone shared your post',
                ],
                'e_wondered' => [
                    'value' => $settings['e_wondered'],
                    'enabled' => $settings['e_wondered'] === 1,
                    'label' => 'Someone reacted to your post',
                ],
                'e_commented' => [
                    'value' => $settings['e_commented'],
                    'enabled' => $settings['e_commented'] === 1,
                    'label' => 'Someone commented on your post',
                ],
                'e_followed' => [
                    'value' => $settings['e_followed'],
                    'enabled' => $settings['e_followed'] === 1,
                    'label' => 'Someone followed you',
                ],
                'e_accepted' => [
                    'value' => $settings['e_accepted'],
                    'enabled' => $settings['e_accepted'] === 1,
                    'label' => 'Someone accepted your follow request',
                ],
                'e_mentioned' => [
                    'value' => $settings['e_mentioned'],
                    'enabled' => $settings['e_mentioned'] === 1,
                    'label' => 'Someone mentioned you',
                ],
                'e_joined_group' => [
                    'value' => $settings['e_joined_group'],
                    'enabled' => $settings['e_joined_group'] === 1,
                    'label' => 'Someone joined your group',
                ],
                'e_liked_page' => [
                    'value' => $settings['e_liked_page'],
                    'enabled' => $settings['e_liked_page'] === 1,
                    'label' => 'Someone liked your page',
                ],
                'e_visited' => [
                    'value' => $settings['e_visited'],
                    'enabled' => $settings['e_visited'] === 1,
                    'label' => 'Someone visited your profile',
                ],
                'e_profile_wall_post' => [
                    'value' => $settings['e_profile_wall_post'],
                    'enabled' => $settings['e_profile_wall_post'] === 1,
                    'label' => 'Someone posted on your wall',
                ],
                'e_memory' => [
                    'value' => $settings['e_memory'],
                    'enabled' => $settings['e_memory'] === 1,
                    'label' => 'Memory reminders',
                ],
            ];

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'notification_settings' => $settings,
                'notification_settings_detailed' => $settingsWithLabels
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to get notification settings: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Update notification settings (mimics WoWonder update-user-data.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateNotificationSettings(Request $request): JsonResponse
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
            'e_liked' => 'nullable|in:0,1',
            'e_shared' => 'nullable|in:0,1',
            'e_wondered' => 'nullable|in:0,1',
            'e_commented' => 'nullable|in:0,1',
            'e_followed' => 'nullable|in:0,1',
            'e_accepted' => 'nullable|in:0,1',
            'e_mentioned' => 'nullable|in:0,1',
            'e_joined_group' => 'nullable|in:0,1',
            'e_liked_page' => 'nullable|in:0,1',
            'e_visited' => 'nullable|in:0,1',
            'e_profile_wall_post' => 'nullable|in:0,1',
            'e_memory' => 'nullable|in:0,1',
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
            $user = User::where('user_id', $tokenUserId)->first();
            if (!$user) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '6',
                        'error_text' => 'User not found.'
                    ]
                ], 404);
            }

            // Build update data for only the provided settings
            $updateData = [];
            if ($request->has('e_liked')) $updateData['e_liked'] = (string) $request->input('e_liked');
            if ($request->has('e_shared')) $updateData['e_shared'] = (string) $request->input('e_shared');
            if ($request->has('e_wondered')) $updateData['e_wondered'] = (string) $request->input('e_wondered');
            if ($request->has('e_commented')) $updateData['e_commented'] = (string) $request->input('e_commented');
            if ($request->has('e_followed')) $updateData['e_followed'] = (string) $request->input('e_followed');
            if ($request->has('e_accepted')) $updateData['e_accepted'] = (string) $request->input('e_accepted');
            if ($request->has('e_mentioned')) $updateData['e_mentioned'] = (string) $request->input('e_mentioned');
            if ($request->has('e_joined_group')) $updateData['e_joined_group'] = (string) $request->input('e_joined_group');
            if ($request->has('e_liked_page')) $updateData['e_liked_page'] = (string) $request->input('e_liked_page');
            if ($request->has('e_visited')) $updateData['e_visited'] = (string) $request->input('e_visited');
            if ($request->has('e_profile_wall_post')) $updateData['e_profile_wall_post'] = (string) $request->input('e_profile_wall_post');
            if ($request->has('e_memory')) $updateData['e_memory'] = (string) $request->input('e_memory');

            // Update individual columns
            DB::table('Wo_Users')
                ->where('user_id', $tokenUserId)
                ->update($updateData);

            // Get updated settings
            $user = User::where('user_id', $tokenUserId)->first();
            $updatedSettings = [
                'e_liked' => (int) ($user->e_liked ?? 1),
                'e_shared' => (int) ($user->e_shared ?? 1),
                'e_wondered' => (int) ($user->e_wondered ?? 1),
                'e_commented' => (int) ($user->e_commented ?? 1),
                'e_followed' => (int) ($user->e_followed ?? 1),
                'e_accepted' => (int) ($user->e_accepted ?? 1),
                'e_mentioned' => (int) ($user->e_mentioned ?? 1),
                'e_joined_group' => (int) ($user->e_joined_group ?? 1),
                'e_liked_page' => (int) ($user->e_liked_page ?? 1),
                'e_visited' => (int) ($user->e_visited ?? 1),
                'e_profile_wall_post' => (int) ($user->e_profile_wall_post ?? 1),
                'e_memory' => (int) ($user->e_memory ?? 1),
            ];

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Notification settings updated successfully',
                'notification_settings' => $updatedSettings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '9',
                    'error_text' => 'Failed to update notification settings: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Enable all notifications
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function enableAllNotifications(Request $request): JsonResponse
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
            // Enable all notifications (update individual columns)
            DB::table('Wo_Users')
                ->where('user_id', $tokenUserId)
                ->update([
                    'e_liked' => '1',
                    'e_shared' => '1',
                    'e_wondered' => '1',
                    'e_commented' => '1',
                    'e_followed' => '1',
                    'e_accepted' => '1',
                    'e_mentioned' => '1',
                    'e_joined_group' => '1',
                    'e_liked_page' => '1',
                    'e_visited' => '1',
                    'e_profile_wall_post' => '1',
                    'e_memory' => '1',
                ]);

            $settings = [
                'e_liked' => 1,
                'e_shared' => 1,
                'e_wondered' => 1,
                'e_commented' => 1,
                'e_followed' => 1,
                'e_accepted' => 1,
                'e_mentioned' => 1,
                'e_joined_group' => 1,
                'e_liked_page' => 1,
                'e_visited' => 1,
                'e_profile_wall_post' => 1,
                'e_memory' => 1,
            ];

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'All notifications enabled',
                'notification_settings' => $settings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '9',
                    'error_text' => 'Failed to enable notifications: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Disable all notifications
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function disableAllNotifications(Request $request): JsonResponse
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
            // Disable all notifications (update individual columns)
            DB::table('Wo_Users')
                ->where('user_id', $tokenUserId)
                ->update([
                    'e_liked' => '0',
                    'e_shared' => '0',
                    'e_wondered' => '0',
                    'e_commented' => '0',
                    'e_followed' => '0',
                    'e_accepted' => '0',
                    'e_mentioned' => '0',
                    'e_joined_group' => '0',
                    'e_liked_page' => '0',
                    'e_visited' => '0',
                    'e_profile_wall_post' => '0',
                    'e_memory' => '0',
                ]);

            $settings = [
                'e_liked' => 0,
                'e_shared' => 0,
                'e_wondered' => 0,
                'e_commented' => 0,
                'e_followed' => 0,
                'e_accepted' => 0,
                'e_mentioned' => 0,
                'e_joined_group' => 0,
                'e_liked_page' => 0,
                'e_visited' => 0,
                'e_profile_wall_post' => 0,
                'e_memory' => 0,
            ];

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'All notifications disabled',
                'notification_settings' => $settings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '9',
                    'error_text' => 'Failed to disable notifications: ' . $e->getMessage()
                ]
            ], 500);
        }
    }
}

