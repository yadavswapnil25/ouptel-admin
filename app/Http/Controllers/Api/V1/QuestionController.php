<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class QuestionController extends Controller
{
    /**
     * Create a question post.
     */
    public function createQuestion(Request $request): JsonResponse
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }
        $tokenUserId = $auth;

        if (!$this->questionsEnabled()) {
            return response()->json([
                'api_status' => 400,
                'ok' => false,
                'errors' => [
                    'error_id' => 20,
                    'error_text' => 'Questions are disabled by the administrator.',
                ],
                'message' => 'Questions are disabled by the administrator.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'postText' => 'required|string|max:5000',
            'postPrivacy' => 'required|in:0,1,2,3,4',
            'page_id' => 'nullable|integer',
            'group_id' => 'nullable|integer',
            'event_id' => 'nullable|integer',
            'recipient_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'ok' => false,
                'errors' => [
                    'error_id' => 3,
                    'error_text' => $validator->errors()->first(),
                ],
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $postText = trim((string) $request->input('postText'));
        if ($postText === '') {
            return response()->json([
                'api_status' => 400,
                'ok' => false,
                'errors' => [
                    'error_id' => 3,
                    'error_text' => 'Question text is required.',
                ],
                'message' => 'Question text is required.',
            ], 400);
        }

        try {
            $publicPostId = $this->generatePublicPostId();
            $insertData = [
                'post_id' => $publicPostId,
                'user_id' => $tokenUserId,
                'postText' => $postText,
                'postPrivacy' => $request->input('postPrivacy'),
                'postType' => 'question',
                'parent_id' => 0,
                'page_id' => $request->input('page_id', 0),
                'group_id' => $request->input('group_id', 0),
                'event_id' => $request->input('event_id', 0),
                'recipient_id' => $request->input('recipient_id', 0),
                'time' => time(),
                'active' => 1,
            ];

            if (Schema::hasColumn('Wo_Posts', 'registered')) {
                $insertData['registered'] = time();
            }
            if (Schema::hasColumn('Wo_Posts', 'post_url')) {
                $insertData['post_url'] = url('/post/' . $publicPostId);
            }

            $id = DB::table('Wo_Posts')->insertGetId($insertData);
            $post = DB::table('Wo_Posts')->where('id', $id)->first();
            $user = DB::table('Wo_Users')->where('user_id', $tokenUserId)->first();

            return response()->json([
                'api_status' => 200,
                'ok' => true,
                'post_id' => $id,
                'post_id_original' => $post->post_id ?? $publicPostId,
                'post_data' => $this->formatAnswerOrQuestion($post, $user, 0, []),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 400,
                'ok' => false,
                'errors' => [
                    'error_id' => 15,
                    'error_text' => 'Failed to create question: ' . $e->getMessage(),
                ],
                'message' => 'Failed to create question',
            ], 500);
        }
    }

    /**
     * Create an answer post under a question.
     */
    public function createAnswer(Request $request, $id): JsonResponse
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }
        $tokenUserId = $auth;

        if (!$this->questionsEnabled()) {
            return response()->json([
                'api_status' => 400,
                'ok' => false,
                'errors' => [
                    'error_id' => 20,
                    'error_text' => 'Questions are disabled by the administrator.',
                ],
                'message' => 'Questions are disabled by the administrator.',
            ], 403);
        }

        $question = $this->findQuestion((int) $id);
        if (!$question) {
            return response()->json([
                'api_status' => 400,
                'ok' => false,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Question not found',
                ],
                'message' => 'Question not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'postText' => 'required|string|max:5000',
            'postPrivacy' => 'nullable|in:0,1,2,3,4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'ok' => false,
                'errors' => [
                    'error_id' => 3,
                    'error_text' => $validator->errors()->first(),
                ],
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $postText = trim((string) $request->input('postText'));
        if ($postText === '') {
            return response()->json([
                'api_status' => 400,
                'ok' => false,
                'errors' => [
                    'error_id' => 3,
                    'error_text' => 'Answer text is required.',
                ],
                'message' => 'Answer text is required.',
            ], 400);
        }

        try {
            $publicPostId = $this->generatePublicPostId();
            $insertData = [
                'post_id' => $publicPostId,
                'user_id' => $tokenUserId,
                'postText' => $postText,
                'postPrivacy' => $request->input('postPrivacy', $question->postPrivacy ?? '0'),
                'postType' => 'answer',
                'parent_id' => $question->id,
                'page_id' => $question->page_id ?? 0,
                'group_id' => $question->group_id ?? 0,
                'event_id' => $question->event_id ?? 0,
                'recipient_id' => 0,
                'time' => time(),
                'active' => 1,
            ];

            if (Schema::hasColumn('Wo_Posts', 'registered')) {
                $insertData['registered'] = time();
            }
            if (Schema::hasColumn('Wo_Posts', 'post_url')) {
                $insertData['post_url'] = url('/post/' . $publicPostId);
            }

            $answerId = DB::table('Wo_Posts')->insertGetId($insertData);
            $answer = DB::table('Wo_Posts')->where('id', $answerId)->first();
            $user = DB::table('Wo_Users')->where('user_id', $tokenUserId)->first();

            return response()->json([
                'api_status' => 200,
                'ok' => true,
                'post_id' => $answerId,
                'post_id_original' => $answer->post_id ?? $publicPostId,
                'question_id' => $question->id,
                'post_data' => $this->formatAnswerOrQuestion($answer, $user, 0, []),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 400,
                'ok' => false,
                'errors' => [
                    'error_id' => 15,
                    'error_text' => 'Failed to create answer: ' . $e->getMessage(),
                ],
                'message' => 'Failed to create answer',
            ], 500);
        }
    }

    /**
     * List answers for a question (paginated).
     */
    public function listAnswers(Request $request, $id): JsonResponse
    {
        $auth = $this->authenticate($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $question = $this->findQuestion((int) $id);
        if (!$question) {
            return response()->json([
                'api_status' => 400,
                'ok' => false,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Question not found',
                ],
                'message' => 'Question not found',
            ], 404);
        }

        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $baseQuery = DB::table('Wo_Posts')
            ->where('parent_id', $question->id)
            ->where('postType', 'answer')
            ->where('active', '1');

        $total = (clone $baseQuery)->count();
        $rows = (clone $baseQuery)
            ->orderByDesc('time')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $userIds = $rows->pluck('user_id')->unique()->filter()->values()->all();
        $usersById = [];
        if (!empty($userIds)) {
            $users = DB::table('Wo_Users')->whereIn('user_id', $userIds)->get();
            foreach ($users as $user) {
                $usersById[$user->user_id] = $user;
            }
        }

        $answers = $rows->map(function ($row) use ($usersById) {
            return $this->formatAnswerOrQuestion($row, $usersById[$row->user_id] ?? null, 0, []);
        })->values()->all();

        $lastPage = (int) ceil($total / $perPage);

        return response()->json([
            'api_status' => 200,
            'ok' => true,
            'question_id' => $question->id,
            'data' => $answers,
            'meta' => [
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => max(1, $lastPage),
                    'has_more' => $page < $lastPage,
                ],
            ],
        ]);
    }

    private function authenticate(Request $request)
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'ok' => false,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided',
                ],
            ], 401);
        }

        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'ok' => false,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found',
                ],
            ], 401);
        }

        return $tokenUserId;
    }

    private function questionsEnabled(): bool
    {
        return filter_var(Setting::get('post_questions', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function findQuestion(int $id)
    {
        if ($id <= 0) {
            return null;
        }

        $question = DB::table('Wo_Posts')
            ->where('id', $id)
            ->where('postType', 'question')
            ->where('active', '1')
            ->first();

        if ($question) {
            return $question;
        }

        // Also accept public post_id
        return DB::table('Wo_Posts')
            ->where('post_id', $id)
            ->where('postType', 'question')
            ->where('active', '1')
            ->first();
    }

    private function generatePublicPostId(): int
    {
        do {
            $postId = rand(100000000, 999999999);
        } while (DB::table('Wo_Posts')->where('post_id', $postId)->exists());

        return $postId;
    }

    private function formatAnswerOrQuestion($post, $user, int $answerCount = 0, array $answerPreviews = []): array
    {
        return [
            'id' => $post->id,
            'post_id' => $post->post_id ?? $post->id,
            'user_id' => $post->user_id,
            'post_text' => $post->postText ?? '',
            'postText' => $post->postText ?? '',
            'post_type' => $post->postType ?? 'text',
            'postType' => $post->postType ?? 'text',
            'post_privacy' => $post->postPrivacy ?? '0',
            'parent_id' => (int) ($post->parent_id ?? 0),
            'answer_count' => $answerCount,
            'answers' => $answerPreviews,
            'time' => $post->time ?? null,
            'created_at' => !empty($post->time) ? date('c', $post->time) : null,
            'author' => $user ? [
                'user_id' => $user->user_id,
                'username' => $user->username ?? 'Unknown',
                'name' => $user->name ?? $user->username ?? 'Unknown User',
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            ] : null,
            'publisher' => $user ? [
                'user_id' => $user->user_id,
                'username' => $user->username ?? 'Unknown',
                'name' => $user->name ?? $user->username ?? 'Unknown User',
            ] : null,
        ];
    }
}
