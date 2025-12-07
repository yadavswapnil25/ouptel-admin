<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class PollController extends Controller
{
    /**
     * Create a poll post (mimics old API: requests.php?f=posts&s=insert_new_post with answer array)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createPoll(Request $request): JsonResponse
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
            'postText' => 'required|string|max:5000',
            'postPrivacy' => 'required|in:0,1,2,3,4',
            'answer' => 'required|array|min:2|max:10',
            'answer.*' => 'required|string|max:255',
            'page_id' => 'nullable|integer',
            'group_id' => 'nullable|integer',
            'event_id' => 'nullable|integer',
            'recipient_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $firstError = $errors->first();
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 3,
                    'error_text' => $firstError
                ]
            ], 400);
        }

        // Validate answer array (matching old API validation)
        $answers = $request->input('answer');
        foreach ($answers as $key => $value) {
            if (empty($value) || ctype_space($value)) {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 12,
                        'error_text' => 'Answer #' . ($key + 1) . ' is empty.'
                    ]
                ], 400);
            }
        }

        try {
            DB::beginTransaction();

            // Check if poll table exists
            if (!Schema::hasTable('Wo_Polls')) {
                DB::rollBack();
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 15,
                        'error_text' => 'Poll system is not available: Wo_Polls table does not exist'
                    ]
                ], 500);
            }

            // Create post with poll_id = 1 (matching old API: poll_id is set to 1 when answers exist)
            $postId = DB::table('Wo_Posts')->insertGetId([
                'user_id' => $tokenUserId,
                'postText' => $request->input('postText'),
                'postPrivacy' => $request->input('postPrivacy'),
                'postType' => 'poll',
                'poll_id' => 1, // Old API sets poll_id to 1 when answers exist
                'page_id' => $request->input('page_id', 0),
                'group_id' => $request->input('group_id', 0),
                'event_id' => $request->input('event_id', 0),
                'recipient_id' => $request->input('recipient_id', 0),
                'time' => time(),
                'active' => 1,
            ]);

            // Create poll options in Wo_Polls table (matching old API: Wo_AddOption function)
            // Each option is stored as a row in Wo_Polls with post_id, text, time
            $optionIds = [];
            foreach ($answers as $answerText) {
                $optionInsertData = [
                    'post_id' => $postId,
                    'text' => $answerText,
                ];
                
                // Only add time if column exists
                if (Schema::hasColumn('Wo_Polls', 'time')) {
                    $optionInsertData['time'] = time();
                }
                
                $optionId = DB::table('Wo_Polls')->insertGetId($optionInsertData);
                $optionIds[] = $optionId;
            }

            DB::commit();

            // Get created post data (matching old API response format)
            $post = DB::table('Wo_Posts')->where('id', $postId)->first();
            $user = DB::table('Wo_Users')->where('user_id', $tokenUserId)->first();

            // Format response matching old API structure
            $responseData = [
                'api_status' => 200,
                'post_id' => $postId,
                'poll_id' => 1, // poll_id is 1 for polls
                'post_data' => [
                    'id' => $post->id,
                    'post_id' => $post->post_id ?? $post->id,
                    'user_id' => $post->user_id,
                    'postText' => $post->postText,
                    'postType' => 'poll',
                    'poll_id' => 1,
                    'time' => $post->time,
                    'publisher' => $user ? [
                        'user_id' => $user->user_id,
                        'username' => $user->username ?? 'Unknown',
                        'name' => $user->name ?? $user->username ?? 'Unknown User',
                    ] : null,
                ],
                'poll_options' => array_map(function($optionId, $index) use ($answers) {
                    return [
                        'id' => $optionId,
                        'text' => $answers[$index],
                        'votes' => 0,
                        'percentage' => 0,
                    ];
                }, $optionIds, array_keys($answers)),
            ];

            return response()->json($responseData);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 14,
                    'error_text' => 'Something went wrong: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Vote on a poll option (mimics old API: vote_up.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function voteUp(Request $request): JsonResponse
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

        // Validate request (matching old API: requires 'id' which is option_id)
        if (empty($request->input('id'))) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 3,
                    'error_text' => 'id (POST) is missing'
                ]
            ], 400);
        }

        $optionId = (int) $request->input('id');

        // Check if poll table exists
        if (!Schema::hasTable('Wo_Polls')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 7,
                    'error_text' => 'Poll system is not available: Wo_Polls table does not exist'
                ]
            ], 500);
        }

        // Get post_id from option_id (matching old API: options are stored in Wo_Polls with post_id)
        $option = DB::table('Wo_Polls')->where('id', $optionId)->first();
        if (!$option || empty($option->post_id)) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Poll option not found'
                ]
            ], 404);
        }

        $postId = $option->post_id;

        // Get post to verify it's a poll
        $post = DB::table('Wo_Posts')->where('id', $postId)->where('poll_id', 1)->first();
        if (!$post) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Poll post not found'
                ]
            ], 404);
        }

        // Check if votes table exists (old API uses Wo_Votes, not Wo_PollVotes)
        $votesTable = 'Wo_Votes';
        if (!Schema::hasTable($votesTable)) {
            // Try alternative table name
            if (Schema::hasTable('Wo_PollVotes')) {
                $votesTable = 'Wo_PollVotes';
            } else {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 8,
                        'error_text' => 'Poll system is not available: Votes table does not exist'
                    ]
                ], 500);
            }
        }

        // Check if user already voted (matching old API behavior: Wo_IsPostVoted)
        $existingVote = DB::table($votesTable)
            ->where('post_id', $postId)
            ->where('user_id', $tokenUserId)
            ->first();

        if ($existingVote) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'you have already voted'
                ]
            ], 400);
        }

        // Create vote (matching old API: Wo_VoteUp - stores option_id, user_id, post_id)
        $voteInsertData = [
            'option_id' => $optionId,
            'user_id' => $tokenUserId,
            'post_id' => $postId,
        ];
        
        // Only add time if column exists
        if (Schema::hasColumn($votesTable, 'time')) {
            $voteInsertData['time'] = time();
        }
        
        $voteCreated = DB::table($votesTable)->insert($voteInsertData);

        if ($voteCreated) {
            // Get percentage of votes for each option (matching old API: Ju_GetPercentageOfOptionPost)
            $totalVotes = DB::table($votesTable)
                ->where('post_id', $postId)
                ->count();

            // Get options from Wo_Polls table (matching old API: Wo_GetPostOptions)
            $options = DB::table('Wo_Polls')
                ->where('post_id', $postId)
                ->get();

            $votes = [];
            foreach ($options as $opt) {
                $optionVotes = DB::table($votesTable)
                    ->where('post_id', $postId)
                    ->where('option_id', $opt->id)
                    ->count();
                
                $percentage = $totalVotes > 0 ? round(($optionVotes / $totalVotes) * 100, 2) : 0;

                $votes[] = [
                    'id' => $opt->id,
                    'text' => $opt->text,
                    'votes' => $optionVotes,
                    'percentage' => $percentage,
                ];
            }

            return response()->json([
                'api_status' => 200,
                'votes' => $votes
            ]);
        } else {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Failed to record vote'
                ]
            ], 500);
        }
    }

    /**
     * Get poll details with vote percentages
     * 
     * @param Request $request
     * @param int $postId
     * @return JsonResponse
     */
    public function getPollDetails(Request $request, int $postId): JsonResponse
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

        // Get post
        $post = DB::table('Wo_Posts')->where('id', $postId)->where('poll_id', 1)->first();
        if (!$post) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 7,
                    'error_text' => 'Poll post not found'
                ]
            ], 404);
        }

        // Check if poll table exists
        if (!Schema::hasTable('Wo_Polls')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 9,
                    'error_text' => 'Poll system is not available: Wo_Polls table does not exist'
                ]
            ], 500);
        }

        // Get poll options from Wo_Polls table (matching old API: Wo_GetPostOptions)
        $options = DB::table('Wo_Polls')
            ->where('post_id', $postId)
            ->get();

        // Check if votes table exists (old API uses Wo_Votes, not Wo_PollVotes)
        $votesTable = 'Wo_Votes';
        if (!Schema::hasTable($votesTable)) {
            // Try alternative table name
            if (Schema::hasTable('Wo_PollVotes')) {
                $votesTable = 'Wo_PollVotes';
            } else {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 10,
                        'error_text' => 'Poll system is not available: Votes table does not exist'
                    ]
                ], 500);
            }
        }

        // Get total votes (matching old API: votes stored with post_id)
        $totalVotes = DB::table($votesTable)
            ->where('post_id', $postId)
            ->count();

        // Get user's vote
        $userVote = DB::table($votesTable)
            ->where('post_id', $postId)
            ->where('user_id', $tokenUserId)
            ->value('option_id');

        // Calculate percentages (matching old API: Ju_GetPercentageOfOptionPost)
        $votes = [];
        foreach ($options as $option) {
            $optionVotes = DB::table($votesTable)
                ->where('post_id', $postId)
                ->where('option_id', $option->id)
                ->count();
            
            $percentage = $totalVotes > 0 ? round(($optionVotes / $totalVotes) * 100, 2) : 0;

            $votes[] = [
                'id' => $option->id,
                'text' => $option->text,
                'votes' => $optionVotes,
                'percentage' => $percentage,
                'is_voted' => $userVote == $option->id,
            ];
        }

        return response()->json([
            'api_status' => 200,
            'data' => [
                'post_id' => $postId,
                'poll_id' => 1, // poll_id is always 1 for polls
                'total_votes' => $totalVotes,
                'user_voted' => !is_null($userVote),
                'user_vote_option_id' => $userVote,
                'votes' => $votes,
            ]
        ]);
    }
}

