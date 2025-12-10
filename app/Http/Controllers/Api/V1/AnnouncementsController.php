<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementsController extends Controller
{
    /**
     * Get home announcement (matching old API: Wo_GetHomeAnnouncements)
     * Returns a random active announcement that the user hasn't viewed yet
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getHomeAnnouncement(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        try {
            // Get a random active announcement that user hasn't viewed yet
            // Matching old API: Wo_GetHomeAnnouncements()
            $viewedAnnouncementIds = DB::table('Wo_Announcement_Views')
                ->where('user_id', $tokenUserId)
                ->pluck('announcement_id')
                ->toArray();

            $announcement = DB::table('Wo_Announcement')
                ->where('active', '1')
                ->whereNotIn('id', $viewedAnnouncementIds)
                ->inRandomOrder()
                ->first();

            if (!$announcement) {
                return response()->json([
                    'api_status' => 200,
                    'announcement' => null
                ]);
            }

            // Format announcement data (matching old API structure)
            $announcementData = [
                'id' => $announcement->id,
                'text' => $announcement->text,
                'text_decode' => strip_tags($announcement->text), // Plain text version
                'time' => $announcement->time,
                'time_text' => $this->getTimeElapsedString($announcement->time),
                'active' => $announcement->active,
            ];

            return response()->json([
                'api_status' => 200,
                'announcement' => $announcementData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 500,
                    'error_text' => 'Failed to get announcement: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Mark announcement as viewed
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function markAsViewed(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        // Validate announcement_id
        $request->validate([
            'announcement_id' => 'required|integer|exists:Wo_Announcement,id',
        ]);

        try {
            $announcementId = $request->input('announcement_id');

            // Check if already viewed
            $alreadyViewed = DB::table('Wo_Announcement_Views')
                ->where('user_id', $tokenUserId)
                ->where('announcement_id', $announcementId)
                ->exists();

            if (!$alreadyViewed) {
                // Mark as viewed (matching old API: Wo_RegisterAnnouncementView)
                DB::table('Wo_Announcement_Views')->insert([
                    'user_id' => $tokenUserId,
                    'announcement_id' => $announcementId,
                ]);
            }

            return response()->json([
                'api_status' => 200,
                'message' => 'Announcement marked as viewed'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 500,
                    'error_text' => 'Failed to mark announcement as viewed: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get all active announcements (optional endpoint)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllActive(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        try {
            $announcements = DB::table('Wo_Announcement')
                ->where('active', '1')
                ->orderBy('time', 'desc')
                ->get()
                ->map(function ($announcement) use ($tokenUserId) {
                    // Check if user has viewed this announcement
                    $viewed = DB::table('Wo_Announcement_Views')
                        ->where('user_id', $tokenUserId)
                        ->where('announcement_id', $announcement->id)
                        ->exists();

                    return [
                        'id' => $announcement->id,
                        'text' => $announcement->text,
                        'text_decode' => strip_tags($announcement->text),
                        'time' => $announcement->time,
                        'time_text' => $this->getTimeElapsedString($announcement->time),
                        'active' => $announcement->active,
                        'viewed' => $viewed,
                    ];
                });

            return response()->json([
                'api_status' => 200,
                'announcements' => $announcements
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 500,
                    'error_text' => 'Failed to get announcements: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get time elapsed string (matching old API: Wo_Time_Elapsed_String)
     * 
     * @param int $timestamp
     * @return string
     */
    private function getTimeElapsedString(int $timestamp): string
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

