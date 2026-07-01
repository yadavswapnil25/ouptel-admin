<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class DeleteAccountController extends Controller
{
    /**
     * Delete user account (mimics WoWonder delete-user.php)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteAccount(Request $request): JsonResponse
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
            'confirmation' => 'nullable|string|in:DELETE,delete', // Optional extra confirmation
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
            if (!Hash::check($request->input('password'), $user->password)) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '8',
                        'error_text' => 'Password is incorrect. Account deletion cancelled.'
                    ]
                ], 422);
            }

            // Cannot delete admin accounts
            if ($user->admin == 1) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '9',
                        'error_text' => 'Cannot delete admin accounts.'
                    ]
                ], 403);
            }

            // Soft-delete flow: user request only marks account pending deletion.
            // Permanent deletion is done by admin confirmation.
            DB::table('Wo_Users')->where('user_id', $tokenUserId)->update([
                'active' => '2',
            ]);

            // End all sessions immediately.
            DB::table('Wo_AppsSessions')->where('user_id', $tokenUserId)->delete();

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Account deletion request submitted. Your account is temporarily deactivated and pending admin confirmation for permanent deletion.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '10',
                    'error_text' => 'Failed to delete account: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Request account deletion (soft delete or schedule deletion)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function requestAccountDeletion(Request $request): JsonResponse
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
            'reason' => 'nullable|string|max:1000',
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

            // Verify password
            if (!Hash::check($request->input('password'), $user->password)) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '8',
                        'error_text' => 'Password is incorrect.'
                    ]
                ], 422);
            }

            // Mark account as pending deletion (temporary deactivation)
            DB::table('Wo_Users')->where('user_id', $tokenUserId)->update([
                'active' => '2',
            ]);

            // Log out all sessions
            DB::table('Wo_AppsSessions')->where('user_id', $tokenUserId)->delete();

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Account deletion request submitted. Your account is temporarily deactivated and pending admin confirmation for permanent deletion.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '10',
                    'error_text' => 'Failed to request account deletion: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Request OTP for account deletion
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function requestDeletionOtp(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => ['error_text' => 'No session sent.']
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => ['error_text' => 'Session id is wrong.']
            ], 401);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'confirmation' => 'nullable|string|in:DELETE,delete',
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
                    'errors' => ['error_text' => 'User not found.']
                ], 404);
            }

            // Verify password
            if (!Hash::check($request->input('password'), $user->password)) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => ['error_text' => 'Password is incorrect.']
                ], 422);
            }

            $hasPendingRequest = DB::table('Wo_AccountDeletionRequests')
                ->where('user_id', $tokenUserId)
                ->where('status', 'pending')
                ->exists();

            if ($hasPendingRequest || (string) $user->active === '2') {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => ['error_text' => 'An account deletion request is already pending for this account.']
                ], 409);
            }

            // Generate 6-digit OTP
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Store OTP in cache with 10-minute expiry
            cache()->put('deletion_otp_' . $tokenUserId, $otp, now()->addMinutes(10));

            // Send OTP email (same template style as signup verification)
            $appName = config('app.name', 'OUPTEL');
            $subject = $appName . ' - Account Deletion Verification Code';
            $plainText = "Your {$appName} account deletion verification code is: {$otp}\n\nThis code expires in 10 minutes. Do not share it with anyone.";
            Mail::send('emails.account-deletion-verification-code', ['code' => $otp, 'appName' => $appName], function ($message) use ($user, $subject, $plainText) {
                $message->to($user->email)
                    ->subject($subject)
                    ->text($plainText);
            });

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'OTP sent to your email address.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => ['error_text' => 'Failed to send OTP: ' . $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Delete account with OTP verification
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteAccountWithOtp(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => ['error_text' => 'No session sent.']
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => ['error_text' => 'Session id is wrong.']
            ], 401);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6',
            'deletion_reason' => 'nullable|string',
            'deletion_reason_other' => 'nullable|string|max:1000',
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
            // Verify OTP
            $cachedOtp = cache()->get('deletion_otp_' . $tokenUserId);
            if (!$cachedOtp || $cachedOtp !== $request->input('otp')) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => ['error_text' => 'Invalid or expired OTP.']
                ], 422);
            }

            // Clear OTP from cache
            cache()->forget('deletion_otp_' . $tokenUserId);

            // Get user data
            $user = User::where('user_id', $tokenUserId)->first();
            if (!$user) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => ['error_text' => 'User not found.']
                ], 404);
            }

            // Cannot delete admin accounts
            if ($user->admin == 1) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => ['error_text' => 'Cannot delete admin accounts.']
                ], 403);
            }

            $hasPendingRequest = DB::table('Wo_AccountDeletionRequests')
                ->where('user_id', $tokenUserId)
                ->where('status', 'pending')
                ->exists();

            if ($hasPendingRequest || (string) $user->active === '2') {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => ['error_text' => 'An account deletion request is already pending for this user.']
                ], 409);
            }

            DB::transaction(function () use ($tokenUserId, $request) {
                DB::table('Wo_AccountDeletionRequests')->insert([
                    'user_id' => $tokenUserId,
                    'deletion_reason' => $request->input('deletion_reason'),
                    'deletion_reason_other' => $request->input('deletion_reason_other'),
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('Wo_Users')->where('user_id', $tokenUserId)->update([
                    'active' => '2',
                ]);

                DB::table('Wo_AppsSessions')->where('user_id', $tokenUserId)->delete();
            });

            // Send confirmation email
            \Mail::raw("Your account deletion request has been submitted and verified. Our admin team will review and process your request shortly.", function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Account Deletion Request Confirmed')
                    ->from(config('mail.from.address'));
            });

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Account deletion request submitted successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => ['error_text' => 'Failed to delete account: ' . $e->getMessage()]
            ], 500);
        }
    }

}

