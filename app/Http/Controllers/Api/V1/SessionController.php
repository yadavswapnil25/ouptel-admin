<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SessionController extends Controller
{
    /**
     * Get all active sessions for the authenticated user (mimics WoWonder sessions.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getSessions(Request $request): JsonResponse
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
            // Get all sessions for this user
            $sessions = DB::table('Wo_AppsSessions')
                ->where('user_id', $tokenUserId)
                ->orderBy('time', 'desc')
                ->get();

            $formattedSessions = [];
            foreach ($sessions as $session) {
                $sessionData = [
                    'id' => (string) $session->id,
                    'user_id' => (string) $session->user_id,
                    'session_id' => $session->session_id,
                    'platform' => $this->detectPlatform($session->platform_type ?? ''),
                    'platform_details' => $session->platform_details ?? '',
                    'time' => (int) $session->time,
                    'time_text' => $this->timeElapsedString($session->time),
                    'created_at' => date('Y-m-d H:i:s', $session->time),
                    'is_current' => $session->session_id === $token,
                ];

                // Add device information if available
                if (!empty($session->device_id)) {
                    $sessionData['device_id'] = $session->device_id;
                }

                // Add IP address if available
                if (!empty($session->ip_address)) {
                    $sessionData['ip_address'] = $session->ip_address;
                }

                // Add browser/device info
                $sessionData['browser'] = $this->getBrowserInfo($session->platform_details ?? '');
                $sessionData['device'] = $this->getDeviceInfo($session->platform_details ?? '');

                $formattedSessions[] = $sessionData;
            }

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'data' => $formattedSessions,
                'total_sessions' => count($formattedSessions)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to get sessions: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Delete a specific session (mimics WoWonder sessions.php with type=delete)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteSession(Request $request): JsonResponse
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
            'id' => 'required|integer|min:1',
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
            $sessionId = $request->input('id');

            // Get the session to verify it belongs to this user
            $session = DB::table('Wo_AppsSessions')
                ->where('id', $sessionId)
                ->where('user_id', $tokenUserId)
                ->first();

            if (!$session) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '8',
                        'error_text' => 'Session not found or access denied.'
                    ]
                ], 404);
            }

            // Prevent deleting current session
            if ($session->session_id === $token) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '9',
                        'error_text' => 'Cannot delete current session. Please use logout instead.'
                    ]
                ], 422);
            }

            // Delete the session
            DB::table('Wo_AppsSessions')
                ->where('id', $sessionId)
                ->where('user_id', $tokenUserId)
                ->delete();

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Session deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '10',
                    'error_text' => 'Failed to delete session: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Delete all sessions except the current one
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteAllOtherSessions(Request $request): JsonResponse
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
            // Count sessions to be deleted
            $sessionsCount = DB::table('Wo_AppsSessions')
                ->where('user_id', $tokenUserId)
                ->where('session_id', '!=', $token)
                ->count();

            // Delete all sessions except the current one
            DB::table('Wo_AppsSessions')
                ->where('user_id', $tokenUserId)
                ->where('session_id', '!=', $token)
                ->delete();

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'All other sessions deleted successfully',
                'deleted_count' => $sessionsCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '10',
                    'error_text' => 'Failed to delete sessions: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Detect platform from platform_type or other identifiers
     */
    private function detectPlatform(string $platformType): string
    {
        $platformType = strtolower($platformType);
        
        if (str_contains($platformType, 'android')) {
            return 'Android';
        } elseif (str_contains($platformType, 'ios') || str_contains($platformType, 'iphone') || str_contains($platformType, 'ipad')) {
            return 'iOS';
        } elseif (str_contains($platformType, 'windows')) {
            return 'Windows';
        } elseif (str_contains($platformType, 'mac')) {
            return 'Mac';
        } elseif (str_contains($platformType, 'linux')) {
            return 'Linux';
        } elseif (str_contains($platformType, 'web')) {
            return 'Web';
        } else {
            return 'Unknown';
        }
    }

    /**
     * Extract browser information from user agent
     */
    private function getBrowserInfo(string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Unknown';
        }

        $userAgent = strtolower($userAgent);

        if (str_contains($userAgent, 'edge') || str_contains($userAgent, 'edg')) {
            return 'Microsoft Edge';
        } elseif (str_contains($userAgent, 'chrome')) {
            return 'Google Chrome';
        } elseif (str_contains($userAgent, 'safari') && !str_contains($userAgent, 'chrome')) {
            return 'Safari';
        } elseif (str_contains($userAgent, 'firefox')) {
            return 'Firefox';
        } elseif (str_contains($userAgent, 'opera') || str_contains($userAgent, 'opr')) {
            return 'Opera';
        } elseif (str_contains($userAgent, 'msie') || str_contains($userAgent, 'trident')) {
            return 'Internet Explorer';
        } else {
            return 'Unknown Browser';
        }
    }

    /**
     * Extract device information from user agent
     */
    private function getDeviceInfo(string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Unknown Device';
        }

        $userAgent = strtolower($userAgent);

        if (str_contains($userAgent, 'mobile') || str_contains($userAgent, 'android')) {
            if (str_contains($userAgent, 'android')) {
                return 'Android Phone';
            } elseif (str_contains($userAgent, 'iphone')) {
                return 'iPhone';
            } else {
                return 'Mobile Device';
            }
        } elseif (str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad')) {
            if (str_contains($userAgent, 'ipad')) {
                return 'iPad';
            } else {
                return 'Tablet';
            }
        } elseif (str_contains($userAgent, 'windows')) {
            return 'Windows PC';
        } elseif (str_contains($userAgent, 'macintosh') || str_contains($userAgent, 'mac os')) {
            return 'Mac';
        } elseif (str_contains($userAgent, 'linux')) {
            return 'Linux PC';
        } else {
            return 'Desktop';
        }
    }

    /**
     * Format time elapsed string
     */
    private function timeElapsedString(int $timestamp): string
    {
        $time = time() - $timestamp;
        
        if ($time < 60) return 'Just now';
        if ($time < 3600) return floor($time / 60) . ' minutes ago';
        if ($time < 86400) return floor($time / 3600) . ' hours ago';
        if ($time < 2592000) return floor($time / 86400) . ' days ago';
        if ($time < 31536000) return floor($time / 2592000) . ' months ago';
        return floor($time / 31536000) . ' years ago';
    }
}

