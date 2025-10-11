<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DesignController extends Controller
{
    /**
     * Upload/Update profile picture (avatar) - mimics WoWonder update_profile_picture.php
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateAvatar(Request $request): JsonResponse
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

        // Validate file upload
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
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

            // Delete old avatar if it exists and is not default
            if ($user->avatar && !str_contains($user->avatar, 'd-avatar') && !str_contains($user->avatar, 'f-avatar')) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Upload new avatar
            $file = $request->file('image');
            $filename = 'avatar_' . $tokenUserId . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('upload/photos/' . date('Y/m'), $filename, 'public');

            // Update user avatar
            DB::table('Wo_Users')->where('user_id', $tokenUserId)->update([
                'avatar' => $path
            ]);

            // Get updated user data
            $updatedUser = User::where('user_id', $tokenUserId)->first();

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Avatar updated successfully',
                'avatar' => asset('storage/' . $path),
                'avatar_path' => $path
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to update avatar: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Upload/Update cover photo - mimics WoWonder update_profile_picture.php
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateCover(Request $request): JsonResponse
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

        // Validate file upload
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:20480', // 20MB max for cover
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

            // Delete old cover if it exists and is not default
            if ($user->cover && !str_contains($user->cover, 'cover.jpg')) {
                Storage::disk('public')->delete($user->cover);
            }

            // Upload new cover
            $file = $request->file('image');
            $filename = 'cover_' . $tokenUserId . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('upload/photos/' . date('Y/m'), $filename, 'public');

            // Update user cover
            DB::table('Wo_Users')->where('user_id', $tokenUserId)->update([
                'cover' => $path
            ]);

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Cover photo updated successfully',
                'cover' => asset('storage/' . $path),
                'cover_path' => $path
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to update cover: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Reset avatar to default - mimics WoWonder reset_avatar.php
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function resetAvatar(Request $request): JsonResponse
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

            // Delete old avatar if it exists and is not default
            if ($user->avatar && !str_contains($user->avatar, 'd-avatar') && !str_contains($user->avatar, 'f-avatar')) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Set default avatar based on gender
            $defaultAvatar = $user->gender === 'female' ? 'upload/photos/f-avatar.jpg' : 'upload/photos/d-avatar.jpg';

            // Update user avatar to default
            DB::table('Wo_Users')->where('user_id', $tokenUserId)->update([
                'avatar' => $defaultAvatar
            ]);

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Avatar reset to default successfully',
                'avatar' => asset('storage/' . $defaultAvatar),
                'avatar_path' => $defaultAvatar
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to reset avatar: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Reset cover to default
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function resetCover(Request $request): JsonResponse
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

            // Delete old cover if it exists and is not default
            if ($user->cover && !str_contains($user->cover, 'cover.jpg')) {
                Storage::disk('public')->delete($user->cover);
            }

            // Set default cover
            $defaultCover = 'upload/photos/cover.jpg';

            // Update user cover to default
            DB::table('Wo_Users')->where('user_id', $tokenUserId)->update([
                'cover' => $defaultCover
            ]);

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Cover photo reset to default successfully',
                'cover' => asset('storage/' . $defaultCover),
                'cover_path' => $defaultCover
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to reset cover: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get current design settings (avatar and cover)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getDesignSettings(Request $request): JsonResponse
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
            $user = DB::table('Wo_Users')
                ->where('user_id', $tokenUserId)
                ->select('avatar', 'cover', 'gender')
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

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'design_settings' => [
                    'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'avatar_path' => $user->avatar ?? '',
                    'cover' => $user->cover ? asset('storage/' . $user->cover) : null,
                    'cover_path' => $user->cover ?? '',
                    'is_avatar_default' => str_contains($user->avatar ?? '', 'd-avatar') || str_contains($user->avatar ?? '', 'f-avatar'),
                    'is_cover_default' => str_contains($user->cover ?? '', 'cover.jpg')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to get design settings: ' . $e->getMessage()
                ]
            ], 500);
        }
    }
}

