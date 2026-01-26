<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AccountVerificationController extends Controller
{
    /**
     * Get available ID proof types and badge types
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getVerificationOptions(Request $request): JsonResponse
    {
        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'data' => [
                'id_proof_types' => array_map(function ($key, $value) {
                    return ['key' => $key, 'name' => $value];
                }, array_keys(VerificationRequest::ID_PROOF_TYPES), VerificationRequest::ID_PROOF_TYPES),
                'badge_types' => [
                    [
                        'key' => 'blue',
                        'name' => 'Blue Badge',
                        'description' => 'For all regular users who want to verify their identity.',
                    ],
                    [
                        'key' => 'golden',
                        'name' => 'Golden Badge',
                        'description' => 'For VIPs, Celebrities and Well Known People only.',
                    ],
                ],
                'rejection_reasons' => array_map(function ($key, $value) {
                    return ['key' => $key, 'message' => $value];
                }, array_keys(VerificationRequest::REJECTION_REASONS), VerificationRequest::REJECTION_REASONS),
            ]
        ]);
    }

    /**
     * Submit account verification request
     * 
     * Endpoint: POST /api/v1/verification/submit
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function submit(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authResult = $this->authenticateUser($request);
        if ($authResult['error']) {
            return response()->json($authResult['response'], $authResult['status']);
        }
        $tokenUserId = $authResult['user_id'];

        // Validate request
        $validator = Validator::make($request->all(), [
            'id_proof_type' => 'required|string|in:' . implode(',', array_keys(VerificationRequest::ID_PROOF_TYPES)),
            'id_proof_number' => 'required|string|max:100',
            'id_proof_front_image' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            'id_proof_back_image' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            'badge_type' => 'required|string|in:blue,golden',
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
        $user = User::where('user_id', $tokenUserId)->first();
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

        // Check if user already has a verified badge
        if ($user->verified === '1') {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 8,
                    'error_text' => 'Your account is already verified.'
                ]
            ], 400);
        }

        // Check if user has a pending verification request
        $pendingRequest = VerificationRequest::where('user_id', $tokenUserId)
            ->whereNotNull('badge_type')
            ->where('status', VerificationRequest::STATUS_PENDING)
            ->first();

        if ($pendingRequest) {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 9,
                    'error_text' => 'You already have a pending verification request. Please wait for admin review.'
                ]
            ], 400);
        }

        try {
            // Upload front image
            $frontImage = $request->file('id_proof_front_image');
            $frontFilename = 'verification_front_' . $tokenUserId . '_' . time() . '.' . $frontImage->getClientOriginalExtension();
            $frontPath = $frontImage->storeAs('upload/verification/' . date('Y/m'), $frontFilename, 'public');

            // Upload back image
            $backImage = $request->file('id_proof_back_image');
            $backFilename = 'verification_back_' . $tokenUserId . '_' . time() . '.' . $backImage->getClientOriginalExtension();
            $backPath = $backImage->storeAs('upload/verification/' . date('Y/m'), $backFilename, 'public');

            // Create verification request
            $verification = VerificationRequest::create([
                'user_id' => $tokenUserId,
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'type' => 'User',
                'id_proof_type' => $request->input('id_proof_type'),
                'id_proof_number' => $request->input('id_proof_number'),
                'id_proof_front_image' => $frontPath,
                'id_proof_back_image' => $backPath,
                'badge_type' => $request->input('badge_type'),
                'status' => VerificationRequest::STATUS_PENDING,
                'submitted_at' => now(),
                'seen' => 0,
            ]);

            return response()->json([
                'api_status' => 200,
                'api_text' => 'success',
                'message' => 'Verification request submitted successfully. Admin will review and verify your account.',
                'data' => [
                    'verification_id' => $verification->id,
                    'badge_type' => $verification->badge_type,
                    'status' => $verification->status,
                    'submitted_at' => $verification->submitted_at?->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 500,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 10,
                    'error_text' => 'Failed to submit verification request: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get current verification status
     * 
     * Endpoint: GET /api/v1/verification/status
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatus(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authResult = $this->authenticateUser($request);
        if ($authResult['error']) {
            return response()->json($authResult['response'], $authResult['status']);
        }
        $tokenUserId = $authResult['user_id'];

        // Check if user exists
        $user = User::where('user_id', $tokenUserId)->first();
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

        // Get the latest verification request
        $verification = VerificationRequest::where('user_id', $tokenUserId)
            ->whereNotNull('badge_type')
            ->orderBy('id', 'desc')
            ->first();

        // Get user's current verification status
        $isVerified = $user->verified === '1';
        
        // Determine badge type from user table (if verified)
        // Note: You may need to add a 'badge_type' column to Wo_Users table
        $currentBadgeType = null;
        if ($isVerified && $verification && $verification->status === VerificationRequest::STATUS_APPROVED) {
            $currentBadgeType = $verification->badge_type;
        }

        $response = [
            'api_status' => 200,
            'api_text' => 'success',
            'data' => [
                'is_verified' => $isVerified,
                'current_badge_type' => $currentBadgeType,
                'verification_request' => null,
            ]
        ];

        if ($verification) {
            $response['data']['verification_request'] = [
                'id' => $verification->id,
                'id_proof_type' => $verification->id_proof_type,
                'id_proof_type_name' => $verification->id_proof_type_name,
                'badge_type' => $verification->badge_type,
                'badge_type_name' => $verification->badge_type_name,
                'status' => $verification->status,
                'status_name' => $verification->status_name,
                'rejection_reason' => $verification->rejection_reason,
                'rejection_reason_text' => $verification->rejection_reason_text,
                'submitted_at' => $verification->submitted_at?->toIso8601String(),
                'reviewed_at' => $verification->reviewed_at?->toIso8601String(),
            ];
        }

        return response()->json($response);
    }

    /**
     * Get verification history
     * 
     * Endpoint: GET /api/v1/verification/history
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getHistory(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authResult = $this->authenticateUser($request);
        if ($authResult['error']) {
            return response()->json($authResult['response'], $authResult['status']);
        }
        $tokenUserId = $authResult['user_id'];

        // Get all verification requests for this user
        $verifications = VerificationRequest::where('user_id', $tokenUserId)
            ->whereNotNull('badge_type')
            ->orderBy('id', 'desc')
            ->get();

        $history = $verifications->map(function ($verification) {
            return [
                'id' => $verification->id,
                'id_proof_type' => $verification->id_proof_type,
                'id_proof_type_name' => $verification->id_proof_type_name,
                'badge_type' => $verification->badge_type,
                'badge_type_name' => $verification->badge_type_name,
                'status' => $verification->status,
                'status_name' => $verification->status_name,
                'rejection_reason' => $verification->rejection_reason,
                'rejection_reason_text' => $verification->rejection_reason_text,
                'submitted_at' => $verification->submitted_at?->toIso8601String(),
                'reviewed_at' => $verification->reviewed_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'data' => [
                'total' => $verifications->count(),
                'history' => $history,
            ]
        ]);
    }

    /**
     * Resubmit verification after rejection
     * 
     * Endpoint: POST /api/v1/verification/resubmit
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function resubmit(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authResult = $this->authenticateUser($request);
        if ($authResult['error']) {
            return response()->json($authResult['response'], $authResult['status']);
        }
        $tokenUserId = $authResult['user_id'];

        // Check if user has a rejected verification request
        $rejectedRequest = VerificationRequest::where('user_id', $tokenUserId)
            ->whereNotNull('badge_type')
            ->where('status', VerificationRequest::STATUS_REJECTED)
            ->orderBy('id', 'desc')
            ->first();

        if (!$rejectedRequest) {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 11,
                    'error_text' => 'No rejected verification request found. Please submit a new request.'
                ]
            ], 400);
        }

        // Check if user already has a pending request
        $pendingRequest = VerificationRequest::where('user_id', $tokenUserId)
            ->whereNotNull('badge_type')
            ->where('status', VerificationRequest::STATUS_PENDING)
            ->first();

        if ($pendingRequest) {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 9,
                    'error_text' => 'You already have a pending verification request. Please wait for admin review.'
                ]
            ], 400);
        }

        // Use the submit method for actual submission
        return $this->submit($request);
    }

    /**
     * Cancel pending verification request
     * 
     * Endpoint: POST /api/v1/verification/cancel
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function cancel(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authResult = $this->authenticateUser($request);
        if ($authResult['error']) {
            return response()->json($authResult['response'], $authResult['status']);
        }
        $tokenUserId = $authResult['user_id'];

        // Find pending verification request
        $pendingRequest = VerificationRequest::where('user_id', $tokenUserId)
            ->whereNotNull('badge_type')
            ->where('status', VerificationRequest::STATUS_PENDING)
            ->first();

        if (!$pendingRequest) {
            return response()->json([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 12,
                    'error_text' => 'No pending verification request found.'
                ]
            ], 400);
        }

        try {
            // Delete uploaded images
            if ($pendingRequest->id_proof_front_image) {
                Storage::disk('public')->delete($pendingRequest->id_proof_front_image);
            }
            if ($pendingRequest->id_proof_back_image) {
                Storage::disk('public')->delete($pendingRequest->id_proof_back_image);
            }

            // Delete the request
            $pendingRequest->delete();

            return response()->json([
                'api_status' => 200,
                'api_text' => 'success',
                'message' => 'Verification request cancelled successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 500,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 10,
                    'error_text' => 'Failed to cancel verification request: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get user's badge information (public endpoint for viewing profiles)
     * 
     * Endpoint: GET /api/v1/users/{userId}/badge
     * 
     * @param Request $request
     * @param string $userId
     * @return JsonResponse
     */
    public function getUserBadge(Request $request, string $userId): JsonResponse
    {
        $user = User::where('user_id', $userId)->first();
        
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

        $isVerified = $user->verified === '1';
        $badgeType = null;

        if ($isVerified) {
            // Get the approved verification to determine badge type
            $approvedVerification = VerificationRequest::where('user_id', $userId)
                ->whereNotNull('badge_type')
                ->where('status', VerificationRequest::STATUS_APPROVED)
                ->orderBy('reviewed_at', 'desc')
                ->first();

            $badgeType = $approvedVerification?->badge_type;
        }

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'data' => [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'is_verified' => $isVerified,
                'badge_type' => $badgeType,
                'badge_info' => $badgeType ? [
                    'type' => $badgeType,
                    'name' => $badgeType === 'golden' ? 'Golden Badge' : 'Blue Badge',
                    'description' => $badgeType === 'golden' 
                        ? 'This user is a verified VIP, Celebrity or Well Known Person.' 
                        : 'This user has verified their identity.',
                ] : null,
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

