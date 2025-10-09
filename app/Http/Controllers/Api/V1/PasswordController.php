<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class PasswordController extends Controller
{
    /**
     * Change user password (mimics WoWonder update_user_data.php with type=password_settings)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword(Request $request): JsonResponse
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
            'current_password' => 'required|string|min:1',
            'new_password' => 'required|string|min:6',
            'repeat_new_password' => 'required|string|min:6',
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

            $errors = [];

            // Verify current password
            if (!Hash::check($request->input('current_password'), $user->password)) {
                $errors[] = 'Current password is incorrect';
            }

            // Check if new passwords match
            if ($request->input('new_password') !== $request->input('repeat_new_password')) {
                $errors[] = 'New passwords do not match';
            }

            // Check password length
            if (strlen($request->input('new_password')) < 6) {
                $errors[] = 'Password must be at least 6 characters long';
            }

            // Check if new password is same as current password
            if (Hash::check($request->input('new_password'), $user->password)) {
                $errors[] = 'New password cannot be the same as current password';
            }

            if (!empty($errors)) {
                return response()->json([
                    'api_status' => '500',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => $errors
                ], 422);
            }

            // Update password
            $newPasswordHash = Hash::make($request->input('new_password'));
            DB::table('Wo_Users')
                ->where('user_id', $tokenUserId)
                ->update(['password' => $newPasswordHash]);

            // Log out all other sessions except the current one (security feature)
            DB::table('Wo_AppsSessions')
                ->where('user_id', $tokenUserId)
                ->where('session_id', '!=', $token)
                ->delete();

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Password changed successfully. All other sessions have been logged out.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '9',
                    'error_text' => 'Failed to change password: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Verify current password (helper endpoint for UI validation)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyCurrentPassword(Request $request): JsonResponse
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
            'password' => 'required|string',
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

            // Verify password
            $isValid = Hash::check($request->input('password'), $user->password);

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'is_valid' => $isValid,
                'message' => $isValid ? 'Password is correct' : 'Password is incorrect'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '9',
                    'error_text' => 'Failed to verify password: ' . $e->getMessage()
                ]
            ], 500);
        }
    }
}

