<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class StoriesController extends Controller
{
    /**
     * View all stories (mimics old API: requests.php?f=view_all_stories / get-stories.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function viewAllStories(Request $request): JsonResponse
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

        // Get limit (matching old API structure)
        $limit = (int) ($request->input('limit', $request->query('limit', 35)));
        $limit = max(1, min($limit, 50));

        // Get all stories from friends (matching old API: Wo_GetFriendsStatus)
        $stories = $this->getFriendsStories($tokenUserId, $limit);

        return response()->json([
            'api_status' => 200,
            'stories' => $stories
        ]);
    }

    /**
     * Get user stories (mimics old API: get-user-stories.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserStories(Request $request): JsonResponse
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

        $offset = (int) ($request->input('offset', $request->query('offset', 0)));
        $limit = (int) ($request->input('limit', $request->query('limit', 20)));
        $limit = max(1, min($limit, 50));

        // Get all stories grouped by user (matching old API: Wo_GetFriendsStatusAPI)
        $storiesData = $this->getFriendsStoriesGrouped($tokenUserId, $limit, $offset);

        return response()->json([
            'api_status' => 200,
            'stories' => $storiesData
        ]);
    }

    /**
     * Create story (mimics old API: create-story.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function create(Request $request): JsonResponse
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

        // Validate request (matching old API structure)
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            'file_type' => 'required|in:video,image',
            'story_title' => 'nullable|string|max:100',
            'story_description' => 'nullable|string|max:300',
            'cover' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $firstError = $errors->first();
            $errorCode = 3;
            if (str_contains($firstError, 'file')) $errorCode = 3;
            if (str_contains($firstError, 'file_type')) $errorCode = 6;
            if (str_contains($firstError, 'Title')) $errorCode = 4;
            if (str_contains($firstError, 'Description')) $errorCode = 5;
            
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => $errorCode,
                    'error_text' => $firstError
                ]
            ], 400);
        }

        // Additional validation matching old API
        if ($request->filled('story_title') && strlen($request->input('story_title')) > 100) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Title is so long'
                ]
            ], 400);
        }

        if ($request->filled('story_description') && strlen($request->input('story_description')) > 300) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Description is so long'
                ]
            ], 400);
        }

        $fileType = $request->input('file_type');
        $file = $request->file('file');

        // Validate file type
        if ($fileType == 'image') {
            $validator = Validator::make($request->all(), [
                'file' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'file' => 'required|mimes:mp4,m4v,avi,mpg,mov,webm|max:51200',
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 7,
                    'error_text' => 'Incorrect value for (file_type), allowed: video|image'
                ]
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Create story record
            $storyId = DB::table('Wo_UserStory')->insertGetId([
                'user_id' => $tokenUserId,
                'posted' => time(),
                'expire' => time() + (60 * 60 * 24), // 24 hours
                'title' => $request->input('story_title', ''),
                'description' => $request->input('story_description', ''),
            ]);

            // Handle file upload
            $filename = '';
            $thumbnail = '';

            if ($file) {
                $extension = $file->getClientOriginalExtension();
                $filename = 'stories/' . date('Y/m') . '/' . uniqid() . '_' . time() . '.' . $extension;
                Storage::disk('public')->put($filename, file_get_contents($file));
                $filename = $filename;
            }

            // Handle video thumbnail
            if ($fileType == 'video' && $request->hasFile('cover')) {
                $coverFile = $request->file('cover');
                $coverExtension = $coverFile->getClientOriginalExtension();
                $thumbnail = 'stories/thumbs/' . date('Y/m') . '/' . uniqid() . '_' . time() . '.' . $coverExtension;
                Storage::disk('public')->put($thumbnail, file_get_contents($coverFile));
            } elseif ($fileType == 'image') {
                // For images, use the image itself as thumbnail
                $thumbnail = $filename;
            }

            // Insert story media (check which table exists)
            if ($filename) {
                // Try Wo_UserStoryMedia first (matching old API: T_USER_STORY_MEDIA)
                $mediaTable = 'Wo_UserStoryMedia';
                if (!Schema::hasTable($mediaTable)) {
                    // Try alternative table name
                    if (Schema::hasTable('Wo_StoryMedia')) {
                        $mediaTable = 'Wo_StoryMedia';
                    } else {
                        // If no media table exists, store filename directly in story table
                        DB::rollBack();
                        return response()->json([
                            'api_status' => 400,
                            'errors' => [
                                'error_id' => 9,
                                'error_text' => 'Story media table does not exist'
                            ]
                        ], 500);
                    }
                }

                $mediaInsertData = [
                    'story_id' => $storyId,
                    'type' => $fileType,
                    'filename' => $filename,
                ];

                // Only add expire if column exists
                if (Schema::hasColumn($mediaTable, 'expire')) {
                    $mediaInsertData['expire'] = time() + (60 * 60 * 24);
                }

                DB::table($mediaTable)->insert($mediaInsertData);

                // Update story thumbnail if column exists
                if ($thumbnail && Schema::hasColumn('Wo_UserStory', 'thumbnail')) {
                    DB::table('Wo_UserStory')
                        ->where('id', $storyId)
                        ->update(['thumbnail' => $thumbnail]);
                }
            }

            DB::commit();

            return response()->json([
                'api_status' => 200,
                'story_id' => $storyId
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 8,
                    'error_text' => 'Something went wrong: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get story by ID (mimics old API: get_story_by_id.php)
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function getStoryById(Request $request, int $id): JsonResponse
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

        // Get story
        $story = DB::table('Wo_UserStory')->where('id', $id)->first();
        if (!$story) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Story not found'
                ]
            ], 404);
        }

        // Get story media (try Wo_UserStoryMedia first, matching old API: T_USER_STORY_MEDIA)
        $mediaTable = 'Wo_UserStoryMedia';
        if (!Schema::hasTable($mediaTable)) {
            if (Schema::hasTable('Wo_StoryMedia')) {
                $mediaTable = 'Wo_StoryMedia';
            }
        }

        $storyImages = [];
        $storyVideos = [];
        
        if (Schema::hasTable($mediaTable)) {
            $storyImages = DB::table($mediaTable)
                ->where('story_id', $id)
                ->where('type', 'image')
                ->get();

            $storyVideos = DB::table($mediaTable)
                ->where('story_id', $id)
                ->where('type', 'video')
                ->get();
        }

        // Get user data
        $user = DB::table('Wo_Users')->where('user_id', $story->user_id)->first();

        // Format story data
        $storyData = [
            'id' => $story->id,
            'user_id' => $story->user_id,
            'title' => $story->title ?? '',
            'description' => $story->description ?? '',
            'posted' => $story->posted,
            'expire' => $story->expire,
            'thumbnail' => $story->thumbnail ? asset('storage/' . $story->thumbnail) : ($user?->avatar ? asset('storage/' . $user->avatar) : null),
            'is_owner' => ($story->user_id == $tokenUserId),
            'user_data' => $user ? [
                'user_id' => $user->user_id,
                'username' => $user->username ?? 'Unknown',
                'name' => $user->name ?? $user->username ?? 'Unknown User',
                'avatar' => $user->avatar ?? '',
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'verified' => (bool) ($user->verified ?? false),
            ] : null,
            'images' => $storyImages->map(function($media) {
                return [
                    'filename' => $media->filename,
                    'url' => asset('storage/' . $media->filename),
                ];
            })->toArray(),
            'videos' => $storyVideos->map(function($media) {
                return [
                    'filename' => $media->filename,
                    'url' => asset('storage/' . $media->filename),
                ];
            })->toArray(),
        ];

        // Check if story is viewed and mark as viewed
        $isViewed = false;
        $viewCount = 0;
        
        if (Schema::hasTable('Wo_StorySeen')) {
            $isViewed = DB::table('Wo_StorySeen')
                ->where('story_id', $id)
                ->where('user_id', $tokenUserId)
                ->exists();

            if (!$isViewed && $story->user_id != $tokenUserId) {
                // Mark as viewed
                $seenInsertData = [
                    'story_id' => $id,
                    'user_id' => $tokenUserId,
                ];
                
                // Only add time if column exists
                if (Schema::hasColumn('Wo_StorySeen', 'time')) {
                    $seenInsertData['time'] = time();
                }
                
                DB::table('Wo_StorySeen')->insert($seenInsertData);

                // Create notification (if user is not viewing their own story)
                // Note: Notification system would be implemented separately
            }

            // Get view count
            $viewCount = DB::table('Wo_StorySeen')
                ->where('story_id', $id)
                ->where('user_id', '!=', $story->user_id)
                ->count();
        }

        $storyData['view_count'] = $viewCount;
        $storyData['is_viewed'] = $isViewed || $story->user_id == $tokenUserId;

        return response()->json([
            'api_status' => 200,
            'story' => $storyData
        ]);
    }

    /**
     * Delete story (mimics old API: delete-story.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(Request $request): JsonResponse
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

        // Validate request
        if (empty($request->input('story_id'))) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'story_id (POST) is missing'
                ]
            ], 400);
        }

        $storyId = (int) $request->input('story_id');

        // Check if story exists and belongs to user
        $story = DB::table('Wo_UserStory')
            ->where('id', $storyId)
            ->where('user_id', $tokenUserId)
            ->first();

        if (!$story) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Story not found or you do not have permission to delete it'
                ]
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Delete story media files
            // Get media table name
            $mediaTable = 'Wo_UserStoryMedia';
            if (!Schema::hasTable($mediaTable)) {
                if (Schema::hasTable('Wo_StoryMedia')) {
                    $mediaTable = 'Wo_StoryMedia';
                } else {
                    DB::rollBack();
                    return response()->json([
                        'api_status' => 400,
                        'errors' => [
                            'error_id' => 5,
                            'error_text' => 'Story media table does not exist'
                        ]
                    ], 500);
                }
            }

            $mediaFiles = DB::table($mediaTable)->where('story_id', $storyId)->get();
            foreach ($mediaFiles as $media) {
                if (Storage::disk('public')->exists($media->filename)) {
                    Storage::disk('public')->delete($media->filename);
                }
            }

            // Delete story media records
            DB::table($mediaTable)->where('story_id', $storyId)->delete();

            // Delete story views
            // Delete story seen records
            if (Schema::hasTable('Wo_StorySeen')) {
                DB::table('Wo_StorySeen')->where('story_id', $storyId)->delete();
            }

            // Delete story reactions
            DB::table('Wo_Reactions')->where('story_id', $storyId)->delete();

            // Delete story
            DB::table('Wo_UserStory')->where('id', $storyId)->delete();

            DB::commit();

            return response()->json([
                'api_status' => 200,
                'message' => "Story #$storyId successfully deleted."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Failed to delete story: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * React to story (mimics old API: react_story.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function react(Request $request): JsonResponse
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

        // Validate request
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|min:1',
            'reaction' => 'required|integer|in:1,2,3,4,5,6', // 1=Like, 2=Love, 3=Haha, 4=Wow, 5=Sad, 6=Angry
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'id, reaction can not be empty.'
                ]
            ], 400);
        }

        $storyId = (int) $request->input('id');
        $reaction = (int) $request->input('reaction');

        // Check if story exists
        $story = DB::table('Wo_UserStory')->where('id', $storyId)->first();
        if (!$story) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Story not found'
                ]
            ], 404);
        }

        // Check if already reacted
        $existingReaction = DB::table('Wo_Reactions')
            ->where('story_id', $storyId)
            ->where('user_id', $tokenUserId)
            ->first();

        if ($existingReaction) {
            // Remove reaction
            DB::table('Wo_Reactions')
                ->where('story_id', $storyId)
                ->where('user_id', $tokenUserId)
                ->delete();

            return response()->json([
                'api_status' => 200,
                'message' => 'reaction removed'
            ]);
        } else {
            // Add reaction (matching old API: no time column)
            $reactionInsertData = [
                'user_id' => $tokenUserId,
                'story_id' => $storyId,
                'reaction' => $reaction,
            ];
            
            // Only add time if column exists
            if (Schema::hasColumn('Wo_Reactions', 'time')) {
                $reactionInsertData['time'] = time();
            }
            
            DB::table('Wo_Reactions')->insert($reactionInsertData);

            return response()->json([
                'api_status' => 200,
                'message' => 'story reacted'
            ]);
        }
    }

    /**
     * Mute/Unmute story (mimics old API: mute_story.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function mute(Request $request): JsonResponse
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

        // Validate request
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|min:1',
            'type' => 'required|in:mute,unmute',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'user_id and type can not be empty'
                ]
            ], 400);
        }

        $userId = (int) $request->input('user_id');
        $type = $request->input('type');

        if ($userId == $tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'you cant mute your own story'
                ]
            ], 400);
        }

        // Check for mute story table (try common variations)
        $muteTable = null;
        $possibleTables = ['Wo_MuteStory', 'Wo_Mute_Story', 'Wo_UserMuteStory', 'Wo_StoryMute'];
        
        foreach ($possibleTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                $muteTable = $tableName;
                break;
            }
        }

        // If no mute table exists, return success but note that feature isn't available
        if (!$muteTable) {
            // Gracefully handle missing table - return success but indicate feature not available
            return response()->json([
                'api_status' => 200,
                'message' => $type == 'mute' ? 'user muted' : 'user unmuted',
                'note' => 'Story muting feature is not available in this system'
            ]);
        }

        if ($type == 'mute') {
            // Check if already muted
            $isMuted = DB::table($muteTable)
                ->where('user_id', $tokenUserId)
                ->where('story_user_id', $userId)
                ->exists();

            if ($isMuted) {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 5,
                        'error_text' => 'this user is already muted'
                    ]
                ], 400);
            }

            // Mute user's stories (matching old API: T_MUTE_STORY)
            $muteInsertData = [
                'user_id' => $tokenUserId,
                'story_user_id' => $userId,
            ];
            
            // Only add time if column exists
            if (Schema::hasColumn($muteTable, 'time')) {
                $muteInsertData['time'] = time();
            }
            
            DB::table($muteTable)->insert($muteInsertData);

            return response()->json([
                'api_status' => 200,
                'message' => 'user muted'
            ]);
        } else {
            // Unmute user's stories
            DB::table($muteTable)
                ->where('user_id', $tokenUserId)
                ->where('story_user_id', $userId)
                ->delete();

            return response()->json([
                'api_status' => 200,
                'message' => 'user unmuted'
            ]);
        }
    }

    /**
     * Mark story as seen (mimics old API: mark_story_seen.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function markAsSeen(Request $request): JsonResponse
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

        // Validate request
        $storyId = $request->input('story_id');
        $storyIds = $request->input('story_ids', []); // Support multiple stories
        
        // If single story_id provided, convert to array
        if ($storyId && empty($storyIds)) {
            $storyIds = [$storyId];
        }
        
        // If story_ids is a string (comma-separated), convert to array
        if (is_string($storyIds)) {
            $storyIds = array_filter(array_map('trim', explode(',', $storyIds)));
        }
        
        if (empty($storyIds) || !is_array($storyIds)) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'story_id or story_ids is required'
                ]
            ], 400);
        }

        // Convert to integers and filter invalid IDs
        $storyIds = array_filter(array_map('intval', $storyIds));
        if (empty($storyIds)) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Invalid story_id(s) provided'
                ]
            ], 400);
        }

        $markedCount = 0;
        $alreadySeenCount = 0;
        $notFoundCount = 0;

        if (Schema::hasTable('Wo_StorySeen')) {
            foreach ($storyIds as $id) {
                // Check if story exists
                $story = DB::table('Wo_UserStory')->where('id', $id)->first();
                if (!$story) {
                    $notFoundCount++;
                    continue;
                }

                // Don't mark own stories as seen
                if ($story->user_id == $tokenUserId) {
                    continue;
                }

                // Check if already viewed
                $alreadyViewed = DB::table('Wo_StorySeen')
                    ->where('story_id', $id)
                    ->where('user_id', $tokenUserId)
                    ->exists();

                if ($alreadyViewed) {
                    $alreadySeenCount++;
                    continue;
                }

                // Mark as viewed
                $seenInsertData = [
                    'story_id' => $id,
                    'user_id' => $tokenUserId,
                ];
                
                // Only add time if column exists
                if (Schema::hasColumn('Wo_StorySeen', 'time')) {
                    $seenInsertData['time'] = time();
                }
                
                try {
                    DB::table('Wo_StorySeen')->insert($seenInsertData);
                    $markedCount++;

                    // Create notification for story owner (if notification system is enabled)
                    if (Schema::hasTable('Wo_Notifications')) {
                        try {
                            $storyOwner = DB::table('Wo_Users')->where('user_id', $story->user_id)->first();
                            if ($storyOwner) {
                                DB::table('Wo_Notifications')->insert([
                                    'notifier_id' => $tokenUserId,
                                    'recipient_id' => $story->user_id,
                                    'type' => 'viewed_story',
                                    'url' => 'index.php?link1=timeline&u=' . ($storyOwner->username ?? ''),
                                    'time' => time(),
                                    'seen' => 0,
                                ]);
                            }
                        } catch (\Exception $e) {
                            // Notification creation failed, but story is still marked as seen
                        }
                    }
                } catch (\Exception $e) {
                    // Insert failed, skip this story
                    continue;
                }
            }
        }

        return response()->json([
            'api_status' => 200,
            'message' => 'Stories marked as seen',
            'marked' => $markedCount,
            'already_seen' => $alreadySeenCount,
            'not_found' => $notFoundCount,
            'total_requested' => count($storyIds)
        ]);
    }

    /**
     * Get story views (mimics old API: get_story_views.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStoryViews(Request $request): JsonResponse
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

        // Validate request
        if (empty($request->input('story_id'))) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'story_id can not be empty'
                ]
            ], 400);
        }

        $storyId = (int) $request->input('story_id');
        $limit = (int) ($request->input('limit', $request->query('limit', 20)));
        $limit = max(1, min($limit, 50));
        $offset = (int) ($request->input('offset', $request->query('offset', 0)));

        // Check if story exists and belongs to user
        $story = DB::table('Wo_UserStory')
            ->where('id', $storyId)
            ->where('user_id', $tokenUserId)
            ->first();

        if (!$story) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Story not found or you do not have permission to view its viewers'
                ]
            ], 404);
        }

        // Get story views (excluding story owner)
        $usersData = [];
        
        if (Schema::hasTable('Wo_StorySeen')) {
            try {
                // First, try with join to get user data
                $query = DB::table('Wo_StorySeen')
                    ->join('Wo_Users', 'Wo_StorySeen.user_id', '=', 'Wo_Users.user_id')
                    ->where('Wo_StorySeen.story_id', $storyId)
                    ->where('Wo_StorySeen.user_id', '!=', $tokenUserId); // Exclude story owner from views

                // Only filter by active if column exists
                if (Schema::hasColumn('Wo_Users', 'active')) {
                    $query->whereIn('Wo_Users.active', ['1', 1]); // Only active users
                }

                // Handle offset - if offset is provided and is numeric, use it as record offset
                // If offset looks like an ID (larger number), use it as ID-based pagination
                if ($offset > 0) {
                    // Check if offset is likely an ID (if it's > 1000, treat as ID, otherwise as record offset)
                    if ($offset > 1000) {
                        // ID-based pagination (for infinite scroll)
                        $query->where('Wo_StorySeen.id', '>', $offset);
                    } else {
                        // Record-based offset (skip N records)
                        $query->offset($offset);
                    }
                }

                // Order by time if column exists, otherwise by id
                $hasTimeColumn = Schema::hasColumn('Wo_StorySeen', 'time');
                if ($hasTimeColumn) {
                    $query->orderBy('Wo_StorySeen.time', 'desc');
                } else {
                    $query->orderBy('Wo_StorySeen.id', 'desc');
                }

                // Build select statement based on available columns
                $selectFields = [
                    'Wo_StorySeen.id as view_id',
                    'Wo_StorySeen.user_id',
                    'Wo_StorySeen.story_id',
                    'Wo_Users.username',
                    'Wo_Users.name',
                    'Wo_Users.first_name',
                    'Wo_Users.last_name',
                    'Wo_Users.avatar',
                    'Wo_Users.verified'
                ];
                
                if ($hasTimeColumn) {
                    $selectFields[] = 'Wo_StorySeen.time as viewed_at';
                }

                $views = $query->select($selectFields)
                    ->limit($limit)
                    ->get();

                // Format users data
                foreach ($views as $view) {
                    $userName = $view->name ?? '';
                    if (empty($userName)) {
                        $firstName = $view->first_name ?? '';
                        $lastName = $view->last_name ?? '';
                        $userName = trim($firstName . ' ' . $lastName);
                    }
                    if (empty($userName)) {
                        $userName = $view->username ?? 'Unknown User';
                    }

                    $usersData[] = [
                        'user_id' => $view->user_id,
                        'username' => $view->username ?? 'Unknown',
                        'name' => $userName,
                        'avatar' => $view->avatar ?? '',
                        'avatar_url' => $view->avatar ? asset('storage/' . $view->avatar) : null,
                        'verified' => (bool) ($view->verified ?? false),
                        'offset_id' => $view->view_id,
                        'viewed_at' => isset($view->viewed_at) && $view->viewed_at ? date('c', $view->viewed_at) : null,
                    ];
                }
            } catch (\Exception $e) {
                // If join fails, try without join (fallback)
                try {
                    $query = DB::table('Wo_StorySeen')
                        ->where('story_id', $storyId)
                        ->where('user_id', '!=', $tokenUserId);

                    if ($offset > 0) {
                        if ($offset > 1000) {
                            $query->where('id', '>', $offset);
                        } else {
                            $query->offset($offset);
                        }
                    }

                    $hasTimeColumn = Schema::hasColumn('Wo_StorySeen', 'time');
                    if ($hasTimeColumn) {
                        $query->orderBy('time', 'desc');
                    } else {
                        $query->orderBy('id', 'desc');
                    }

                    $views = $query->limit($limit)->get();

                    // Format users data by fetching user info separately
                    foreach ($views as $view) {
                        $user = DB::table('Wo_Users')->where('user_id', $view->user_id)->first();
                        if ($user) {
                            $userName = $user->name ?? '';
                            if (empty($userName)) {
                                $firstName = $user->first_name ?? '';
                                $lastName = $user->last_name ?? '';
                                $userName = trim($firstName . ' ' . $lastName);
                            }
                            if (empty($userName)) {
                                $userName = $user->username ?? 'Unknown User';
                            }

                            $usersData[] = [
                                'user_id' => $user->user_id,
                                'username' => $user->username ?? 'Unknown',
                                'name' => $userName,
                                'avatar' => $user->avatar ?? '',
                                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                                'verified' => (bool) ($user->verified ?? false),
                                'offset_id' => $view->id,
                                'viewed_at' => ($hasTimeColumn && isset($view->time)) ? date('c', $view->time) : null,
                            ];
                        }
                    }
                } catch (\Exception $e2) {
                    // If both queries fail, return empty array
                    $usersData = [];
                }
            }
        }

        return response()->json([
            'api_status' => 200,
            'users' => $usersData
        ]);
    }

    /**
     * Get friends stories (matching old API: Wo_GetFriendsStatus)
     * 
     * @param string $userId
     * @param int $limit
     * @return array
     */
    private function getFriendsStories(string $userId, int $limit): array
    {
        // Get user's friends and following
        $friendIds = [$userId]; // Include own stories

        // Get friends from Wo_Friends table (if exists)
        if (Schema::hasTable('Wo_Friends')) {
            try {
                $userFriends = DB::table('Wo_Friends')
                    ->where(function($q) use ($userId) {
                        $q->where('from_id', $userId)
                          ->orWhere('to_id', $userId);
                    })
                    ->get();

                foreach ($userFriends as $friend) {
                    if ($friend->from_id != $userId) {
                        $friendIds[] = $friend->from_id;
                    }
                    if ($friend->to_id != $userId) {
                        $friendIds[] = $friend->to_id;
                    }
                }
            } catch (\Exception $e) {
                // Table exists but query failed, continue to followers check
            }
        }

        // Also get people you follow from Wo_Followers table (if exists)
        // This ensures you see stories from people you follow, even if not friends
        if (Schema::hasTable('Wo_Followers')) {
            try {
                // People you are following (where you are the follower)
                $following = DB::table('Wo_Followers')
                    ->where('follower_id', $userId)
                    ->where(function($q) {
                        // Include both active (accepted) and pending (active=0) to see their stories
                        $q->where('active', 1)
                          ->orWhere('active', '1')
                          ->orWhere('active', 0)
                          ->orWhere('active', '0');
                    })
                    ->pluck('following_id')
                    ->toArray();
                
                // People following you (mutual follows)
                $followers = DB::table('Wo_Followers')
                    ->where('following_id', $userId)
                    ->where(function($q) {
                        // Include both active (accepted) and pending
                        $q->where('active', 1)
                          ->orWhere('active', '1')
                          ->orWhere('active', 0)
                          ->orWhere('active', '0');
                    })
                    ->pluck('follower_id')
                    ->toArray();
                
                $friendIds = array_merge($friendIds, $following, $followers);
            } catch (\Exception $e) {
                // Table exists but query failed
            }
        }
        
        $friendIds = array_unique($friendIds);

        // Get muted users
        $mutedUsers = [];
        if (Schema::hasTable('Wo_MuteStory')) {
            $mutedUsers = DB::table('Wo_MuteStory')
                ->where('user_id', $userId)
                ->pluck('story_user_id')
                ->toArray();
        }

        // Get stories from friends (not expired, not muted)
        $stories = DB::table('Wo_UserStory')
            ->whereIn('user_id', $friendIds)
            ->where('expire', '>', time())
            ->whereNotIn('user_id', $mutedUsers)
            ->orderByDesc('posted')
            ->limit($limit)
            ->get();

        $formattedStories = [];
        foreach ($stories as $story) {
            $user = DB::table('Wo_Users')->where('user_id', $story->user_id)->first();
            
            // Get story media
            // Get media table name
            $mediaTable = 'Wo_UserStoryMedia';
            if (!Schema::hasTable($mediaTable)) {
                if (Schema::hasTable('Wo_StoryMedia')) {
                    $mediaTable = 'Wo_StoryMedia';
                }
            }

            $media = null;
            if (Schema::hasTable($mediaTable)) {
                $media = DB::table($mediaTable)
                    ->where('story_id', $story->id)
                    ->first();
            }

            $thumbnail = $story->thumbnail;
            if (empty($thumbnail) && $media) {
                $thumbnail = $media->filename;
            }
            if (empty($thumbnail) && $user) {
                $thumbnail = $user->avatar ?? '';
            }

            $formattedStories[] = [
                'id' => $story->id,
                'user_id' => $story->user_id,
                'title' => $story->title ?? '',
                'description' => $story->description ?? '',
                'posted' => $story->posted,
                'expire' => $story->expire,
                'thumbnail' => $thumbnail ? asset('storage/' . $thumbnail) : null,
                'user_data' => $user ? [
                    'user_id' => $user->user_id,
                    'username' => $user->username ?? 'Unknown',
                    'name' => $user->name ?? $user->username ?? 'Unknown User',
                    'avatar' => $user->avatar ?? '',
                    'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'verified' => (bool) ($user->verified ?? false),
                ] : null,
            ];
        }

        return $formattedStories;
    }

    /**
     * Get friends stories grouped by user (matching old API: Wo_GetFriendsStatusAPI)
     * 
     * @param string $userId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    private function getFriendsStoriesGrouped(string $userId, int $limit, int $offset): array
    {
        // Get user's friends and following
        $friendIds = [$userId]; // Include own stories

        // Get friends from Wo_Friends table (if exists)
        if (Schema::hasTable('Wo_Friends')) {
            try {
                $userFriends = DB::table('Wo_Friends')
                    ->where(function($q) use ($userId) {
                        $q->where('from_id', $userId)
                          ->orWhere('to_id', $userId);
                    })
                    ->get();

                foreach ($userFriends as $friend) {
                    if ($friend->from_id != $userId) {
                        $friendIds[] = $friend->from_id;
                    }
                    if ($friend->to_id != $userId) {
                        $friendIds[] = $friend->to_id;
                    }
                }
            } catch (\Exception $e) {
                // Table exists but query failed, continue to followers check
            }
        }

        // Also get people you follow from Wo_Followers table (if exists)
        // This ensures you see stories from people you follow, even if not friends
        if (Schema::hasTable('Wo_Followers')) {
            try {
                // People you are following (where you are the follower)
                $following = DB::table('Wo_Followers')
                    ->where('follower_id', $userId)
                    ->where(function($q) {
                        // Include both active (accepted) and pending (active=0) to see their stories
                        $q->where('active', 1)
                          ->orWhere('active', '1')
                          ->orWhere('active', 0)
                          ->orWhere('active', '0');
                    })
                    ->pluck('following_id')
                    ->toArray();
                
                // People following you (mutual follows)
                $followers = DB::table('Wo_Followers')
                    ->where('following_id', $userId)
                    ->where(function($q) {
                        // Include both active (accepted) and pending
                        $q->where('active', 1)
                          ->orWhere('active', '1')
                          ->orWhere('active', 0)
                          ->orWhere('active', '0');
                    })
                    ->pluck('follower_id')
                    ->toArray();
                
                $friendIds = array_merge($friendIds, $following, $followers);
            } catch (\Exception $e) {
                // Table exists but query failed
            }
        }
        
        $friendIds = array_unique($friendIds);

        // Get muted users
        $mutedUsers = [];
        if (Schema::hasTable('Wo_MuteStory')) {
            $mutedUsers = DB::table('Wo_MuteStory')
                ->where('user_id', $userId)
                ->pluck('story_user_id')
                ->toArray();
        }

        // Get stories grouped by user
        $storiesByUser = DB::table('Wo_UserStory')
            ->whereIn('user_id', $friendIds)
            ->where('expire', '>', time())
            ->whereNotIn('user_id', $mutedUsers)
            ->orderByDesc('posted')
            ->get()
            ->groupBy('user_id');

        $dataArray = [];
        foreach ($storiesByUser as $storyUserId => $userStories) {
            $user = DB::table('Wo_Users')->where('user_id', $storyUserId)->first();
            if (!$user) continue;

            $userData = [
                'user_id' => $user->user_id,
                'username' => $user->username ?? 'Unknown',
                'name' => $user->name ?? $user->username ?? 'Unknown User',
                'avatar' => $user->avatar ?? '',
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'verified' => (bool) ($user->verified ?? false),
                'stories' => [],
            ];

            // Get media table name
            $mediaTable = 'Wo_UserStoryMedia';
            if (!Schema::hasTable($mediaTable)) {
                if (Schema::hasTable('Wo_StoryMedia')) {
                    $mediaTable = 'Wo_StoryMedia';
                }
            }

            foreach ($userStories as $story) {
                // Get story media
                $media = null;
                if (Schema::hasTable($mediaTable)) {
                    $media = DB::table($mediaTable)
                        ->where('story_id', $story->id)
                        ->first();
                }

                $thumbnail = $story->thumbnail;
                if (empty($thumbnail) && $media) {
                    $thumbnail = $media->filename;
                }
                if (empty($thumbnail)) {
                    $thumbnail = $user->avatar ?? '';
                }

                // Get view count
                $viewCount = 0;
                if (Schema::hasTable('Wo_StorySeen')) {
                    $viewCount = DB::table('Wo_StorySeen')
                        ->where('story_id', $story->id)
                        ->where('user_id', '!=', $story->user_id)
                        ->count();
                }

                $userData['stories'][] = [
                    'id' => $story->id,
                    'user_id' => $story->user_id,
                    'title' => $story->title ?? '',
                    'description' => $story->description ?? '',
                    'posted' => $story->posted,
                    'expire' => $story->expire,
                    'thumbnail' => $thumbnail ? asset('storage/' . $thumbnail) : null,
                    'time_text' => $this->getTimeElapsedString($story->posted),
                    'view_count' => $viewCount,
                ];
            }

            $dataArray[] = $userData;
        }

        return $dataArray;
    }

    /**
     * Get time elapsed string (mimics Wo_Time_Elapsed_String function)
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

