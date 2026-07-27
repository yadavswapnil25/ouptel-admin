<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Models\GroupCategory;
use App\Models\GroupSubCategory;
use App\Models\CommunityPreference;

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
            // Member count excludes the group owner
            $membersCount = $this->countGroupMembersExcludingOwner($group);
            
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
                'allowed_gender' => Schema::hasColumn('Wo_Groups', 'allowed_gender')
                    ? ($group->allowed_gender ?: 'all')
                    : 'all',
                'community_preferences' => Schema::hasColumn('Wo_Groups', 'community_preferences')
                    ? $this->parseCommunityPreferences($group->community_preferences ?? '')
                    : [],
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

        $agreementAccepted = $request->input('agreement_accepted', $request->input('agreed_to_terms', false));
        $agreementAcceptedNormalized = filter_var($agreementAccepted, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($agreementAcceptedNormalized !== true) {
            return response()->json([
                'ok' => false,
                'message' => 'Please agree to Ouptel\'s Group Terms & Community Guidelines',
            ], 400);
        }

        $validated = $request->validate([
            'group_name' => ['required', 'string', 'max:32'],
            'group_title' => ['required', 'string', 'max:100'],
            'about' => ['nullable', 'string', 'max:500'],
            'category' => ['required', 'integer'],
            'sub_category' => ['nullable', 'integer'],
            'privacy' => ['required', 'in:public,private'],
            'join_privacy' => ['required', 'in:public,private'],
            'allowed_gender' => ['nullable', 'in:all,male,female,other'],
            'community_preferences' => ['nullable', 'array'],
            'community_preferences.*' => ['integer', 'exists:community_preferences,id'],
            'avatar' => ['sometimes', 'image', 'max:10240'],
            'cover' => ['sometimes', 'image', 'max:10240'],
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
        if (Schema::hasColumn('Wo_Groups', 'allowed_gender')) {
            $group->allowed_gender = $validated['allowed_gender'] ?? 'all';
        }
        if (Schema::hasColumn('Wo_Groups', 'community_preferences')) {
            $group->community_preferences = $this->normalizeCommunityPreferences(
                $validated['community_preferences'] ?? []
            );
        }
        $group->user_id = (string) $userId;
        $group->active = '1';
        $group->time = (string) time();
        if (Schema::hasColumn('Wo_Groups', 'agreement_accepted')) {
            $group->setAttribute('agreement_accepted', true);
        }
        if (Schema::hasColumn('Wo_Groups', 'agreement_accepted_at')) {
            $group->setAttribute('agreement_accepted_at', now());
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('upload/photos/' . date('Y/m'), 'public');
            $group->avatar = $path;
        }
        if ($request->hasFile('cover')) {
            $path = $request->file('cover')->store('upload/photos/' . date('Y/m'), 'public');
            $group->cover = $path;
        }

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
                'allowed_gender' => Schema::hasColumn('Wo_Groups', 'allowed_gender')
                    ? ($group->allowed_gender ?: 'all')
                    : 'all',
                'community_preferences' => Schema::hasColumn('Wo_Groups', 'community_preferences')
                    ? $this->parseCommunityPreferences($group->community_preferences ?? '')
                    : [],
                'avatar_url' => $group->avatar_url,
                'cover_url' => $group->cover_url,
                'members_count' => 0,
                'is_joined' => true,
                'is_owner' => true,
                'created_at' => $group->time ? date('c', $group->time_as_timestamp) : null,
            ],
        ], 201);
    }

    /**
     * Get single group by ID
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, $id): JsonResponse
    {
        // Auth is optional - public groups can be viewed without auth
        $tokenUserId = null;
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        }

        // Find the group
        $group = Group::find($id);
        
        if (!$group) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Group not found',
                ],
            ], 404);
        }

        // Check if group is active (unless user is the owner)
        if ($group->active != '1' && $group->active != 1) {
            if (!$tokenUserId || $group->user_id != $tokenUserId) {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 4,
                        'error_text' => 'Group not found',
                    ],
                ], 404);
            }
        }

        // Get member count
        $membersCount = 0;
        $isJoined = false;
        $isPending = false;
        $isAdmin = false;
        
        if (Schema::hasTable('Wo_Group_Members')) {
            try {
                // Member count excludes the group owner
                $membersCount = $this->countGroupMembersExcludingOwner($group);
                
                if ($tokenUserId) {
                    // Convert tokenUserId to string to match database type
                    $tokenUserIdStr = (string) $tokenUserId;
                    
                    // Check if user is joined (active = '1')
                    $isJoined = DB::table('Wo_Group_Members')
                        ->where('group_id', $id)
                        ->where(function($query) use ($tokenUserIdStr) {
                            $query->where('user_id', $tokenUserIdStr)
                                  ->orWhere('user_id', (int) $tokenUserIdStr);
                        })
                        ->where('active', '1')
                        ->exists();
                    
                    // Also check if user is the group owner (owners are automatically "joined")
                    if (!$isJoined && $group->user_id) {
                        $groupOwnerId = (string) $group->user_id;
                        if ($groupOwnerId == $tokenUserIdStr || (int) $groupOwnerId == (int) $tokenUserIdStr) {
                            $isJoined = true;
                        }
                    }
                    
                    // Check if user has pending join request (active = '0')
                    $isPending = DB::table('Wo_Group_Members')
                        ->where('group_id', $id)
                        ->where(function($query) use ($tokenUserIdStr) {
                            $query->where('user_id', $tokenUserIdStr)
                                  ->orWhere('user_id', (int) $tokenUserIdStr);
                        })
                        ->where('active', '0')
                        ->exists();
                    
                    // Check if user is admin
                    if (Schema::hasTable('Wo_GroupAdmins')) {
                        $isAdmin = DB::table('Wo_GroupAdmins')
                            ->where('group_id', $id)
                            ->where(function($query) use ($tokenUserIdStr) {
                                $query->where('user_id', $tokenUserIdStr)
                                      ->orWhere('user_id', (int) $tokenUserIdStr);
                            })
                            ->exists();
                    }
                }
            } catch (\Exception $e) {
                // If query fails, continue with default values
            }
        }

        // Get category information
        $category = null;
        if ($group->category) {
            $categoryModel = GroupCategory::find($group->category);
            if ($categoryModel) {
                $category = [
                    'id' => $categoryModel->id,
                    'name' => $categoryModel->name,
                ];
            }
        }

        // Get sub-category information
        $subCategory = null;
        if ($group->sub_category && $group->sub_category > 0) {
            $subCategoryModel = GroupSubCategory::find($group->sub_category);
            if ($subCategoryModel) {
                $subCategory = [
                    'id' => $subCategoryModel->id,
                    'category_id' => $subCategoryModel->category_id,
                    'name' => $subCategoryModel->name,
                ];
            }
        }

        // Get owner/user information
        $owner = null;
        if ($group->user) {
            $owner = [
                'user_id' => $group->user->user_id,
                'username' => $group->user->username ?? 'Unknown',
                'name' => $group->user->name ?? $group->user->username ?? 'Unknown User',
                'avatar' => $group->user->avatar ?? '',
                'avatar_url' => $group->user->avatar ? asset('storage/' . $group->user->avatar) : null,
                'verified' => (bool) ($group->user->verified ?? false),
            ];
        }

        // Format response
        $response = [
            'api_status' => 200,
            'api_text' => 'success',
            'api_version' => '1.0',
            'data' => [
                'id' => $group->id,
                'group_name' => $group->group_name,
                'group_title' => $group->group_title,
                'about' => $group->about ?? '',
                'category' => $category,
                'category_id' => $group->category,
                'sub_category' => $subCategory,
                'sub_category_id' => $group->sub_category ?? 0,
                'privacy' => $group->privacy,
                'privacy_text' => ucfirst($group->privacy),
                'join_privacy' => $group->join_privacy,
                'join_privacy_text' => ucfirst($group->join_privacy),
                'allowed_gender' => Schema::hasColumn('Wo_Groups', 'allowed_gender')
                    ? ($group->allowed_gender ?: 'all')
                    : 'all',
                'community_preferences' => Schema::hasColumn('Wo_Groups', 'community_preferences')
                    ? $this->parseCommunityPreferences($group->community_preferences ?? '')
                    : [],
                'avatar' => $group->avatar ?? '',
                'avatar_url' => $group->avatar_url,
                'cover' => $group->cover ?? '',
                'cover_url' => $group->cover_url,
                'members_count' => $membersCount,
                'is_joined' => $isJoined,
                'is_pending' => $isPending,
                'is_admin' => $isAdmin,
                'is_owner' => $tokenUserId && $group->user_id == $tokenUserId,
                'active' => (bool) ($group->active == '1' || $group->active == 1),
                'created_at' => $group->time ? date('c', $group->time_as_timestamp) : null,
                'created_at_timestamp' => $group->time_as_timestamp,
                'owner' => $owner,
            ],
        ];

        return response()->json($response);
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
                    'allowed_gender' => ['all', 'male', 'female', 'other'],
                ],
                'community_preferences' => Schema::hasTable('community_preferences')
                    ? CommunityPreference::query()
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get(['id', 'name', 'slug', 'description'])
                    : [],
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

        // Convert userId to string for consistent comparison
        $userIdStr = (string) $userId;
        
        // Check if user is already a member (active = '1')
        // Handle both string and integer user_id types
        $isMember = DB::table('Wo_Group_Members')
            ->where('group_id', $groupId)
            ->where(function($query) use ($userIdStr) {
                $query->where('user_id', $userIdStr)
                      ->orWhere('user_id', (int) $userIdStr);
            })
            ->where('active', '1')
            ->exists();

        // Check if user has a pending join request (active = '0')
        $hasJoinRequest = DB::table('Wo_Group_Members')
            ->where('group_id', $groupId)
            ->where(function($query) use ($userIdStr) {
                $query->where('user_id', $userIdStr)
                      ->orWhere('user_id', (int) $userIdStr);
            })
            ->where('active', '0')
            ->exists();

        $joinStatus = 'invalid';

        // If user is already a member or has requested to join, leave the group
        if ($isMember || $hasJoinRequest) {
            $leaveReason = trim((string) $request->input('leave_reason', ''));
            if ($isMember && $leaveReason !== '' && Schema::hasTable('Wo_Group_Leave_Reasons')) {
                DB::table('Wo_Group_Leave_Reasons')->insert([
                    'group_id' => $groupId,
                    'user_id' => (string) $userId,
                    'reason' => mb_substr($leaveReason, 0, 500),
                    'time' => time(),
                ]);
            }

            // Remove from members table (deletes both joined and requested entries)
            // Handle both string and integer user_id types
            DB::table('Wo_Group_Members')
                ->where('group_id', $groupId)
                ->where(function($query) use ($userIdStr) {
                    $query->where('user_id', $userIdStr)
                          ->orWhere('user_id', (int) $userIdStr);
                })
                ->delete();

            $joinStatus = 'left';
        } else {
            // Age group restriction (admin-set): based on user birthday / DOB
            $ageCheck = $this->assertUserMeetsGroupAgeRequirement($group, $userIdStr);
            if ($ageCheck !== true) {
                return response()->json([
                    'ok' => false,
                    'message' => $ageCheck,
                ], 403);
            }

            $genderCheck = $this->assertUserMeetsAllowedGenderRequirement($group, $userIdStr);
            if ($genderCheck !== true) {
                return response()->json([
                    'ok' => false,
                    'message' => $genderCheck,
                ], 403);
            }

            $communityPreferenceCheck = $this->assertUserMeetsCommunityPreferenceRequirement($group, $userIdStr);
            if ($communityPreferenceCheck !== true) {
                return response()->json([
                    'ok' => false,
                    'message' => $communityPreferenceCheck,
                ], 403);
            }

            // Check if join_privacy is private
            // The Group model accessor converts database values: '1' = 'public', '0' = 'private'
            // Also check raw database value for compatibility with old system (where '2' = private)
            $rawJoinPrivacy = DB::table('Wo_Groups')->where('id', $groupId)->value('join_privacy');
            $isPrivate = ($rawJoinPrivacy == '0' || $rawJoinPrivacy == 0 || $rawJoinPrivacy == '2' || $rawJoinPrivacy == 2 || $group->join_privacy === 'private');

            // Set active based on privacy: '1' for joined (public), '0' for requested (private)
            $active = $isPrivate ? '0' : '1';
            $joinStatus = $isPrivate ? 'requested' : 'joined';

            // Insert into Wo_Group_Members table with appropriate active value
            // Ensure user_id is stored consistently (as string to match database type)
            DB::table('Wo_Group_Members')->insert([
                'group_id' => $groupId,
                'user_id' => (string) $userId,
                'active' => $active,
                'time' => time(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'join_status' => $joinStatus,
            'message' => match ($joinStatus) {
                'joined' => 'Joined group successfully',
                'requested' => 'Join request sent',
                'left' => 'Left group successfully',
                default => 'OK',
            },
            'data' => [
                'join' => $joinStatus === 'left' ? 'left' : ($joinStatus === 'requested' ? 'requested' : 'joined'),
                'is_joined' => $joinStatus === 'joined',
                'is_pending' => $joinStatus === 'requested',
            ],
        ]);
    }

    /**
     * Get posts for a group feed.
     */
    public function getPosts(Request $request, $id): JsonResponse
    {
        $tokenUserId = $this->resolveUserId($request);
        $group = Group::find($id);

        if (!$group || !$this->isGroupActiveForUser($group, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Group not found'], 404);
        }

        if (!$this->canViewGroup($group, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'You must be a member to view this group'], 403);
        }

        $perPage = max(1, min((int) $request->query('per_page', 10), 50));
        $pageNum = max(1, (int) $request->query('page', 1));
        $offset = ($pageNum - 1) * $perPage;

        $query = DB::table('Wo_Posts')
            ->where('group_id', $id)
            ->where('active', '1')
            ->orderByDesc('time');

        $total = (clone $query)->count();
        $posts = $query->offset($offset)->limit($perPage)->get();

        $formatter = app(PagesController::class);
        $formattedPosts = $posts->map(
            fn ($post) => $formatter->formatPostRowForApi($post, $tokenUserId)
        )->values()->all();

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return response()->json([
            'ok' => true,
            'data' => [
                'group_id' => (int) $id,
                'posts' => $formattedPosts,
                'pagination' => [
                    'current_page' => $pageNum,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'has_more' => $pageNum < $lastPage,
                ],
            ],
        ]);
    }

    /**
     * List group members or pending join requests.
     */
    public function getMembers(Request $request, $id): JsonResponse
    {
        $tokenUserId = $this->resolveUserId($request);
        $group = Group::find($id);

        if (!$group || !$this->isGroupActiveForUser($group, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Group not found'], 404);
        }

        if (!$this->canViewGroup($group, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'You must be a member to view members'], 403);
        }

        if (!Schema::hasTable('Wo_Group_Members')) {
            return response()->json(['ok' => true, 'data' => []]);
        }

        $status = $request->query('status', 'active');
        $activeValue = $status === 'pending' ? '0' : '1';

        $rows = DB::table('Wo_Group_Members')
            ->where('group_id', $id)
            ->where('active', $activeValue)
            ->orderByDesc('time')
            ->get();

        $adminIds = [];
        if (Schema::hasTable('Wo_GroupAdmins')) {
            $adminIds = DB::table('Wo_GroupAdmins')
                ->where('group_id', $id)
                ->pluck('user_id')
                ->map(fn ($uid) => (string) $uid)
                ->all();
        }

        $ownerId = (string) $group->user_id;
        $viewerId = $tokenUserId ? (string) $tokenUserId : '';
        $memberIds = $rows->map(fn ($row) => (string) $row->user_id)->unique()->values()->all();

        $followingIds = [];
        $followPendingIds = [];
        $friendIds = [];
        $friendPendingIds = [];

        if ($viewerId !== '' && $memberIds !== []) {
            if (Schema::hasTable('Wo_Followers')) {
                $followingIds = DB::table('Wo_Followers')
                    ->where('follower_id', $viewerId)
                    ->whereIn('following_id', $memberIds)
                    ->whereIn('active', ['1', 1])
                    ->pluck('following_id')
                    ->map(fn ($id) => (string) $id)
                    ->all();

                $followPendingIds = DB::table('Wo_Followers')
                    ->where('follower_id', $viewerId)
                    ->whereIn('following_id', $memberIds)
                    ->whereIn('active', ['0', 0])
                    ->pluck('following_id')
                    ->map(fn ($id) => (string) $id)
                    ->all();
            }

            if (Schema::hasTable('Wo_Friends')) {
                try {
                    if (Schema::hasColumn('Wo_Friends', 'user_id') && Schema::hasColumn('Wo_Friends', 'friend_id')) {
                        $friendIds = DB::table('Wo_Friends')
                            ->where('user_id', $viewerId)
                            ->whereIn('friend_id', $memberIds)
                            ->whereIn('status', ['2', 2])
                            ->pluck('friend_id')
                            ->merge(
                                DB::table('Wo_Friends')
                                    ->where('friend_id', $viewerId)
                                    ->whereIn('user_id', $memberIds)
                                    ->whereIn('status', ['2', 2])
                                    ->pluck('user_id')
                            )
                            ->map(fn ($id) => (string) $id)
                            ->unique()
                            ->values()
                            ->all();

                        $friendPendingIds = DB::table('Wo_Friends')
                            ->where('user_id', $viewerId)
                            ->whereIn('friend_id', $memberIds)
                            ->whereIn('status', ['0', 0])
                            ->pluck('friend_id')
                            ->map(fn ($id) => (string) $id)
                            ->all();
                    } elseif (Schema::hasColumn('Wo_Friends', 'from_id') && Schema::hasColumn('Wo_Friends', 'to_id')) {
                        $friendIds = DB::table('Wo_Friends')
                            ->where('from_id', $viewerId)
                            ->whereIn('to_id', $memberIds)
                            ->whereIn('status', ['2', 2])
                            ->pluck('to_id')
                            ->merge(
                                DB::table('Wo_Friends')
                                    ->where('to_id', $viewerId)
                                    ->whereIn('from_id', $memberIds)
                                    ->whereIn('status', ['2', 2])
                                    ->pluck('from_id')
                            )
                            ->map(fn ($id) => (string) $id)
                            ->unique()
                            ->values()
                            ->all();

                        $friendPendingIds = DB::table('Wo_Friends')
                            ->where('from_id', $viewerId)
                            ->whereIn('to_id', $memberIds)
                            ->whereIn('status', ['0', 0])
                            ->pluck('to_id')
                            ->map(fn ($id) => (string) $id)
                            ->all();
                    }
                } catch (\Throwable $e) {
                    $friendIds = [];
                    $friendPendingIds = [];
                }
            }
        }

        $followingSet = array_fill_keys($followingIds, true);
        $followPendingSet = array_fill_keys($followPendingIds, true);
        $friendSet = array_fill_keys($friendIds, true);
        $friendPendingSet = array_fill_keys($friendPendingIds, true);

        $members = $rows->map(function ($row) use ($ownerId, $adminIds, $viewerId, $followingSet, $followPendingSet, $friendSet, $friendPendingSet) {
            $user = DB::table('Wo_Users')->where('user_id', $row->user_id)->first();
            $userIdStr = (string) $row->user_id;
            $isSelf = $viewerId !== '' && $viewerId === $userIdStr;
            $isFollowing = !$isSelf && isset($followingSet[$userIdStr]);
            $isFollowPending = !$isSelf && isset($followPendingSet[$userIdStr]);
            $isFriend = !$isSelf && isset($friendSet[$userIdStr]);
            $friendRequestSent = !$isSelf && isset($friendPendingSet[$userIdStr]);

            return [
                'user_id' => $userIdStr,
                'username' => $user->username ?? 'Unknown',
                'name' => $user->name ?? $user->username ?? 'Unknown User',
                'avatar_url' => !empty($user->avatar) ? asset('storage/' . $user->avatar) : null,
                'joined_at' => is_numeric($row->time) ? (int) $row->time : null,
                'is_owner' => $userIdStr === $ownerId,
                'is_admin' => in_array($userIdStr, $adminIds, true) || $userIdStr === $ownerId,
                'status' => $row->active === '1' || $row->active === 1 ? 'active' : 'pending',
                'is_self' => $isSelf,
                'is_following' => $isFollowing,
                'follow_pending' => $isFollowPending,
                'is_friend' => $isFriend,
                'friend_request_sent' => $friendRequestSent,
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'data' => $members,
            'meta' => [
                'status' => $status,
                'count' => $members->count(),
            ],
        ]);
    }

    /**
     * Approve a pending join request.
     */
    public function approveMember(Request $request, $id, $userId): JsonResponse
    {
        $tokenUserId = $this->resolveUserId($request);
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $group = Group::find($id);
        if (!$group || !$this->canManageGroup($group, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Permission denied'], 403);
        }

        $updated = DB::table('Wo_Group_Members')
            ->where('group_id', $id)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', (string) $userId)->orWhere('user_id', (int) $userId);
            })
            ->where('active', '0')
            ->update(['active' => '1']);

        if (!$updated) {
            return response()->json(['ok' => false, 'message' => 'No pending request found'], 404);
        }

        return response()->json(['ok' => true, 'message' => 'Member approved']);
    }

    /**
     * Reject a pending join request.
     */
    public function rejectMember(Request $request, $id, $userId): JsonResponse
    {
        $tokenUserId = $this->resolveUserId($request);
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $group = Group::find($id);
        if (!$group || !$this->canManageGroup($group, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Permission denied'], 403);
        }

        $deleted = DB::table('Wo_Group_Members')
            ->where('group_id', $id)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', (string) $userId)->orWhere('user_id', (int) $userId);
            })
            ->where('active', '0')
            ->delete();

        if (!$deleted) {
            return response()->json(['ok' => false, 'message' => 'No pending request found'], 404);
        }

        return response()->json(['ok' => true, 'message' => 'Request declined']);
    }

    /**
     * Remove an active member from the group.
     */
    public function removeMember(Request $request, $id, $userId): JsonResponse
    {
        $tokenUserId = $this->resolveUserId($request);
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $group = Group::find($id);
        if (!$group || !$this->canManageGroup($group, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Permission denied'], 403);
        }

        if ((string) $group->user_id === (string) $userId) {
            return response()->json(['ok' => false, 'message' => 'Cannot remove the group owner'], 400);
        }

        $deleted = DB::table('Wo_Group_Members')
            ->where('group_id', $id)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', (string) $userId)->orWhere('user_id', (int) $userId);
            })
            ->delete();

        if (!$deleted) {
            return response()->json(['ok' => false, 'message' => 'Member not found'], 404);
        }

        if (Schema::hasTable('Wo_GroupAdmins')) {
            DB::table('Wo_GroupAdmins')
                ->where('group_id', $id)
                ->where(function ($q) use ($userId) {
                    $q->where('user_id', (string) $userId)->orWhere('user_id', (int) $userId);
                })
                ->delete();
        }

        return response()->json(['ok' => true, 'message' => 'Member removed']);
    }

    /**
     * Invite users to join a group (notification only; no auto-join).
     */
    public function inviteMembers(Request $request, $id): JsonResponse
    {
        $tokenUserId = $this->resolveUserId($request);
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:20'],
            'user_ids.*' => ['integer', 'distinct'],
        ]);

        $group = Group::find($id);
        if (!$group || !$this->isGroupActiveForUser($group, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Group not found'], 404);
        }

        if (!$this->canInviteToGroup($group, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'You must be an active member to invite people'], 403);
        }

        $groupId = (int) $group->id;
        $ownerId = (string) $group->user_id;
        $invited = [];
        $skipped = [];

        foreach ($validated['user_ids'] as $targetUserId) {
            $targetId = (string) $targetUserId;

            if ($targetId === $tokenUserId) {
                $skipped[] = ['user_id' => $targetId, 'reason' => 'self'];
                continue;
            }

            if ($targetId === $ownerId) {
                $skipped[] = ['user_id' => $targetId, 'reason' => 'group_owner'];
                continue;
            }

            $targetUser = DB::table('Wo_Users')
                ->where('user_id', $targetId)
                ->where('active', '1')
                ->first();

            if (!$targetUser) {
                $skipped[] = ['user_id' => $targetId, 'reason' => 'invalid_user'];
                continue;
            }

            if ($this->isUserBlocked($tokenUserId, $targetId)) {
                $skipped[] = ['user_id' => $targetId, 'reason' => 'blocked'];
                continue;
            }

            if ($this->isActiveMember($groupId, $targetId) || $this->isGroupOwner($group, $targetId)) {
                $skipped[] = ['user_id' => $targetId, 'reason' => 'already_member'];
                continue;
            }

            if ($this->hasPendingJoinRequest($groupId, $targetId)) {
                $skipped[] = ['user_id' => $targetId, 'reason' => 'pending_request'];
                continue;
            }

            if ($this->sendGroupInviteNotification($tokenUserId, $targetId, $groupId)) {
                $invited[] = $targetId;
            } else {
                $skipped[] = ['user_id' => $targetId, 'reason' => 'already_invited'];
            }
        }

        $count = count($invited);
        $message = $count === 1
            ? '1 invite sent'
            : "{$count} invite(s) sent";

        return response()->json([
            'ok' => true,
            'message' => $message,
            'data' => [
                'invited' => $invited,
                'skipped' => $skipped,
            ],
        ]);
    }

    /**
     * Update group settings (owner or admin).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $tokenUserId = $this->resolveUserId($request);
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $group = Group::find($id);
        if (!$group) {
            return response()->json(['ok' => false, 'message' => 'Group not found'], 404);
        }

        if (!$this->canManageGroup($group, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Permission denied'], 403);
        }

        $validated = $request->validate([
            'group_title' => ['sometimes', 'string', 'max:100'],
            'about' => ['sometimes', 'nullable', 'string', 'max:500'],
            'privacy' => ['sometimes', 'in:public,private'],
            'join_privacy' => ['sometimes', 'in:public,private'],
            'allowed_gender' => ['sometimes', 'nullable', 'in:all,male,female,other'],
            'community_preferences' => ['sometimes', 'nullable', 'array'],
            'community_preferences.*' => ['integer', 'exists:community_preferences,id'],
            'category' => ['sometimes', 'integer'],
            'sub_category' => ['sometimes', 'nullable', 'integer'],
            'avatar' => ['sometimes', 'image', 'max:10240'],
            'cover' => ['sometimes', 'image', 'max:10240'],
        ]);

        if (isset($validated['group_title'])) {
            $group->group_title = $validated['group_title'];
        }
        if (array_key_exists('about', $validated)) {
            $group->about = $validated['about'] ?? '';
        }
        if (isset($validated['privacy'])) {
            $group->privacy = $validated['privacy'];
        }
        if (isset($validated['join_privacy'])) {
            $group->join_privacy = $validated['join_privacy'];
        }
        if (array_key_exists('allowed_gender', $validated) && Schema::hasColumn('Wo_Groups', 'allowed_gender')) {
            $group->allowed_gender = $validated['allowed_gender'] ?? 'all';
        }
        if (array_key_exists('community_preferences', $validated) && Schema::hasColumn('Wo_Groups', 'community_preferences')) {
            $group->community_preferences = $this->normalizeCommunityPreferences(
                $validated['community_preferences'] ?? []
            );
        }
        if (isset($validated['category'])) {
            $group->category = $validated['category'];
        }
        if (isset($validated['sub_category'])) {
            $group->sub_category = $validated['sub_category'] ?? 0;
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('upload/photos/' . date('Y/m'), 'public');
            $group->avatar = $path;
        }
        if ($request->hasFile('cover')) {
            $path = $request->file('cover')->store('upload/photos/' . date('Y/m'), 'public');
            $group->cover = $path;
        }

        $group->save();

        return response()->json([
            'ok' => true,
            'message' => 'Group updated successfully',
            'data' => [
                'id' => $group->id,
                'group_title' => $group->group_title,
                'about' => $group->about,
                'privacy' => $group->privacy,
                'join_privacy' => $group->join_privacy,
                'allowed_gender' => Schema::hasColumn('Wo_Groups', 'allowed_gender')
                    ? ($group->allowed_gender ?: 'all')
                    : 'all',
                'community_preferences' => Schema::hasColumn('Wo_Groups', 'community_preferences')
                    ? $this->parseCommunityPreferences($group->community_preferences ?? '')
                    : [],
                'avatar_url' => $group->avatar_url,
                'cover_url' => $group->cover_url,
            ],
        ]);
    }

    private function resolveUserId(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');

        return $userId ? (string) $userId : null;
    }

    private function isGroupActiveForUser(Group $group, ?string $userId): bool
    {
        if ($group->active == '1' || $group->active == 1) {
            return true;
        }

        return $userId && (string) $group->user_id === $userId;
    }

    private function isActiveMember(int $groupId, ?string $userId): bool
    {
        if (!$userId || !Schema::hasTable('Wo_Group_Members')) {
            return false;
        }

        return DB::table('Wo_Group_Members')
            ->where('group_id', $groupId)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('user_id', (int) $userId);
            })
            ->where('active', '1')
            ->exists();
    }

    /**
     * Active members excluding the group owner (owner is not counted as a "member").
     */
    private function countGroupMembersExcludingOwner(Group $group): int
    {
        if (!Schema::hasTable('Wo_Group_Members')) {
            return 0;
        }

        $ownerId = (string) $group->user_id;

        return (int) DB::table('Wo_Group_Members')
            ->where('group_id', $group->id)
            ->where('active', '1')
            ->whereNotIn('user_id', array_values(array_unique([(string) $ownerId, (int) $ownerId])))
            ->count();
    }

    private function isGroupOwner(Group $group, ?string $userId): bool
    {
        return $userId && (string) $group->user_id === $userId;
    }

    private function isGroupAdmin(int $groupId, ?string $userId): bool
    {
        if (!$userId || !Schema::hasTable('Wo_GroupAdmins')) {
            return false;
        }

        return DB::table('Wo_GroupAdmins')
            ->where('group_id', $groupId)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('user_id', (int) $userId);
            })
            ->exists();
    }

    private function canManageGroup(Group $group, ?string $userId): bool
    {
        return $this->isGroupOwner($group, $userId)
            || $this->isGroupAdmin((int) $group->id, $userId);
    }

    private function canInviteToGroup(Group $group, ?string $userId): bool
    {
        return $this->isGroupOwner($group, $userId)
            || $this->isActiveMember((int) $group->id, $userId);
    }

    private function hasPendingJoinRequest(int $groupId, string $userId): bool
    {
        if (!Schema::hasTable('Wo_Group_Members')) {
            return false;
        }

        return DB::table('Wo_Group_Members')
            ->where('group_id', $groupId)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('user_id', (int) $userId);
            })
            ->where('active', '0')
            ->exists();
    }

    private function isUserBlocked(string $viewerId, string $targetId): bool
    {
        if (!Schema::hasTable('Wo_Blocks')) {
            return false;
        }

        return DB::table('Wo_Blocks')
            ->where(function ($q) use ($viewerId, $targetId) {
                $q->where(function ($inner) use ($viewerId, $targetId) {
                    $inner->where('blocker', $viewerId)->where('blocked', $targetId);
                })->orWhere(function ($inner) use ($viewerId, $targetId) {
                    $inner->where('blocker', $targetId)->where('blocked', $viewerId);
                });
            })
            ->exists();
    }

    private function sendGroupInviteNotification(string $inviterId, string $recipientId, int $groupId): bool
    {
        if (!Schema::hasTable('Wo_Notifications')) {
            return false;
        }

        try {
            $existing = DB::table('Wo_Notifications')
                ->where('notifier_id', $inviterId)
                ->where('recipient_id', $recipientId)
                ->where('type', 'invited_group')
                ->where('seen', 0)
                ->where(function ($q) use ($groupId) {
                    if (Schema::hasColumn('Wo_Notifications', 'group_id')) {
                        $q->where('group_id', $groupId);
                    }
                })
                ->first();

            if ($existing) {
                return false;
            }

            $notificationData = [
                'notifier_id' => $inviterId,
                'recipient_id' => $recipientId,
                'type' => 'invited_group',
                'text' => '',
                'url' => 'index.php?link1=timeline&group_id=' . $groupId,
                'seen' => 0,
            ];

            if (Schema::hasColumn('Wo_Notifications', 'group_id')) {
                $notificationData['group_id'] = $groupId;
            }
            if (Schema::hasColumn('Wo_Notifications', 'time')) {
                $notificationData['time'] = time();
            }

            DB::table('Wo_Notifications')->insert($notificationData);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function canViewGroup(Group $group, ?string $userId): bool
    {
        $rawPrivacy = $group->getAttributes()['privacy'] ?? $group->privacy;
        $isPublic = $rawPrivacy === '1' || $rawPrivacy === 1 || $group->privacy === 'public';

        if ($isPublic) {
            return true;
        }

        return $this->isGroupOwner($group, $userId)
            || $this->isActiveMember((int) $group->id, $userId);
    }

    /**
     * Admin age-group restriction for joining.
     * @return true|string true when allowed, otherwise error message
     */
    private function assertUserMeetsGroupAgeRequirement(Group $group, string $userId)
    {
        if (!Schema::hasColumn('Wo_Groups', 'age_group')) {
            return true;
        }

        $ageGroup = trim((string) ($group->age_group ?? ''));
        if ($ageGroup === '') {
            return true;
        }

        $range = $this->ageGroupRange($ageGroup);
        if ($range === null) {
            return true;
        }

        [$minAge, $maxAge, $label] = $range;

        $birthdayRaw = DB::table('Wo_Users')->where('user_id', $userId)->value('birthday');
        $birthdayRaw = is_string($birthdayRaw) ? trim($birthdayRaw) : '';

        if ($birthdayRaw === '' || $birthdayRaw === '0000-00-00') {
            return 'Please add your date of birth in profile settings before joining this group.';
        }

        try {
            $age = \Carbon\Carbon::parse($birthdayRaw)->age;
        } catch (\Throwable $e) {
            return 'Please add a valid date of birth in profile settings before joining this group.';
        }

        if ($age < $minAge || ($maxAge !== null && $age > $maxAge)) {
            return "You cannot join this group. This group is only for ages {$label}.";
        }

        return true;
    }

    /**
     * @return array{0:int,1:?int,2:string}|null [min, max|null, label]
     */
    private function ageGroupRange(string $ageGroup): ?array
    {
        return match ($ageGroup) {
            '0_17' => [0, 17, 'Under 18'],
            '18_24' => [18, 24, '18–24'],
            '25_34' => [25, 34, '25–34'],
            '35_44' => [35, 44, '35–44'],
            '45_54' => [45, 54, '45–54'],
            '55_64' => [55, 64, '55–64'],
            '65_plus' => [65, null, '65+'],
            default => null,
        };
    }

    private function assertUserMeetsAllowedGenderRequirement(Group $group, string $userId)
    {
        if (!Schema::hasColumn('Wo_Groups', 'allowed_gender')) {
            return true;
        }

        $allowedGender = strtolower(trim((string) ($group->allowed_gender ?? '')));
        if ($allowedGender === '' || $allowedGender === 'all') {
            return true;
        }

        $userGender = strtolower(trim((string) DB::table('Wo_Users')->where('user_id', $userId)->value('gender')));
        if ($userGender === '') {
            return 'Please add your gender in profile settings before joining this group.';
        }

        if ($userGender !== $allowedGender) {
            return 'You cannot join this group. This group is restricted to ' . ucfirst($allowedGender) . ' users.';
        }

        return true;
    }

    private function assertUserMeetsCommunityPreferenceRequirement(Group $group, string $userId)
    {
        if (!Schema::hasColumn('Wo_Groups', 'community_preferences')) {
            return true;
        }

        $requiredPreferenceIds = $this->parseCommunityPreferences($group->community_preferences ?? '');
        if ($requiredPreferenceIds === []) {
            return true;
        }

        if (!Schema::hasTable('user_community_preferences')) {
            return true;
        }

        $userPreferenceIds = DB::table('user_community_preferences')
            ->where('user_id', $userId)
            ->pluck('preference_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($userPreferenceIds === []) {
            return 'Please select your community preferences in profile settings before joining this group.';
        }

        if (array_intersect($requiredPreferenceIds, $userPreferenceIds) === []) {
            return 'You cannot join this group because your community preferences do not match this group requirement.';
        }

        return true;
    }

    private function normalizeCommunityPreferences(mixed $preferences): string
    {
        if (!is_array($preferences)) {
            return '';
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $preferences))));

        return implode(',', $ids);
    }

    /**
     * @return list<int>
     */
    private function parseCommunityPreferences(?string $preferences): array
    {
        $value = trim((string) $preferences);
        if ($value === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', explode(',', $value)))));
    }
}
