<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Forum;
use App\Models\ForumTopic;
use App\Models\ForumReply;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ForumMemberController extends Controller
{
    /**
     * Get forum members list
     * GET /forums/{forumId}/members
     * 
     * Matches old WoWonder API: ajax_loading.php?link1=forum-members
     * In old code, forum members are users who have posted in forums (topics or replies)
     * No separate membership table - just users who are active in forums
     * 
     * @param Request $request
     * @param int $forumId
     * @return JsonResponse
     */
    public function index(Request $request, $forumId): JsonResponse
    {
        $forum = Forum::where('id', $forumId)->first();
        if (!$forum) {
            return response()->json(['ok' => false, 'message' => 'Forum not found'], 404);
        }

        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));
        $offset = (int) ($request->query('offset', 0));
        $search = $request->query('search'); // Search by username
        $char = $request->query('char'); // Filter by first letter of username

        // Get distinct user_ids who have posted in this forum (topics or replies)
        $topicUserIds = ForumTopic::where('forum_id', $forumId)
            ->where('active', '1')
            ->distinct()
            ->pluck('user_id')
            ->toArray();

        $replyUserIds = ForumReply::whereHas('topic', function ($q) use ($forumId) {
                $q->where('forum_id', $forumId);
            })
            ->where('active', '1')
            ->distinct()
            ->pluck('user_id')
            ->toArray();

        $allUserIds = array_unique(array_merge($topicUserIds, $replyUserIds));

        if (empty($allUserIds)) {
            return response()->json([
                'ok' => true,
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                    'forum_id' => $forumId,
                ],
            ]);
        }

        // Build query for users
        $query = User::whereIn('user_id', $allUserIds)
            ->where('active', '1');

        // Filter by offset (pagination by user_id)
        if ($offset > 0) {
            $query->where('user_id', '<', $offset);
        }

        // Filter by first letter of username
        if ($char) {
            $char = substr($char, 0, 1);
            $query->where('username', 'like', $char . '%');
        }

        // Search by username
        if ($search) {
            $like = '%' . str_replace('%', '\\%', $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('username', 'like', $like)
                  ->orWhere('first_name', 'like', $like)
                  ->orWhere('last_name', 'like', $like);
            });
        }

        // Get users ordered by user_id DESC (matching old WoWonder behavior)
        $users = $query->orderByDesc('user_id')
            ->limit($perPage)
            ->get();

        // Get forum post counts for each user
        $data = $users->map(function (User $user) use ($forumId) {
            // Count topics and replies in this forum
            $topicsCount = ForumTopic::where('forum_id', $forumId)
                ->where('user_id', $user->user_id)
                ->where('active', '1')
                ->count();

            $repliesCount = ForumReply::whereHas('topic', function ($q) use ($forumId) {
                    $q->where('forum_id', $forumId);
                })
                ->where('user_id', $user->user_id)
                ->where('active', '1')
                ->count();

            $forumPosts = $topicsCount + $repliesCount;

            return [
                'user_id' => $user->user_id,
                'username' => $user->username ?? null,
                'first_name' => $user->first_name ?? null,
                'last_name' => $user->last_name ?? null,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'avatar' => $user->avatar ?? null,
                'avatar_url' => $user->avatar_url ?? null,
                'verified' => isset($user->verified) ? ($user->verified === '1' || $user->verified === 1) : false,
                'active' => isset($user->active) ? ($user->active === '1' || $user->active === 1) : false,
                'forum_posts' => $forumPosts, // Total posts (topics + replies) in this forum
            ];
        });

        // Calculate total (simplified - get count of unique users)
        $totalQuery = User::whereIn('user_id', $allUserIds)->where('active', '1');
        if ($char) {
            $char = substr($char, 0, 1);
            $totalQuery->where('username', 'like', $char . '%');
        }
        if ($search) {
            $like = '%' . str_replace('%', '\\%', $search) . '%';
            $totalQuery->where(function ($q) use ($like) {
                $q->where('username', 'like', $like)
                  ->orWhere('first_name', 'like', $like)
                  ->orWhere('last_name', 'like', $like);
            });
        }
        $total = $totalQuery->count();

        return response()->json([
            'ok' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $offset > 0 ? floor($offset / $perPage) + 1 : 1,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'forum_id' => $forumId,
            ],
        ]);
    }

    /**
     * Check if user has posted in forum
     * POST /forums/{forumId}/members (not in old WoWonder - kept for API compatibility)
     * 
     * @param Request $request
     * @param int $forumId
     * @return JsonResponse
     */
    public function store(Request $request, $forumId): JsonResponse
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

        $forum = Forum::where('id', $forumId)->first();
        if (!$forum) {
            return response()->json(['ok' => false, 'message' => 'Forum not found'], 404);
        }

        // Check if user has posted in this forum
        $hasPosted = ForumTopic::where('forum_id', $forumId)
            ->where('user_id', $userId)
            ->exists() || 
            ForumReply::whereHas('topic', function ($q) use ($forumId) {
                $q->where('forum_id', $forumId);
            })
            ->where('user_id', $userId)
            ->exists();

        $user = User::where('user_id', $userId)->first();

        return response()->json([
            'ok' => true,
            'message' => $hasPosted ? 'User has posted in this forum' : 'User has not posted in this forum',
            'has_posted' => $hasPosted,
            'data' => $user ? [
                'user_id' => $user->user_id,
                'username' => $user->username ?? null,
                'first_name' => $user->first_name ?? null,
                'last_name' => $user->last_name ?? null,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'avatar' => $user->avatar ?? null,
                'avatar_url' => $user->avatar_url ?? null,
                'verified' => isset($user->verified) ? ($user->verified === '1' || $user->verified === 1) : false,
                'active' => isset($user->active) ? ($user->active === '1' || $user->active === 1) : false,
            ] : null,
        ]);
    }

    /**
     * Not applicable in old WoWonder - forum members are users who posted, not a membership system
     * DELETE /forums/{forumId}/members/{memberId}
     * DELETE /forums/{forumId}/members
     * 
     * @param Request $request
     * @param int $forumId
     * @param int|null $memberId
     * @return JsonResponse
     */
    public function destroy(Request $request, $forumId, $memberId = null): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Forum membership is not managed - members are users who have posted in forums. To remove a user, delete their forum posts.',
        ], 400);
    }

    /**
     * Not applicable in old WoWonder - no role system for forum members
     * PUT/PATCH /forums/{forumId}/members/{memberId}
     * 
     * @param Request $request
     * @param int $forumId
     * @param int $memberId (user_id)
     * @return JsonResponse
     */
    public function update(Request $request, $forumId, $memberId): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Forum members do not have roles in the old WoWonder system. Members are simply users who have posted in forums.',
        ], 400);
    }

    /**
     * Get specific user's forum activity
     * GET /forums/{forumId}/members/{memberId} (memberId is user_id)
     * 
     * @param Request $request
     * @param int $forumId
     * @param int $memberId (user_id)
     * @return JsonResponse
     */
    public function show(Request $request, $forumId, $memberId): JsonResponse
    {
        $forum = Forum::where('id', $forumId)->first();
        if (!$forum) {
            return response()->json(['ok' => false, 'message' => 'Forum not found'], 404);
        }

        $user = User::where('user_id', $memberId)->first();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'User not found'], 404);
        }

        // Check if user has posted in this forum
        $topicsCount = ForumTopic::where('forum_id', $forumId)
            ->where('user_id', $memberId)
            ->where('active', '1')
            ->count();

        $repliesCount = ForumReply::whereHas('topic', function ($q) use ($forumId) {
                $q->where('forum_id', $forumId);
            })
            ->where('user_id', $memberId)
            ->where('active', '1')
            ->count();

        $forumPosts = $topicsCount + $repliesCount;

        return response()->json([
            'ok' => true,
            'data' => [
                'user_id' => $user->user_id,
                'username' => $user->username ?? null,
                'first_name' => $user->first_name ?? null,
                'last_name' => $user->last_name ?? null,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'avatar' => $user->avatar ?? null,
                'avatar_url' => $user->avatar_url ?? null,
                'verified' => isset($user->verified) ? ($user->verified === '1' || $user->verified === 1) : false,
                'active' => isset($user->active) ? ($user->active === '1' || $user->active === 1) : false,
                'forum_posts' => $forumPosts,
                'topics_count' => $topicsCount,
                'replies_count' => $repliesCount,
            ],
        ]);
    }

    /**
     * Check if user has posted in forum
     * GET /forums/{forumId}/members/check
     * 
     * @param Request $request
     * @param int $forumId
     * @return JsonResponse
     */
    public function check(Request $request, $forumId): JsonResponse
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

        $forum = Forum::where('id', $forumId)->first();
        if (!$forum) {
            return response()->json(['ok' => false, 'message' => 'Forum not found'], 404);
        }

        // Check if user has posted in this forum
        $hasPosted = ForumTopic::where('forum_id', $forumId)
            ->where('user_id', $userId)
            ->exists() || 
            ForumReply::whereHas('topic', function ($q) use ($forumId) {
                $q->where('forum_id', $forumId);
            })
            ->where('user_id', $userId)
            ->exists();

        $user = User::where('user_id', $userId)->first();

        return response()->json([
            'ok' => true,
            'has_posted' => $hasPosted,
            'is_member' => $hasPosted, // In old WoWonder, "member" = has posted
            'data' => $user ? [
                'user_id' => $user->user_id,
                'username' => $user->username ?? null,
                'first_name' => $user->first_name ?? null,
                'last_name' => $user->last_name ?? null,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'avatar' => $user->avatar ?? null,
                'avatar_url' => $user->avatar_url ?? null,
                'verified' => isset($user->verified) ? ($user->verified === '1' || $user->verified === 1) : false,
                'active' => isset($user->active) ? ($user->active === '1' || $user->active === 1) : false,
            ] : null,
        ]);
    }
}

