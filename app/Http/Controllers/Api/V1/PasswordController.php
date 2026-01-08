<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

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

    /**
     * Request password reset (Forgot Password)
     * User provides email or username, system generates reset token and sends email
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email|max:100',
            'username' => 'nullable|string|max:32',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => $validator->errors()->all()
            ], 422);
        }

        $email = $request->input('email');
        $username = $request->input('username');

        if (empty($email) && empty($username)) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '10',
                    'error_text' => 'Email or username is required.'
                ]
            ], 422);
        }

        try {
            // Find user by email or username
            $query = User::query();
            if (!empty($email)) {
                $query->where('email', $email);
            } else {
                $query->where('username', $username);
            }

            $user = $query->first();

            // Always return success message for security (don't reveal if user exists)
            if (!$user) {
                return response()->json([
                    'api_status' => '200',
                    'api_text' => 'success',
                    'api_version' => '1.0',
                    'message' => 'If the email/username exists, a password reset link has been sent.'
                ]);
            }

            // Check if user is active
            if ($user->active === '2') {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '11',
                        'error_text' => 'Account is banned. Cannot reset password.'
                    ]
                ], 403);
            }

            // Generate reset token
            $resetToken = Str::random(64);
            $expiresAt = time() + (60 * 60); // Token expires in 1 hour

            // Store reset token in database
            // Check if password reset table exists, otherwise use a simple approach
            if (DB::getSchemaBuilder()->hasTable('Wo_PasswordReset')) {
                // Delete any existing reset tokens for this user
                DB::table('Wo_PasswordReset')
                    ->where('user_id', $user->user_id)
                    ->delete();

                // Insert new reset token
                DB::table('Wo_PasswordReset')->insert([
                    'user_id' => $user->user_id,
                    'email' => $user->email,
                    'token' => $resetToken,
                    'created_at' => time(),
                    'expires_at' => $expiresAt,
                ]);
            } else {
                // Fallback: Store token in user table if reset table doesn't exist
                // Check if password_reset_token column exists
                if (DB::getSchemaBuilder()->hasColumn('Wo_Users', 'password_reset_token')) {
                    DB::table('Wo_Users')
                        ->where('user_id', $user->user_id)
                        ->update([
                            'password_reset_token' => $resetToken,
                            'password_reset_expires' => $expiresAt,
                        ]);
                } else {
                    // If no reset table and no column, log warning and continue
                    Log::warning('Password reset: No Wo_PasswordReset table and no password_reset_token column found. Token generated but not stored.', [
                        'user_id' => $user->user_id,
                        'token' => substr($resetToken, 0, 10) . '...',
                    ]);
                }
            }

            // Send password reset email
            $this->sendPasswordResetEmail($user, $resetToken);

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'If the email/username exists, a password reset link has been sent.',
                'data' => [
                    'email' => $this->maskEmail($user->email), // Mask email for privacy
                    'expires_in' => 3600, // 1 hour in seconds
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Password reset request failed: ' . $e->getMessage(), [
                'email' => $email,
                'username' => $username,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '12',
                    'error_text' => 'Failed to process password reset request. Please try again later.'
                ]
            ], 500);
        }
    }

    /**
     * Reset password using reset token
     * User provides reset token and new password
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function resetPassword(Request $request): JsonResponse
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|min:64|max:64',
            'password' => 'required|string|min:6|max:100',
            'confirm_password' => 'required|string|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => $validator->errors()->all()
            ], 422);
        }

        $token = $request->input('token');
        $newPassword = $request->input('password');

        try {
            $user = null;
            $resetRecord = null;

            // Check password reset table first
            if (DB::getSchemaBuilder()->hasTable('Wo_PasswordReset')) {
                $resetRecord = DB::table('Wo_PasswordReset')
                    ->where('token', $token)
                    ->where('expires_at', '>', time())
                    ->first();

                if ($resetRecord) {
                    $user = User::where('user_id', $resetRecord->user_id)->first();
                }
            } else {
                // Fallback: Check user table for reset token
                // Check if password_reset_token column exists
                if (DB::getSchemaBuilder()->hasColumn('Wo_Users', 'password_reset_token')) {
                    $user = User::where('password_reset_token', $token)
                        ->where('password_reset_expires', '>', time())
                        ->first();
                    // Set resetRecord to indicate token was found
                    if ($user) {
                        $resetRecord = (object)['token' => $token];
                    }
                }
            }

            if (!$user || !$resetRecord) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '13',
                        'error_text' => 'Invalid or expired reset token.'
                    ]
                ], 400);
            }

            // Check if user is active
            if ($user->active === '2') {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '11',
                        'error_text' => 'Account is banned. Cannot reset password.'
                    ]
                ], 403);
            }

            // Update password
            $newPasswordHash = Hash::make($newPassword);
            DB::table('Wo_Users')
                ->where('user_id', $user->user_id)
                ->update(['password' => $newPasswordHash]);

            // Delete reset token
            if (DB::getSchemaBuilder()->hasTable('Wo_PasswordReset')) {
                DB::table('Wo_PasswordReset')
                    ->where('token', $token)
                    ->delete();
            } else {
                // Clear reset token from user table if column exists
                if (DB::getSchemaBuilder()->hasColumn('Wo_Users', 'password_reset_token')) {
                    DB::table('Wo_Users')
                        ->where('user_id', $user->user_id)
                        ->update([
                            'password_reset_token' => null,
                            'password_reset_expires' => null,
                        ]);
                }
            }

            // Log out all sessions for security
            DB::table('Wo_AppsSessions')
                ->where('user_id', $user->user_id)
                ->delete();

            // Send confirmation email
            $this->sendPasswordResetConfirmationEmail($user);

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Password has been reset successfully. Please login with your new password.',
                'data' => [
                    'user_id' => $user->user_id,
                    'username' => $user->username,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Password reset failed: ' . $e->getMessage(), [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '14',
                    'error_text' => 'Failed to reset password. Please try again later.'
                ]
            ], 500);
        }
    }

    /**
     * Send password reset email
     * 
     * @param User $user
     * @param string $token
     * @return void
     */
    private function sendPasswordResetEmail(User $user, string $token): void
    {
        // In a real implementation, you would send an email with the reset link
        // For now, we'll just log the action
        $resetLink = url('/reset-password?token=' . $token);
        
        Log::info("Password reset email sent to user {$user->user_id} ({$user->email})", [
            'reset_link' => $resetLink,
            'token' => substr($token, 0, 10) . '...',
        ]);

        // TODO: Implement actual email sending
        // Example: Mail::to($user->email)->send(new PasswordResetMail($user, $resetLink));
    }

    /**
     * Send password reset confirmation email
     * 
     * @param User $user
     * @return void
     */
    private function sendPasswordResetConfirmationEmail(User $user): void
    {
        // In a real implementation, you would send a confirmation email
        // For now, we'll just log the action
        Log::info("Password reset confirmation email sent to user {$user->user_id} ({$user->email})");

        // TODO: Implement actual email sending
        // Example: Mail::to($user->email)->send(new PasswordResetConfirmationMail($user));
    }

    /**
     * Mask email address for privacy (e.g., u***@example.com)
     * 
     * @param string $email
     * @return string
     */
    private function maskEmail(string $email): string
    {
        if (empty($email)) {
            return '';
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $username = $parts[0];
        $domain = $parts[1];

        // Mask username (show first character, mask the rest)
        $maskedUsername = substr($username, 0, 1) . str_repeat('*', max(1, strlen($username) - 1));

        return $maskedUsername . '@' . $domain;
    }
}

