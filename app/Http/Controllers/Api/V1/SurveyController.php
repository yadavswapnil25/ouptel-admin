<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SurveyController extends Controller
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

    public function status(Request $request): JsonResponse
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

        $submitted = DB::table('Wo_UserSurveyResponses')
            ->where('user_id', $userId)
            ->exists();

        return response()->json([
            'api_status' => 200,
            'submitted' => $submitted,
        ]);
    }

    public function submit(Request $request): JsonResponse
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
            'struggling_with' => 'required|string|max:1000',
            'hear_about' => 'required|in:Facebook,instagram,Google Search,Twitter,Whatsapp,Other',
            'hear_about_other' => 'nullable|string|max:255',
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

        $hearAbout = (string) $request->input('hear_about');
        $hearAboutOther = trim((string) $request->input('hear_about_other', ''));
        if ($hearAbout !== 'Other') {
            $hearAboutOther = '';
        }

        DB::table('Wo_UserSurveyResponses')->updateOrInsert(
            ['user_id' => $userId],
            [
                'struggling_with' => trim((string) $request->input('struggling_with')),
                'hear_about' => $hearAbout,
                'hear_about_other' => $hearAboutOther !== '' ? $hearAboutOther : null,
                'submitted_at' => now(),
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'api_status' => 200,
            'message' => 'Survey submitted successfully',
            'submitted' => true,
        ]);
    }
}

