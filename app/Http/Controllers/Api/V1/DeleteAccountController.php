<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

            // Begin transaction for safe deletion
            DB::beginTransaction();

            try {
                // Delete user data in proper order
                $this->deleteUserData($tokenUserId);

                // Finally, delete the user account
                DB::table('Wo_Users')->where('user_id', $tokenUserId)->delete();

                DB::commit();

                return response()->json([
                    'api_status' => '200',
                    'api_text' => 'success',
                    'api_version' => '1.0',
                    'message' => 'User account successfully deleted.'
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

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

            // Schedule deletion for 30 days later (grace period)
            $deletionDate = time() + (30 * 24 * 60 * 60); // 30 days

            DB::table('Wo_Users')->where('user_id', $tokenUserId)->update([
                'active' => '2', // Mark as pending deletion
                'deletion_request_time' => time(),
                'deletion_reason' => $request->input('reason', '')
            ]);

            // Log out all sessions
            DB::table('Wo_AppsSessions')->where('user_id', $tokenUserId)->delete();

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Account deletion requested. Your account will be deleted in 30 days. You can cancel this request by logging in again.',
                'deletion_date' => date('Y-m-d H:i:s', $deletionDate)
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
     * Delete all user-related data
     */
    private function deleteUserData(int $userId): void
    {
        // Delete sessions
        DB::table('Wo_AppsSessions')->where('user_id', $userId)->delete();

        // Delete posts
        DB::table('Wo_Posts')->where('user_id', $userId)->delete();

        // Delete comments
        DB::table('Wo_Comments')->where('user_id', $userId)->delete();

        // Delete reactions
        DB::table('Wo_PostReactions')->where('user_id', $userId)->delete();

        // Delete followers/following
        DB::table('Wo_Followers')
            ->where('follower_id', $userId)
            ->orWhere('following_id', $userId)
            ->delete();

        // Delete friends
        DB::table('Wo_Friends')
            ->where('user_id', $userId)
            ->orWhere('friend_id', $userId)
            ->delete();

        // Delete blocks
        DB::table('Wo_Blocks')
            ->where('blocker', $userId)
            ->orWhere('blocked', $userId)
            ->delete();

        // Delete notifications
        DB::table('Wo_Notifications')
            ->where('notifier_id', $userId)
            ->orWhere('recipient_id', $userId)
            ->delete();

        // Delete addresses
        DB::table('Wo_UserAddress')->where('user_id', $userId)->delete();

        // Delete pages
        DB::table('Wo_Pages')->where('user_id', $userId)->delete();

        // Delete group memberships
        DB::table('Wo_GroupMembers')->where('user_id', $userId)->delete();

        // Delete page likes
        DB::table('Wo_PageLikes')->where('user_id', $userId)->delete();

        // Delete saved posts
        DB::table('Wo_SavedPosts')->where('user_id', $userId)->delete();

        // Delete messages (if table exists)
        try {
            DB::table('Wo_Messages')
                ->where('from_id', $userId)
                ->orWhere('to_id', $userId)
                ->delete();
        } catch (\Exception $e) {
            // Table might not exist
        }

        // Delete user files (avatar, cover, etc.)
        $user = User::where('user_id', $userId)->first();
        if ($user) {
            if ($user->avatar && !str_contains($user->avatar, 'd-avatar') && !str_contains($user->avatar, 'f-avatar')) {
                Storage::disk('public')->delete($user->avatar);
            }
            if ($user->cover && !str_contains($user->cover, 'cover.jpg')) {
                Storage::disk('public')->delete($user->cover);
            }
            if ($user->info_file) {
                Storage::disk('public')->delete($user->info_file);
            }
        }
    }
}

