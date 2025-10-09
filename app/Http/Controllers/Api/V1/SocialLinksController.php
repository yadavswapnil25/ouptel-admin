<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SocialLinksController extends Controller
{
    /**
     * Get user's social links (mimics WoWonder profile settings)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getSocialLinks(Request $request): JsonResponse
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
            // Get user's social links
            $user = DB::table('Wo_Users')
                ->where('user_id', $tokenUserId)
                ->select(
                    'facebook',
                    'twitter',
                    'google',
                    'instagram',
                    'linkedin',
                    'youtube',
                    'vk'
                )
                ->first();

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

            $socialLinks = [
                'facebook' => $user->facebook ?? '',
                'twitter' => $user->twitter ?? '',
                'google' => $user->google ?? '',
                'instagram' => $user->instagram ?? '',
                'linkedin' => $user->linkedin ?? '',
                'youtube' => $user->youtube ?? '',
                'vk' => $user->vk ?? '',
            ];

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'social_links' => $socialLinks
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to get social links: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Update user's social links (mimics WoWonder profile settings)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateSocialLinks(Request $request): JsonResponse
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
            'facebook' => 'nullable|string|max:500',
            'twitter' => 'nullable|string|max:500',
            'google' => 'nullable|string|max:500',
            'instagram' => 'nullable|string|max:500',
            'linkedin' => 'nullable|string|max:500',
            'youtube' => 'nullable|string|max:500',
            'vk' => 'nullable|string|max:500',
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
            $errors = [];

            // Validate and prepare social links
            if ($request->has('facebook')) {
                $facebook = $request->input('facebook');
                if (!empty($facebook) && !$this->isValidUrl($facebook)) {
                    $errors[] = 'Facebook URL is invalid';
                } else {
                    $updateData['facebook'] = $facebook ?: '';
                }
            }

            if ($request->has('twitter')) {
                $twitter = $request->input('twitter');
                if (!empty($twitter) && !$this->isValidUrl($twitter)) {
                    $errors[] = 'Twitter URL is invalid';
                } else {
                    $updateData['twitter'] = $twitter ?: '';
                }
            }

            if ($request->has('google')) {
                $google = $request->input('google');
                if (!empty($google) && !$this->isValidUrl($google)) {
                    $errors[] = 'Google+ URL is invalid';
                } else {
                    $updateData['google'] = $google ?: '';
                }
            }

            if ($request->has('instagram')) {
                $instagram = $request->input('instagram');
                if (!empty($instagram) && !$this->isValidUrl($instagram)) {
                    $errors[] = 'Instagram URL is invalid';
                } else {
                    $updateData['instagram'] = $instagram ?: '';
                }
            }

            if ($request->has('linkedin')) {
                $linkedin = $request->input('linkedin');
                if (!empty($linkedin) && !$this->isValidUrl($linkedin)) {
                    $errors[] = 'LinkedIn URL is invalid';
                } else {
                    $updateData['linkedin'] = $linkedin ?: '';
                }
            }

            if ($request->has('youtube')) {
                $youtube = $request->input('youtube');
                if (!empty($youtube) && !$this->isValidUrl($youtube)) {
                    $errors[] = 'YouTube URL is invalid';
                } else {
                    $updateData['youtube'] = $youtube ?: '';
                }
            }

            if ($request->has('vk')) {
                $vk = $request->input('vk');
                if (!empty($vk) && !$this->isValidUrl($vk)) {
                    $errors[] = 'VKontakte URL is invalid';
                } else {
                    $updateData['vk'] = $vk ?: '';
                }
            }

            if (!empty($errors)) {
                return response()->json([
                    'api_status' => '500',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => $errors
                ], 422);
            }

            if (empty($updateData)) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '8',
                        'error_text' => 'No social links to update.'
                    ]
                ], 422);
            }

            // Update social links
            DB::table('Wo_Users')->where('user_id', $tokenUserId)->update($updateData);

            // Get updated social links
            $updatedUser = DB::table('Wo_Users')
                ->where('user_id', $tokenUserId)
                ->select(
                    'facebook',
                    'twitter',
                    'google',
                    'instagram',
                    'linkedin',
                    'youtube',
                    'vk'
                )
                ->first();

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Social links updated successfully',
                'social_links' => [
                    'facebook' => $updatedUser->facebook ?? '',
                    'twitter' => $updatedUser->twitter ?? '',
                    'google' => $updatedUser->google ?? '',
                    'instagram' => $updatedUser->instagram ?? '',
                    'linkedin' => $updatedUser->linkedin ?? '',
                    'youtube' => $updatedUser->youtube ?? '',
                    'vk' => $updatedUser->vk ?? '',
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '9',
                    'error_text' => 'Failed to update social links: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Validate URL format
     * 
     * @param string $url
     * @return bool
     */
    private function isValidUrl(string $url): bool
    {
        // Basic URL validation
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        // Check if URL starts with http:// or https://
        if (!preg_match('/^https?:\/\//i', $url)) {
            return false;
        }

        return true;
    }
}

