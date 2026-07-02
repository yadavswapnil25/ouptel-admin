<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class InvitationController extends Controller
{
    private function getTokenUserId(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');

        return $tokenUserId ? (string) $tokenUserId : null;
    }

    public function sendEmail(Request $request): JsonResponse
    {
        $userId = $this->getTokenUserId($request);
        if (!$userId) {
            return response()->json([
                'api_status' => 401,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - Invalid or missing token',
                ],
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => $validator->errors()->first(),
                ],
            ], 400);
        }

        $email = strtolower(trim((string) $request->input('email')));

        if (User::where('email', $email)->exists()) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 3,
                    'error_text' => 'This email is already registered',
                ],
            ], 400);
        }

        $user = User::where('user_id', $userId)->first();
        if (!$user) {
            return response()->json([
                'api_status' => 404,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'User not found',
                ],
            ], 404);
        }

        $appName = (string) (Setting::get('siteName') ?: config('app.name', 'Ouptel'));
        $frontendBase = rtrim((string) config('app.frontend_url', config('app.url', '')), '/');
        $siteUrl = $frontendBase !== '' ? $frontendBase : rtrim((string) config('app.url', ''), '/');
        $joinLink = $siteUrl . '/signup';
        $inviterName = $user->full_name ?: $user->username ?: $appName;
        $accentColor = (string) (Setting::get('btn_background_color') ?: '#2457d3');
        $subject = $appName . ' - You have been invited';
        $plainText = "Hi there,\n\n{$inviterName} invited you to join {$appName}.\n\nCreate your account: {$joinLink}\n\nIf you were not expecting this invitation, you can safely ignore this email.";

        try {
            Mail::send(
                'emails.invite',
                [
                    'appName' => $appName,
                    'inviterName' => $inviterName,
                    'joinLink' => $joinLink,
                    'accentColor' => $accentColor,
                ],
                function ($mail) use ($email, $subject, $plainText, $appName) {
                    $mail->to($email)
                        ->subject($subject)
                        ->text($plainText);

                    $fromEmail = Setting::get('siteEmail');
                    if ($fromEmail) {
                        $mail->from($fromEmail, $appName);
                    }
                }
            );
        } catch (\Exception $e) {
            Log::error('Failed to send invite email: ' . $e->getMessage(), [
                'inviter_id' => $userId,
                'email' => $email,
            ]);

            return response()->json([
                'api_status' => 500,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Failed to send invitation email. Please try again.',
                ],
            ], 500);
        }

        return response()->json([
            'api_status' => 200,
            'message' => 'Invitation email sent successfully',
        ]);
    }
}
