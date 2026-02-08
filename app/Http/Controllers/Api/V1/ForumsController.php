<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Forum;
use App\Models\ForumTopic;
use App\Models\ForumReply;
use App\Models\ForumMember;
use App\Models\ForumCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class ForumsController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $type = $request->query('type', 'all'); // all, my_forums, joined_forums, suggested

        // Resolve user via token when needed
        $authHeader = $request->header('Authorization');
        $tokenUserId = null;
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        }

        $query = Forum::query();

        if ($type === 'my_forums') {
            if (!$tokenUserId) {
                return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
            }
            // Note: user_id column doesn't exist in Wo_Forums table
            // Return empty result since we can't filter by user
            $query->where('id', 0); // This will return no results
        } elseif ($type === 'joined_forums') {
            if (!$tokenUserId) {
                return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
            }
            // Simplified since Wo_ForumMembers table might not exist
            // Return all forums since we can't filter by user
        } elseif ($type === 'suggested') {
            // Return all forums since we can't filter by user
        }

        // Note: category column doesn't exist in Wo_Forums table
        // if ($request->filled('category')) {
        //     $query->where('category', $request->query('category'));
        // }

        if ($request->filled('term')) {
            $like = '%' . str_replace('%', '\\%', $request->query('term')) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('description', 'like', $like);
            });
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Forum $forum) use ($tokenUserId) {
            return [
                'id' => $forum->id,
                'name' => $forum->name,
                'description' => $forum->description,
                'category' => null, // Column doesn't exist
                'privacy' => 'public', // Default value since column doesn't exist
                'join_privacy' => 'public', // Default value since column doesn't exist
                'topics_count' => $forum->topics_count,
                'members_count' => $forum->members_count,
                'is_joined' => false, // Simplified since user_id doesn't exist
                'is_owner' => false, // Simplified since user_id doesn't exist
                'created_at' => null, // time column doesn't exist
                'owner' => [
                    'user_id' => null,
                    'username' => 'Unknown',
                    'avatar_url' => null,
                ],
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'type' => $type,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            // Note: category, privacy, join_privacy columns don't exist in Wo_Forums table
        ]);

        // Check if forum name is already taken
        $existingForum = Forum::where('name', $validated['name'])->first();
        if ($existingForum) {
            return response()->json(['ok' => false, 'message' => 'Forum name is already taken'], 400);
        }

        $forum = new Forum();
        $forum->name = $validated['name'];
        $forum->description = $validated['description'] ?? '';
        // Note: category, privacy, join_privacy, user_id, active, time columns don't exist in Wo_Forums table
        $forum->save();

        return response()->json([
            'ok' => true,
            'message' => 'Forum created successfully',
            'data' => [
                'id' => $forum->id,
                'name' => $forum->name,
                'description' => $forum->description,
                'category' => null, // Column doesn't exist
                'privacy' => 'public', // Default value since column doesn't exist
                'join_privacy' => 'public', // Default value since column doesn't exist
                'topics_count' => 0,
                'members_count' => 0,
                'is_joined' => false,
                'is_owner' => false, // Simplified since user_id doesn't exist
                'created_at' => null, // time column doesn't exist
            ],
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $forum = Forum::where('id', $id)->first();
        if (!$forum) {
            return response()->json(['ok' => false, 'message' => 'Forum not found'], 404);
        }

        // Note: Wo_ForumTopics table doesn't exist
        // Return empty topics data
        $topicsData = collect([]);

        return response()->json([
            'ok' => true,
            'data' => [
                'forum' => [
                    'id' => $forum->id,
                    'name' => $forum->name,
                    'description' => $forum->description,
                    'category' => null, // Column doesn't exist
                    'privacy' => 'public', // Default value since column doesn't exist
                    'join_privacy' => 'public', // Default value since column doesn't exist
                    'topics_count' => $forum->topics_count,
                    'members_count' => $forum->members_count,
                    'created_at' => null, // time column doesn't exist
                    'owner' => [
                        'user_id' => null,
                        'username' => 'Unknown',
                        'avatar_url' => null,
                    ],
                ],
                'topics' => $topicsData,
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 12,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ],
        ]);
    }

    public function topics(Request $request, $id): JsonResponse
    {
        $forum = Forum::where('id', $id)->first();
        if (!$forum) {
            return response()->json(['ok' => false, 'message' => 'Forum not found'], 404);
        }

        // Note: Wo_ForumTopics table doesn't exist
        // Return empty topics data
        $data = collect([]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => 1,
                'per_page' => 12,
                'total' => 0,
                'last_page' => 1,
            ],
        ]);
    }

    public function createTopic(Request $request, $id): JsonResponse
    {
        // Note: Wo_ForumTopics table doesn't exist
        return response()->json([
            'ok' => false,
            'message' => 'Topics feature not available - Wo_ForumTopics table does not exist'
        ], 501);
    }

    public function topicReplies(Request $request, $forumId, $topicId): JsonResponse
    {
        // Note: Wo_ForumTopics and Wo_ForumReplies tables don't exist
        return response()->json([
            'ok' => false,
            'message' => 'Replies feature not available - Required tables do not exist'
        ], 501);
    }

    public function createReply(Request $request, $forumId, $topicId): JsonResponse
    {
        // Note: Wo_ForumTopics and Wo_ForumReplies tables don't exist
        return response()->json([
            'ok' => false,
            'message' => 'Replies feature not available - Required tables do not exist'
        ], 501);
    }

    public function members(Request $request, $id): JsonResponse
    {
        // This method is kept for backward compatibility but delegates to ForumMemberController
        // In old WoWonder, forum members are users who have posted in forums (no separate membership table)
        $forum = Forum::where('id', $id)->first();
        if (!$forum) {
            return response()->json(['ok' => false, 'message' => 'Forum not found'], 404);
        }

        // Redirect to ForumMemberController logic
        $memberController = new \App\Http\Controllers\Api\V1\ForumMemberController();
        return $memberController->index($request, $id);
    }

    public function search(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));
        $page = (int) ($request->query('page', 1));
        $page = max(1, $page);

        $term = $request->query('q', $request->query('term'));
        if (empty($term)) {
            return response()->json(['ok' => false, 'message' => 'Search term is required'], 400);
        }

        $like = '%' . str_replace('%', '\\%', $term) . '%';

        // Search forums
        $forums = Forum::where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('description', 'like', $like);
            })
            ->orderByDesc('id')
            ->get()
            ->map(function (Forum $forum) {
                return [
                    'id' => $forum->id,
                    'name' => $forum->name,
                    'description' => $forum->description,
                    'type' => 'forum',
                    'created_at' => null,
                    'sort_timestamp' => $forum->id, // Use ID as fallback for sorting
                ];
            });

        // Search topics (threads) in Wo_Forum_Threads table
        $topics = ForumTopic::where(function ($q) use ($like) {
                $q->where('headline', 'like', $like)
                  ->orWhere('post', 'like', $like);
            })
            ->where('posted', '>', 0) // Only active threads (posted > 0)
            ->orderByDesc('posted')
            ->get()
            ->map(function (ForumTopic $topic) {
                return [
                    'id' => $topic->id,
                    'name' => $topic->headline,
                    'description' => mb_substr($topic->post, 0, 200), // Truncate description
                    'type' => 'topic',
                    'forum_id' => $topic->forum,
                    'created_at' => $topic->posted,
                    'sort_timestamp' => is_numeric($topic->posted) ? (int) $topic->posted : 0,
                ];
            });

        // Search replies in Wo_ForumThreadReplies table
        $replies = ForumReply::where('post', 'like', $like)
            ->where('active', '1') // Only active replies
            ->orderByDesc('posted_time')
            ->get()
            ->map(function (ForumReply $reply) {
                return [
                    'id' => $reply->id,
                    'name' => 'Reply', // Replies don't have a title
                    'description' => mb_substr($reply->post, 0, 200), // Truncate description
                    'type' => 'reply',
                    'thread_id' => $reply->thread_id,
                    'created_at' => $reply->posted_time,
                    'sort_timestamp' => is_numeric($reply->posted_time) ? (int) $reply->posted_time : 0,
                ];
            });

        // Merge all results and sort by timestamp (newest first)
        $allResults = collect()
            ->merge($forums)
            ->merge($topics)
            ->merge($replies)
            ->sortByDesc('sort_timestamp')
            ->values();

        // Calculate pagination
        $total = $allResults->count();
        $lastPage = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedResults = $allResults->slice($offset, $perPage)->values();

        return response()->json([
            'data' => $paginatedResults,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'search_term' => $term,
            ],
        ]);
    }

    public function myThreads(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        // Note: Wo_ForumTopics table doesn't exist
        // Return empty threads data
        $data = collect([]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => 1,
                'per_page' => 12,
                'total' => 0,
                'last_page' => 1,
            ],
        ]);
    }

    public function myMessages(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        // Note: Wo_ForumReplies table doesn't exist
        // Return empty messages data
        $data = collect([]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => 1,
                'per_page' => 12,
                'total' => 0,
                'last_page' => 1,
            ],
        ]);
    }

    public function meta(): JsonResponse
    {
        // Note: Wo_ForumCategories table doesn't exist
        // Return default categories
        $categories = collect([
            (object) [
                'id' => 1,
                'name' => 'General',
                'description' => 'General discussion',
            ],
            (object) [
                'id' => 2,
                'name' => 'Technology',
                'description' => 'Technology discussions',
            ],
            (object) [
                'id' => 3,
                'name' => 'Business',
                'description' => 'Business discussions',
            ],
        ]);

        return response()->json([
            'ok' => true,
            'data' => [
                'types' => [
                    'privacy' => ['public', 'private'],
                    'join_privacy' => ['public', 'private'],
                ],
                'categories' => $categories,
            ],
        ]);
    }
}
