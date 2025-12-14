<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use App\Models\GroupCategory;
use App\Models\GroupSubCategory;

class GroupsController extends BaseController
{
    public function index(Request $request): JsonResponse
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

        $type = $request->query('type', 'my_groups'); // my_groups, joined_groups, category, suggested

        if ($type === 'my_groups') {
            // Groups owned by the user
            $query = Group::query()
                ->where('user_id', (string) $userId)
                ->where('active', '1')
                ->orderByDesc('id');
        } elseif ($type === 'joined_groups') {
            // Groups the user has joined
            $joinedGroupIds = DB::table('Wo_Group_Members')
                ->where('user_id', (string) $userId)
                ->where('active', '1')
                ->pluck('group_id');
            $query = Group::query()
                ->whereIn('id', $joinedGroupIds)
                ->where('active', '1')
                ->orderByDesc('id');
        } elseif ($type === 'category') {
            // Groups by category
            $categoryId = $request->query('category');
            if (!$categoryId) {
                return response()->json(['ok' => false, 'message' => 'Category ID is required'], 400);
            }
            
            $query = Group::query()
                ->where('category', $categoryId)
                ->where('active', '1')
                ->orderByDesc('id');
        } elseif ($type === 'suggested') {
            // Suggested groups - groups user hasn't joined yet
            $joinedGroupIds = DB::table('Wo_Group_Members')
                ->where('user_id', (string) $userId)
                ->pluck('group_id')
                ->toArray();
            $query = Group::query()
                ->where('active', '1')
                ->where('user_id', '!=', (string) $userId) // Not owned by user
                ->whereNotIn('id', $joinedGroupIds)
                ->orderByDesc('id')
                ->limit(50); // Limit suggestions
        } else {
            return response()->json(['ok' => false, 'message' => 'Invalid type. Use: my_groups, joined_groups, category, or suggested'], 400);
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Group $group) use ($userId) {
            // Get member count and join status
            $membersCount = DB::table('Wo_Group_Members')
                ->where('group_id', $group->id)
                ->where('active', '1')
                ->count();
            
            $isJoined = DB::table('Wo_Group_Members')
                ->where('group_id', $group->id)
                ->where('user_id', $userId)
                ->where('active', '1')
                ->exists();

            return [
                'id' => $group->id,
                'group_name' => $group->group_name,
                'group_title' => $group->group_title,
                'about' => $group->about,
                'category' => $group->category,
                'privacy' => $group->privacy,
                'avatar_url' => $group->avatar_url,
                'cover_url' => $group->cover_url,
                'members_count' => $membersCount,
                'is_joined' => $isJoined,
                'is_owner' => $group->user_id == $userId,
                'created_at' => $group->time ? $group->time->toIso8601String() : null,
                'owner' => [
                    'user_id' => optional($group->user)->user_id,
                    'username' => optional($group->user)->username,
                    'avatar_url' => optional($group->user)->avatar_url,
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
            'group_name' => ['required', 'string', 'max:32'],
            'group_title' => ['required', 'string', 'max:100'],
            'about' => ['nullable', 'string', 'max:500'],
            'category' => ['required', 'integer'],
            'sub_category' => ['nullable', 'integer'],
            'privacy' => ['required', 'in:public,private'],
            'join_privacy' => ['required', 'in:public,private'],
        ]);

        // Check if group name is already taken
        $existingGroup = Group::where('group_name', $validated['group_name'])->first();
        if ($existingGroup) {
            return response()->json(['ok' => false, 'message' => 'Group name is already taken'], 400);
        }

        // Create the group
        $group = new Group();
        $group->group_name = $validated['group_name'];
        $group->group_title = $validated['group_title'];
        $group->about = $validated['about'] ?? '';
        $group->category = $validated['category'];
        $group->sub_category = $validated['sub_category'] ?? 0;
        $group->privacy = $validated['privacy'];
        $group->join_privacy = $validated['join_privacy'];
        $group->user_id = (string) $userId;
        $group->active = '1';
        $group->time = (string) time();
        $group->save();

        // Add creator as member
        if (DB::getSchemaBuilder()->hasTable('Wo_Group_Members')) {
            DB::table('Wo_Group_Members')->insert([
                'group_id' => $group->id,
                'user_id' => (string) $userId,
                'active' => '1',
                'time' => time(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Group created successfully',
            'data' => [
                'id' => $group->id,
                'group_name' => $group->group_name,
                'group_title' => $group->group_title,
                'about' => $group->about,
                'category' => $group->category,
                'privacy' => $group->privacy,
                'join_privacy' => $group->join_privacy,
                'avatar_url' => $group->avatar_url,
                'cover_url' => $group->cover_url,
                'members_count' => 1,
                'is_joined' => true,
                'is_owner' => true,
                'created_at' => $group->time ? date('c', $group->time_as_timestamp) : null,
            ],
        ], 201);
    }

    public function meta(Request $request): JsonResponse
    {
        // Public metadata; no auth required
        $categories = GroupCategory::query()
            ->orderBy('id')
            ->get()
            ->map(fn (GroupCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]);

        $categoryId = $request->query('category_id');
        $subsQuery = GroupSubCategory::query();
        if (!empty($categoryId)) {
            $subsQuery->where('category_id', (int) $categoryId);
        }
        $subCategories = $subsQuery
            ->orderBy('id')
            ->get()
            ->map(fn (GroupSubCategory $s) => [
                'id' => $s->id,
                'category_id' => $s->category_id,
                'name' => $s->name,
            ]);

        return response()->json([
            'ok' => true,
            'data' => [
                'types' => [
                    'privacy' => ['public', 'private'],
                    'join_privacy' => ['public', 'private'],
                ],
                'categories' => $categories,
                'sub_categories' => $subCategories,
            ],
        ]);
    }

    public function joinGroup(Request $request): JsonResponse
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

        // Validate group_id
        $validated = $request->validate([
            'group_id' => ['required', 'integer'],
        ]);

        $groupId = $validated['group_id'];

        // Check if group exists
        $group = Group::find($groupId);
        if (!$group) {
            return response()->json([
                'ok' => false,
                'message' => 'Group not found',
            ], 404);
        }

        // Check if user is the owner
        if ($group->user_id == $userId) {
            return response()->json([
                'ok' => false,
                'message' => 'You are the owner of this group',
            ], 400);
        }

        // Check if group is active
        if ($group->active != '1') {
            return response()->json([
                'ok' => false,
                'message' => 'Group is not active',
            ], 400);
        }

        // Check if Wo_Group_Members table exists (with underscore, matching old code)
        $groupMembersTableExists = DB::getSchemaBuilder()->hasTable('Wo_Group_Members');
        
        if (!$groupMembersTableExists) {
            return response()->json([
                'ok' => false,
                'message' => 'Group members table does not exist. Please contact administrator.',
            ], 500);
        }

        // Check if user is already a member (active = '1')
        $isMember = DB::table('Wo_Group_Members')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('active', '1')
            ->exists();

        // Check if user has a pending join request (active = '0')
        $hasJoinRequest = DB::table('Wo_Group_Members')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('active', '0')
            ->exists();

        $joinStatus = 'invalid';

        // If user is already a member or has requested to join, leave the group
        if ($isMember || $hasJoinRequest) {
            // Remove from members table (deletes both joined and requested entries)
            DB::table('Wo_Group_Members')
                ->where('group_id', $groupId)
                ->where('user_id', $userId)
                ->delete();

            $joinStatus = 'left';
        } else {
            // Check if join_privacy is private
            // The Group model accessor converts database values: '1' = 'public', '0' = 'private'
            // Also check raw database value for compatibility with old system (where '2' = private)
            $rawJoinPrivacy = DB::table('Wo_Groups')->where('id', $groupId)->value('join_privacy');
            $isPrivate = ($rawJoinPrivacy == '0' || $rawJoinPrivacy == 0 || $rawJoinPrivacy == '2' || $rawJoinPrivacy == 2 || $group->join_privacy === 'private');

            // Set active based on privacy: '1' for joined (public), '0' for requested (private)
            $active = $isPrivate ? '0' : '1';
            $joinStatus = $isPrivate ? 'requested' : 'joined';

            // Insert into Wo_Group_Members table with appropriate active value
            DB::table('Wo_Group_Members')->insert([
                'group_id' => $groupId,
                'user_id' => $userId,
                'active' => $active,
                'time' => time(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'join_status' => $joinStatus,
            'data' => [
                'join' => $joinStatus === 'left' ? 'left' : ($joinStatus === 'requested' ? 'requested' : 'joined'),
            ],
        ]);
    }
}
