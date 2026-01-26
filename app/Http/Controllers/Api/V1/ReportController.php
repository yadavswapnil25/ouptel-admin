<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    /**
     * Report reasons matching WoWonder
     */
    private const REPORT_REASONS = [
        'r_spam' => 'Spam',
        'r_violence' => 'Violence',
        'r_harassment' => 'Harassment',
        'r_hate' => 'Hate Speech',
        'r_terrorism' => 'Terrorism',
        'r_nudity' => 'Nudity',
        'r_fake' => 'Fake Account',
        'r_other' => 'Other',
    ];

    /**
     * Report a post (mimics WoWonder Wo_ReportPost function)
     * 
     * Endpoint: POST /api/v1/posts/{postId}/report
     * 
     * @param Request $request
     * @param int $postId
     * @return JsonResponse
     */
    public function reportPost(Request $request, int $postId): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authResult = $this->authenticateUser($request);
        if ($authResult['error']) {
            return response()->json($authResult['response'], $authResult['status']);
        }
        $tokenUserId = $authResult['user_id'];

        // Validate request
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|in:' . implode(',', array_keys(self::REPORT_REASONS)),
            'text' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 7,
                    'error_text' => 'Validation failed',
                    'validation_errors' => $validator->errors()->all()
                ]
            ], 422);
        }

        // Check if post exists
        $post = Post::where('id', $postId)->orWhere('post_id', $postId)->first();
        if (!$post) {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Post not found.'
                ]
            ], 404);
        }

        // Check if user already reported this post
        $existingReport = Report::where('post_id', $post->id)
            ->where('user_id', $tokenUserId)
            ->first();

        if ($existingReport) {
            // Unreport (toggle behavior - matching WoWonder logic)
            $existingReport->delete();

            return response()->json([
                'api_status' => 200,
                'api_text' => 'success',
                'code' => 0,
                'message' => 'Post unreported successfully',
                'action' => 'unreport post',
                'is_reported' => false,
            ]);
        }

        // Create new report
        $report = Report::create([
            'post_id' => $post->id,
            'user_id' => $tokenUserId,
            'profile_id' => 0,
            'page_id' => 0,
            'group_id' => 0,
            'comment_id' => 0,
            'reason' => $request->input('reason', 'r_other'),
            'text' => $request->input('text', ''),
            'seen' => 0,
            'time' => time(),
        ]);

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'code' => 1,
            'message' => 'Post reported successfully',
            'action' => 'report post',
            'is_reported' => true,
            'report_id' => $report->id,
        ]);
    }

    /**
     * Report a comment (mimics WoWonder Wo_ReportPost function for comments)
     * 
     * Endpoint: POST /api/v1/comments/{commentId}/report
     * 
     * @param Request $request
     * @param int $commentId
     * @return JsonResponse
     */
    public function reportComment(Request $request, int $commentId): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authResult = $this->authenticateUser($request);
        if ($authResult['error']) {
            return response()->json($authResult['response'], $authResult['status']);
        }
        $tokenUserId = $authResult['user_id'];

        // Validate request
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|in:' . implode(',', array_keys(self::REPORT_REASONS)),
            'text' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 7,
                    'error_text' => 'Validation failed',
                    'validation_errors' => $validator->errors()->all()
                ]
            ], 422);
        }

        // Check if comment exists
        $comment = DB::table('Wo_Comments')->where('id', $commentId)->first();
        if (!$comment) {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Comment not found.'
                ]
            ], 404);
        }

        // Check if user already reported this comment
        $existingReport = Report::where('comment_id', $commentId)
            ->where('user_id', $tokenUserId)
            ->first();

        if ($existingReport) {
            // Unreport (toggle behavior - matching WoWonder logic)
            $existingReport->delete();

            return response()->json([
                'api_status' => 200,
                'api_text' => 'success',
                'code' => 0,
                'message' => 'Comment unreported successfully',
                'action' => 'unreport comment',
                'is_reported' => false,
            ]);
        }

        // Create new report
        $report = Report::create([
            'comment_id' => $commentId,
            'user_id' => $tokenUserId,
            'post_id' => 0,
            'profile_id' => 0,
            'page_id' => 0,
            'group_id' => 0,
            'reason' => $request->input('reason', 'r_other'),
            'text' => $request->input('text', ''),
            'seen' => 0,
            'time' => time(),
        ]);

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'code' => 1,
            'message' => 'Comment reported successfully',
            'action' => 'report comment',
            'is_reported' => true,
            'report_id' => $report->id,
        ]);
    }

    /**
     * Report a user/profile (mimics WoWonder Wo_ReportUser function)
     * 
     * Endpoint: POST /api/v1/users/{userId}/report
     * 
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     */
    public function reportUser(Request $request, int $userId): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authResult = $this->authenticateUser($request);
        if ($authResult['error']) {
            return response()->json($authResult['response'], $authResult['status']);
        }
        $tokenUserId = $authResult['user_id'];

        // Cannot report yourself
        if ($tokenUserId == $userId) {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 8,
                    'error_text' => 'You cannot report yourself.'
                ]
            ], 400);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|in:' . implode(',', array_keys(self::REPORT_REASONS)),
            'text' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 7,
                    'error_text' => 'Validation failed',
                    'validation_errors' => $validator->errors()->all()
                ]
            ], 422);
        }

        // Check if user exists
        $user = DB::table('Wo_Users')->where('user_id', $userId)->first();
        if (!$user) {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'User not found.'
                ]
            ], 404);
        }

        // Check if user already reported this profile
        $existingReport = Report::where('profile_id', $userId)
            ->where('user_id', $tokenUserId)
            ->first();

        if ($existingReport) {
            // Unreport (toggle behavior - matching WoWonder logic)
            $existingReport->delete();

            return response()->json([
                'api_status' => 200,
                'api_text' => 'success',
                'code' => 0,
                'message' => 'User unreported successfully',
                'action' => 'unreport user',
                'is_reported' => false,
            ]);
        }

        // Create new report
        $report = Report::create([
            'profile_id' => $userId,
            'user_id' => $tokenUserId,
            'post_id' => 0,
            'page_id' => 0,
            'group_id' => 0,
            'comment_id' => 0,
            'reason' => $request->input('reason', 'r_other'),
            'text' => $request->input('text', ''),
            'seen' => 0,
            'time' => time(),
        ]);

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'code' => 1,
            'message' => 'User reported successfully',
            'action' => 'report user',
            'is_reported' => true,
            'report_id' => $report->id,
        ]);
    }

    /**
     * Get available report reasons
     * 
     * Endpoint: GET /api/v1/reports/reasons
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getReportReasons(Request $request): JsonResponse
    {
        $reasons = [];
        foreach (self::REPORT_REASONS as $key => $label) {
            $reasons[] = [
                'key' => $key,
                'label' => $label,
            ];
        }

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'data' => [
                'reasons' => $reasons,
            ]
        ]);
    }

    /**
     * Check if a post is reported by the current user
     * 
     * Endpoint: GET /api/v1/posts/{postId}/report-status
     * 
     * @param Request $request
     * @param int $postId
     * @return JsonResponse
     */
    public function getPostReportStatus(Request $request, int $postId): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authResult = $this->authenticateUser($request);
        if ($authResult['error']) {
            return response()->json($authResult['response'], $authResult['status']);
        }
        $tokenUserId = $authResult['user_id'];

        // Check if post exists
        $post = Post::where('id', $postId)->orWhere('post_id', $postId)->first();
        if (!$post) {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Post not found.'
                ]
            ], 404);
        }

        // Check if user reported this post
        $report = Report::where('post_id', $post->id)
            ->where('user_id', $tokenUserId)
            ->first();

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'data' => [
                'post_id' => $post->id,
                'is_reported' => $report ? true : false,
                'report_id' => $report?->id,
                'reported_at' => $report?->time ? date('c', $report->time) : null,
            ]
        ]);
    }

    /**
     * Authenticate user from Authorization header
     * 
     * @param Request $request
     * @return array
     */
    private function authenticateUser(Request $request): array
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return [
                'error' => true,
                'status' => 401,
                'response' => [
                    'api_status' => 400,
                    'api_text' => 'failed',
                    'errors' => [
                        'error_id' => 3,
                        'error_text' => 'No user id sent.'
                    ]
                ]
            ];
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        
        if (!$tokenUserId) {
            return [
                'error' => true,
                'status' => 401,
                'response' => [
                    'api_status' => 400,
                    'api_text' => 'failed',
                    'errors' => [
                        'error_id' => 6,
                        'error_text' => 'Session id is wrong.'
                    ]
                ]
            ];
        }

        return [
            'error' => false,
            'user_id' => $tokenUserId
        ];
    }
}

