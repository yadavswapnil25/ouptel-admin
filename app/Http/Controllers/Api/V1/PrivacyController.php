<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PrivacyController extends Controller
{
    /**
     * Get user privacy settings (mimics WoWonder privacy settings retrieval)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getPrivacySettings(Request $request): JsonResponse
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
            // Get user data
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

            // Get privacy settings from database
            $privacySettings = DB::table('Wo_Users')
                ->where('user_id', $tokenUserId)
                ->select(
                    'message_privacy',
                    'follow_privacy',
                    'birth_privacy',
                    'status',
                    'visit_privacy',
                    'post_privacy',
                    'confirm_followers',
                    'show_activities_privacy',
                    'share_my_location',
                    'share_my_data'
                )
                ->first();

            if (!$privacySettings) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '6',
                        'error_text' => 'Privacy settings not found.'
                    ]
                ], 404);
            }

            // Format privacy settings
            $settings = [
                'message_privacy' => (string) ($privacySettings->message_privacy ?? '0'),
                'follow_privacy' => (string) ($privacySettings->follow_privacy ?? '0'),
                'birth_privacy' => (string) ($privacySettings->birth_privacy ?? '0'),
                'status' => (string) ($privacySettings->status ?? '0'),
                'visit_privacy' => (string) ($privacySettings->visit_privacy ?? '0'),
                'post_privacy' => (string) ($privacySettings->post_privacy ?? '0'),
                'confirm_followers' => (string) ($privacySettings->confirm_followers ?? '0'),
                'show_activities_privacy' => (string) ($privacySettings->show_activities_privacy ?? '0'),
                'share_my_location' => (string) ($privacySettings->share_my_location ?? '0'),
                'share_my_data' => (string) ($privacySettings->share_my_data ?? '0'),
            ];

            // Add human-readable labels
            $settings['message_privacy_text'] = $this->getMessagePrivacyText($settings['message_privacy']);
            $settings['follow_privacy_text'] = $this->getFollowPrivacyText($settings['follow_privacy']);
            $settings['birth_privacy_text'] = $this->getBirthPrivacyText($settings['birth_privacy']);
            $settings['status_text'] = $settings['status'] === '1' ? 'Online' : 'Offline';
            $settings['visit_privacy_text'] = $settings['visit_privacy'] === '1' ? 'Hidden' : 'Visible';

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'privacy_settings' => $settings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to get privacy settings: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Update privacy settings (mimics WoWonder update_user_data.php with type=privacy_settings)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePrivacySettings(Request $request): JsonResponse
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
            'message_privacy' => 'nullable|in:0,1',
            'follow_privacy' => 'nullable|in:0,1',
            'birth_privacy' => 'nullable|in:0,1,2',
            'status' => 'nullable|in:0,1',
            'visit_privacy' => 'nullable|in:0,1',
            'post_privacy' => 'nullable|string',
            'confirm_followers' => 'nullable|in:0,1',
            'show_activities_privacy' => 'nullable|in:0,1',
            'share_my_location' => 'nullable|in:0,1',
            'share_my_data' => 'nullable|in:0,1',
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

            $updateData = [];

            // Message Privacy: 0 = Everyone, 1 = Friends Only
            if ($request->has('message_privacy')) {
                $updateData['message_privacy'] = $request->input('message_privacy');
            }

            // Follow Privacy: 0 = Everyone can follow, 1 = No one can follow
            if ($request->has('follow_privacy')) {
                $updateData['follow_privacy'] = $request->input('follow_privacy');
            }

            // Birth Privacy: 0 = Everyone, 1 = Friends, 2 = Only Me
            if ($request->has('birth_privacy')) {
                $updateData['birth_privacy'] = $request->input('birth_privacy');
            }

            // Online Status: 0 = Offline, 1 = Online
            if ($request->has('status')) {
                $updateData['status'] = $request->input('status');
            }

            // Visit Privacy: 0 = Visible, 1 = Hidden
            if ($request->has('visit_privacy')) {
                $updateData['visit_privacy'] = $request->input('visit_privacy');
            }

            // Post Privacy: Default privacy for posts
            if ($request->has('post_privacy')) {
                $updateData['post_privacy'] = $request->input('post_privacy');
            }

            // Confirm Followers: 0 = Auto approve, 1 = Manual approval
            if ($request->has('confirm_followers')) {
                $updateData['confirm_followers'] = $request->input('confirm_followers');
            }

            // Show Activities Privacy: 0 = Show, 1 = Hide
            if ($request->has('show_activities_privacy')) {
                $updateData['show_activities_privacy'] = $request->input('show_activities_privacy');
            }

            // Share Location: 0 = No, 1 = Yes
            if ($request->has('share_my_location')) {
                $updateData['share_my_location'] = $request->input('share_my_location');
            }

            // Share Data: 0 = No, 1 = Yes
            if ($request->has('share_my_data')) {
                $updateData['share_my_data'] = $request->input('share_my_data');
            }

            if (empty($updateData)) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '8',
                        'error_text' => 'No privacy settings to update.'
                    ]
                ], 422);
            }

            // Update privacy settings
            DB::table('Wo_Users')->where('user_id', $tokenUserId)->update($updateData);

            // Get updated settings
            $updatedSettings = DB::table('Wo_Users')
                ->where('user_id', $tokenUserId)
                ->select(
                    'message_privacy',
                    'follow_privacy',
                    'birth_privacy',
                    'status',
                    'visit_privacy',
                    'post_privacy',
                    'confirm_followers',
                    'show_activities_privacy',
                    'share_my_location',
                    'share_my_data'
                )
                ->first();

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Privacy settings updated successfully',
                'privacy_settings' => [
                    'message_privacy' => (string) ($updatedSettings->message_privacy ?? '0'),
                    'follow_privacy' => (string) ($updatedSettings->follow_privacy ?? '0'),
                    'birth_privacy' => (string) ($updatedSettings->birth_privacy ?? '0'),
                    'status' => (string) ($updatedSettings->status ?? '0'),
                    'visit_privacy' => (string) ($updatedSettings->visit_privacy ?? '0'),
                    'post_privacy' => (string) ($updatedSettings->post_privacy ?? '0'),
                    'confirm_followers' => (string) ($updatedSettings->confirm_followers ?? '0'),
                    'show_activities_privacy' => (string) ($updatedSettings->show_activities_privacy ?? '0'),
                    'share_my_location' => (string) ($updatedSettings->share_my_location ?? '0'),
                    'share_my_data' => (string) ($updatedSettings->share_my_data ?? '0'),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '9',
                    'error_text' => 'Failed to update privacy settings: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get message privacy text
     */
    private function getMessagePrivacyText(string $value): string
    {
        return match($value) {
            '0' => 'Everyone',
            '1' => 'Friends Only',
            default => 'Everyone'
        };
    }

    /**
     * Get follow privacy text
     */
    private function getFollowPrivacyText(string $value): string
    {
        return match($value) {
            '0' => 'Everyone can follow',
            '1' => 'No one can follow',
            default => 'Everyone can follow'
        };
    }

    /**
     * Get birth privacy text
     */
    private function getBirthPrivacyText(string $value): string
    {
        return match($value) {
            '0' => 'Everyone',
            '1' => 'Friends Only',
            '2' => 'Only Me',
            default => 'Everyone'
        };
    }
}

