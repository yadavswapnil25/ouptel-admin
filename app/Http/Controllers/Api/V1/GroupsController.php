<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Group;
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
                ->where('user_id', $userId)
                ->where('active', 1)
                ->orderByDesc('id');
        } elseif ($type === 'joined_groups') {
            // Groups the user has joined
            $query = Group::query()
                ->whereHas('members', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->where('active', 1)
                ->orderByDesc('id');
        } elseif ($type === 'category') {
            // Groups by category
            $categoryId = $request->query('category');
            if (!$categoryId) {
                return response()->json(['ok' => false, 'message' => 'Category ID is required'], 400);
            }
            
            $query = Group::query()
                ->where('category', $categoryId)
                ->where('active', 1)
                ->orderByDesc('id');
        } elseif ($type === 'suggested') {
            // Suggested groups - groups user hasn't joined yet
            $joinedGroupIds = DB::table('Wo_GroupMembers')
                ->where('user_id', $userId)
                ->pluck('group_id')
                ->toArray();

            $query = Group::query()
                ->where('active', 1)
                ->where('user_id', '!=', $userId) // Not owned by user
                ->whereNotIn('id', $joinedGroupIds) // Not already joined
                ->orderByDesc('id')
                ->limit(50); // Limit suggestions
        } else {
            return response()->json(['ok' => false, 'message' => 'Invalid type. Use: my_groups, joined_groups, category, or suggested'], 400);
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Group $group) use ($userId) {
            // Count members
            $membersCount = DB::table('Wo_GroupMembers')
                ->where('group_id', $group->id)
                ->count();

            // Check if user is joined
            $isJoined = DB::table('Wo_GroupMembers')
                ->where('group_id', $group->id)
                ->where('user_id', $userId)
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
        $group->user_id = $userId;
        $group->active = 1;
        $group->time = time();
        $group->save();

        // Add creator as first member
        DB::table('Wo_GroupMembers')->insert([
            'group_id' => $group->id,
            'user_id' => $userId,
            'time' => time(),
        ]);

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
                'created_at' => $group->time ? date('c', (int) $group->time) : null,
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
}
