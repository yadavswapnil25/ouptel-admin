<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Forum;
use App\Models\ForumMember;
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

        $role = $request->query('role'); // Filter by role if provided
        $search = $request->query('search'); // Search by username or name

        $query = ForumMember::where('forum_id', $forumId)
            ->with(['user']);

        // Filter by role if provided
        if ($role) {
            $query->where('role', $role);
        }

        // Search by username or name
        if ($search) {
            $like = '%' . str_replace('%', '\\%', $search) . '%';
            $query->whereHas('user', function ($q) use ($like) {
                $q->where('username', 'like', $like)
                  ->orWhere('first_name', 'like', $like)
                  ->orWhere('last_name', 'like', $like);
            });
        }

        $paginator = $query->orderByDesc('time')->paginate($perPage);

        $data = $paginator->getCollection()->map(function (ForumMember $member) {
            $user = $member->user;
            return [
                'id' => $member->id,
                'forum_id' => $member->forum_id,
                'user_id' => $member->user_id,
                'role' => $member->role,
                'joined_at' => $member->time ? $member->time->toIso8601String() : null,
                'joined_at_timestamp' => $member->getTimeAsTimestampAttribute(),
                'user' => $user ? [
                    'user_id' => $user->user_id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    'avatar' => $user->avatar,
                    'avatar_url' => $user->avatar_url ?? null,
                    'verified' => $user->verified === '1',
                    'active' => $user->active === '1',
                ] : null,
            ];
        });

        return response()->json([
            'ok' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'forum_id' => $forumId,
            ],
        ]);
    }

    /**
     * Join forum (add member)
     * POST /forums/{forumId}/members
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

        // Check if user is already a member
        $existingMember = ForumMember::where('forum_id', $forumId)
            ->where('user_id', $userId)
            ->first();

        if ($existingMember) {
            return response()->json([
                'ok' => false,
                'message' => 'You are already a member of this forum',
                'data' => [
                    'id' => $existingMember->id,
                    'forum_id' => $existingMember->forum_id,
                    'user_id' => $existingMember->user_id,
                    'role' => $existingMember->role,
                ],
            ], 400);
        }

        // Create new member
        $member = new ForumMember();
        $member->forum_id = $forumId;
        $member->user_id = $userId;
        $member->role = $request->input('role', 'member'); // Default role is 'member'
        $member->time = time();
        $member->save();

        // Load user relationship
        $member->load('user');
        $user = $member->user;

        return response()->json([
            'ok' => true,
            'message' => 'Successfully joined forum',
            'data' => [
                'id' => $member->id,
                'forum_id' => $member->forum_id,
                'user_id' => $member->user_id,
                'role' => $member->role,
                'joined_at' => $member->time ? $member->time->toIso8601String() : null,
                'joined_at_timestamp' => $member->getTimeAsTimestampAttribute(),
                'user' => $user ? [
                    'user_id' => $user->user_id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    'avatar' => $user->avatar,
                    'avatar_url' => $user->avatar_url ?? null,
                    'verified' => $user->verified === '1',
                    'active' => $user->active === '1',
                ] : null,
            ],
        ], 201);
    }

    /**
     * Leave forum (remove member)
     * DELETE /forums/{forumId}/members/{memberId}
     * or
     * DELETE /forums/{forumId}/members (removes current user)
     * 
     * @param Request $request
     * @param int $forumId
     * @param int|null $memberId
     * @return JsonResponse
     */
    public function destroy(Request $request, $forumId, $memberId = null): JsonResponse
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

        // If memberId is provided, check if user has permission to remove that member
        if ($memberId) {
            $member = ForumMember::where('id', $memberId)
                ->where('forum_id', $forumId)
                ->first();

            if (!$member) {
                return response()->json(['ok' => false, 'message' => 'Member not found'], 404);
            }

            // Check if user is admin or the member themselves
            $currentUserMember = ForumMember::where('forum_id', $forumId)
                ->where('user_id', $userId)
                ->first();

            if (!$currentUserMember) {
                return response()->json(['ok' => false, 'message' => 'You are not a member of this forum'], 403);
            }

            // Only allow removal if user is admin or removing themselves
            if ($currentUserMember->role !== 'admin' && $member->user_id !== $userId) {
                return response()->json(['ok' => false, 'message' => 'You do not have permission to remove this member'], 403);
            }

            $member->delete();

            return response()->json([
                'ok' => true,
                'message' => 'Member removed successfully',
            ]);
        } else {
            // Remove current user from forum
            $member = ForumMember::where('forum_id', $forumId)
                ->where('user_id', $userId)
                ->first();

            if (!$member) {
                return response()->json(['ok' => false, 'message' => 'You are not a member of this forum'], 404);
            }

            $member->delete();

            return response()->json([
                'ok' => true,
                'message' => 'Successfully left forum',
            ]);
        }
    }

    /**
     * Update member role (admin only)
     * PUT/PATCH /forums/{forumId}/members/{memberId}
     * 
     * @param Request $request
     * @param int $forumId
     * @param int $memberId
     * @return JsonResponse
     */
    public function update(Request $request, $forumId, $memberId): JsonResponse
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

        // Check if current user is admin
        $currentUserMember = ForumMember::where('forum_id', $forumId)
            ->where('user_id', $userId)
            ->first();

        if (!$currentUserMember || $currentUserMember->role !== 'admin') {
            return response()->json(['ok' => false, 'message' => 'Only forum admins can update member roles'], 403);
        }

        $member = ForumMember::where('id', $memberId)
            ->where('forum_id', $forumId)
            ->first();

        if (!$member) {
            return response()->json(['ok' => false, 'message' => 'Member not found'], 404);
        }

        $validated = $request->validate([
            'role' => 'required|in:member,admin,moderator',
        ]);

        $member->role = $validated['role'];
        $member->save();

        $member->load('user');
        $user = $member->user;

        return response()->json([
            'ok' => true,
            'message' => 'Member role updated successfully',
            'data' => [
                'id' => $member->id,
                'forum_id' => $member->forum_id,
                'user_id' => $member->user_id,
                'role' => $member->role,
                'joined_at' => $member->time ? $member->time->toIso8601String() : null,
                'joined_at_timestamp' => $member->getTimeAsTimestampAttribute(),
                'user' => $user ? [
                    'user_id' => $user->user_id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    'avatar' => $user->avatar,
                    'avatar_url' => $user->avatar_url ?? null,
                    'verified' => $user->verified === '1',
                    'active' => $user->active === '1',
                ] : null,
            ],
        ]);
    }

    /**
     * Get specific member details
     * GET /forums/{forumId}/members/{memberId}
     * 
     * @param Request $request
     * @param int $forumId
     * @param int $memberId
     * @return JsonResponse
     */
    public function show(Request $request, $forumId, $memberId): JsonResponse
    {
        $forum = Forum::where('id', $forumId)->first();
        if (!$forum) {
            return response()->json(['ok' => false, 'message' => 'Forum not found'], 404);
        }

        $member = ForumMember::where('id', $memberId)
            ->where('forum_id', $forumId)
            ->with(['user'])
            ->first();

        if (!$member) {
            return response()->json(['ok' => false, 'message' => 'Member not found'], 404);
        }

        $user = $member->user;

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $member->id,
                'forum_id' => $member->forum_id,
                'user_id' => $member->user_id,
                'role' => $member->role,
                'joined_at' => $member->time ? $member->time->toIso8601String() : null,
                'joined_at_timestamp' => $member->getTimeAsTimestampAttribute(),
                'user' => $user ? [
                    'user_id' => $user->user_id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    'avatar' => $user->avatar,
                    'avatar_url' => $user->avatar_url ?? null,
                    'verified' => $user->verified === '1',
                    'active' => $user->active === '1',
                ] : null,
            ],
        ]);
    }

    /**
     * Check if user is a member of forum
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

        $member = ForumMember::where('forum_id', $forumId)
            ->where('user_id', $userId)
            ->with(['user'])
            ->first();

        if (!$member) {
            return response()->json([
                'ok' => true,
                'is_member' => false,
                'message' => 'User is not a member of this forum',
            ]);
        }

        $user = $member->user;

        return response()->json([
            'ok' => true,
            'is_member' => true,
            'data' => [
                'id' => $member->id,
                'forum_id' => $member->forum_id,
                'user_id' => $member->user_id,
                'role' => $member->role,
                'joined_at' => $member->time ? $member->time->toIso8601String() : null,
                'joined_at_timestamp' => $member->getTimeAsTimestampAttribute(),
                'user' => $user ? [
                    'user_id' => $user->user_id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    'avatar' => $user->avatar,
                    'avatar_url' => $user->avatar_url ?? null,
                    'verified' => $user->verified === '1',
                    'active' => $user->active === '1',
                ] : null,
            ],
        ]);
    }
}

