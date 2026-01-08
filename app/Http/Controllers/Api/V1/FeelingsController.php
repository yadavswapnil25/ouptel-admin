<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\PostController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeelingsController extends Controller
{
    /**
     * Get available feelings (mimics WoWonder feelings system)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Auth is optional for this endpoint
        $tokenUserId = null;
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        }

        // Available feelings from WoWonder
        $feelings = [
            [
                'key' => 'happy',
                'label' => 'Happy',
                'icon' => 'smile',
                'emoji' => '😊'
            ],
            [
                'key' => 'loved',
                'label' => 'Loved',
                'icon' => 'heart-eyes',
                'emoji' => '😍'
            ],
            [
                'key' => 'sad',
                'label' => 'Sad',
                'icon' => 'disappointed',
                'emoji' => '😞'
            ],
            [
                'key' => 'so_sad',
                'label' => 'So Sad',
                'icon' => 'sob',
                'emoji' => '😭'
            ],
            [
                'key' => 'angry',
                'label' => 'Angry',
                'icon' => 'angry',
                'emoji' => '😠'
            ],
            [
                'key' => 'confused',
                'label' => 'Confused',
                'icon' => 'confused',
                'emoji' => '😕'
            ],
            [
                'key' => 'smirk',
                'label' => 'Smirk',
                'icon' => 'smirk',
                'emoji' => '😏'
            ],
            [
                'key' => 'broke',
                'label' => 'Broke',
                'icon' => 'broken-heart',
                'emoji' => '💔'
            ],
            [
                'key' => 'expressionless',
                'label' => 'Expressionless',
                'icon' => 'expressionless',
                'emoji' => '😑'
            ],
            [
                'key' => 'cool',
                'label' => 'Cool',
                'icon' => 'sunglasses',
                'emoji' => '😎'
            ],
            [
                'key' => 'funny',
                'label' => 'Funny',
                'icon' => 'joy',
                'emoji' => '😂'
            ],
            [
                'key' => 'tired',
                'label' => 'Tired',
                'icon' => 'tired-face',
                'emoji' => '😴'
            ],
            [
                'key' => 'lovely',
                'label' => 'Lovely',
                'icon' => 'heart',
                'emoji' => '❤️'
            ],
            [
                'key' => 'blessed',
                'label' => 'Blessed',
                'icon' => 'innocent',
                'emoji' => '😇'
            ],
            [
                'key' => 'shocked',
                'label' => 'Shocked',
                'icon' => 'scream',
                'emoji' => '😱'
            ],
            [
                'key' => 'sleepy',
                'label' => 'Sleepy',
                'icon' => 'sleeping',
                'emoji' => '😴'
            ],
            [
                'key' => 'pretty',
                'label' => 'Pretty',
                'icon' => 'relaxed',
                'emoji' => '😌'
            ],
            [
                'key' => 'bored',
                'label' => 'Bored',
                'icon' => 'unamused',
                'emoji' => '😒'
            ],
        ];

        return response()->json([
            'ok' => true,
            'data' => [
                'feelings' => $feelings,
                'total' => count($feelings)
            ]
        ]);
    }

    /**
     * Create a feeling post (mimics WoWonder requests.php?f=posts&s=insert_new_post with feeling)
     * 
     * This is a convenience endpoint that wraps the post creation with feeling
     * The main POST /api/v1/posts endpoint also supports feelings via postFeeling parameter
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createFeelingPost(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized - No Bearer token provided'], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token - Session not found'], 401);
        }

        // Validate request
        $validated = $request->validate([
            'postText' => 'nullable|string|max:5000',
            'postPrivacy' => 'required|in:0,1,2,3,4', // 0=Public, 1=Friends, 2=Only Me, 3=Custom, 4=Group
            'feeling' => 'required|string|max:100',
            'page_id' => 'nullable|integer',
            'group_id' => 'nullable|integer',
            'event_id' => 'nullable|integer',
        ]);

        // Validate feeling key
        $validFeelings = [
            'happy', 'loved', 'sad', 'so_sad', 'angry', 'confused', 'smirk',
            'broke', 'expressionless', 'cool', 'funny', 'tired', 'lovely',
            'blessed', 'shocked', 'sleepy', 'pretty', 'bored'
        ];

        if (!in_array($validated['feeling'], $validFeelings)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid feeling. Use GET /api/v1/feelings to see available feelings.'
            ], 422);
        }

        // Create post with feeling using PostController
        // Merge feeling into request
        $request->merge(['postFeeling' => $validated['feeling']]);
        
        // Use dependency injection to get PostController
        $postController = app(PostController::class);
        
        return $postController->insertNewPost($request);
    }
}

