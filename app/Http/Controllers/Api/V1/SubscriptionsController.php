<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SubscriptionsController extends Controller
{
    /**
     * Get my subscriptions (users, pages, groups I follow/like/joined)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getMySubscriptions(Request $request): JsonResponse
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

        try {
            $perPage = (int) ($request->input('per_page', $request->query('per_page', 20)));
            $perPage = max(1, min($perPage, 100));
            $page = (int) ($request->input('page', $request->query('page', 1)));
            $page = max(1, $page);

            // Get type filter (users, pages, groups, or all)
            $type = $request->input('type', $request->query('type', 'all')); // all, users, pages, groups

            $subscriptions = [
                'users' => [],
                'pages' => [],
                'groups' => [],
            ];

            $totals = [
                'users' => 0,
                'pages' => 0,
                'groups' => 0,
            ];

            // Get users I follow
            if ($type === 'all' || $type === 'users') {
                $usersData = $this->getSubscribedUsers($tokenUserId, $perPage, $page);
                $subscriptions['users'] = $usersData['data'];
                $totals['users'] = $usersData['total'];
            }

            // Get pages I like
            if ($type === 'all' || $type === 'pages') {
                $pagesData = $this->getSubscribedPages($tokenUserId, $perPage, $page);
                $subscriptions['pages'] = $pagesData['data'];
                $totals['pages'] = $pagesData['total'];
            }

            // Get groups I joined
            if ($type === 'all' || $type === 'groups') {
                $groupsData = $this->getSubscribedGroups($tokenUserId, $perPage, $page);
                $subscriptions['groups'] = $groupsData['data'];
                $totals['groups'] = $groupsData['total'];
            }

            return response()->json([
                'api_status' => 200,
                'data' => $subscriptions,
                'meta' => [
                    'totals' => $totals,
                    'total_subscriptions' => array_sum($totals),
                    'per_page' => $perPage,
                    'current_page' => $page,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Failed to get subscriptions: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get users I follow
     * 
     * @param string $userId
     * @param int $perPage
     * @param int $page
     * @return array
     */
    private function getSubscribedUsers(string $userId, int $perPage, int $page): array
    {
        if (!Schema::hasTable('Wo_Followers')) {
            return ['data' => [], 'total' => 0];
        }

        $offset = ($page - 1) * $perPage;

        $query = DB::table('Wo_Followers as f')
            ->join('Wo_Users as u', 'f.following_id', '=', 'u.user_id')
            ->where('f.follower_id', $userId)
            ->whereIn('f.active', ['1', 1]) // Only active follows
            ->whereIn('u.active', ['1', 1]) // Only active users
            ->select(
                'u.user_id',
                'u.username',
                'u.first_name',
                'u.last_name',
                'u.avatar',
                'u.verified',
                'u.cover',
                'f.time as subscribed_at'
            )
            ->orderByDesc('f.time');

        $total = $query->count();
        $users = $query->offset($offset)->limit($perPage)->get();

        $formattedUsers = $users->map(function ($user) {
            $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            if (empty($fullName)) {
                $fullName = $user->username ?? 'Unknown User';
            }

            return [
                'id' => $user->user_id,
                'type' => 'user',
                'user_id' => $user->user_id,
                'username' => $user->username ?? 'Unknown',
                'name' => $fullName,
                'avatar' => $user->avatar ?? '',
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'cover' => $user->cover ?? '',
                'cover_url' => $user->cover ? asset('storage/' . $user->cover) : null,
                'verified' => (bool) ($user->verified ?? false),
                'subscribed_at' => $user->subscribed_at ? date('c', $user->subscribed_at) : null,
                'subscribed_at_human' => $user->subscribed_at ? $this->getHumanTime($user->subscribed_at) : null,
            ];
        })->toArray();

        return [
            'data' => $formattedUsers,
            'total' => $total,
        ];
    }

    /**
     * Get pages I like
     * 
     * @param string $userId
     * @param int $perPage
     * @param int $page
     * @return array
     */
    private function getSubscribedPages(string $userId, int $perPage, int $page): array
    {
        // Check for page likes table (could be Wo_Pages_Likes or Wo_PageLikes)
        $pageLikesTable = null;
        if (Schema::hasTable('Wo_Pages_Likes')) {
            $pageLikesTable = 'Wo_Pages_Likes';
        } elseif (Schema::hasTable('Wo_PageLikes')) {
            $pageLikesTable = 'Wo_PageLikes';
        }

        if (!$pageLikesTable || !Schema::hasTable('Wo_Pages')) {
            return ['data' => [], 'total' => 0];
        }

        $offset = ($page - 1) * $perPage;

        // Build select fields based on available columns
        $selectFields = [
            'p.page_id',
            'p.page_name',
            'p.page_title',
            'p.page_description',
            'p.user_id as owner_id',
            'pl.time as subscribed_at'
        ];

        // Add optional columns if they exist
        if (Schema::hasColumn('Wo_Pages', 'avatar')) {
            $selectFields[] = 'p.avatar';
        }
        if (Schema::hasColumn('Wo_Pages', 'cover')) {
            $selectFields[] = 'p.cover';
        }

        // Add category - check for page_category first (most common), then category
        if (Schema::hasColumn('Wo_Pages', 'page_category')) {
            $selectFields[] = 'p.page_category as category';
        } elseif (Schema::hasColumn('Wo_Pages', 'category')) {
            $selectFields[] = 'p.category';
        }

        $query = DB::table($pageLikesTable . ' as pl')
            ->join('Wo_Pages as p', 'pl.page_id', '=', 'p.page_id')
            ->where('pl.user_id', $userId)
            ->whereIn('p.active', ['1', 1]) // Only active pages
            ->select($selectFields)
            ->orderByDesc('pl.time');

        $total = $query->count();
        $pages = $query->offset($offset)->limit($perPage)->get();

        $formattedPages = $pages->map(function ($page) use ($pageLikesTable) {
            // Get page category name
            $categoryName = '';
            // Get category ID - handle both column names
            $categoryId = 0;
            if (isset($page->category)) {
                $categoryId = (int) $page->category;
            } elseif (isset($page->page_category)) {
                $categoryId = (int) $page->page_category;
            }
            if (Schema::hasTable('Wo_PageCategories') && $categoryId) {
                $category = DB::table('Wo_PageCategories')
                    ->where('id', $categoryId)
                    ->first();
                $categoryName = $category->category_name ?? '';
            }

            // Get page owner
            $owner = null;
            if ($page->owner_id) {
                $owner = DB::table('Wo_Users')
                    ->where('user_id', $page->owner_id)
                    ->first();
            }

            // Get page likes count
            $likesCount = 0;
            if ($pageLikesTable && Schema::hasTable($pageLikesTable)) {
                $likesCount = DB::table($pageLikesTable)
                    ->where('page_id', $page->page_id)
                    ->count();
            }

            $avatar = $page->avatar ?? null;
            $cover = $page->cover ?? null;

            return [
                'id' => $page->page_id,
                'type' => 'page',
                'page_id' => $page->page_id,
                'page_name' => $page->page_name ?? '',
                'page_title' => $page->page_title ?? '',
                'page_description' => $page->page_description ?? '',
                'avatar' => $avatar ?? '',
                'avatar_url' => $avatar ? asset('storage/' . $avatar) : null,
                'cover' => $cover ?? '',
                'cover_url' => $cover ? asset('storage/' . $cover) : null,
                'category' => $categoryId,
                'category_name' => $categoryName,
                'likes_count' => $likesCount,
                'owner' => $owner ? [
                    'user_id' => $owner->user_id,
                    'username' => $owner->username ?? 'Unknown',
                    'name' => $this->getUserName($owner),
                ] : null,
                'subscribed_at' => $page->subscribed_at ? date('c', $page->subscribed_at) : null,
                'subscribed_at_human' => $page->subscribed_at ? $this->getHumanTime($page->subscribed_at) : null,
            ];
        })->toArray();

        return [
            'data' => $formattedPages,
            'total' => $total,
        ];
    }

    /**
     * Get groups I joined
     * 
     * @param string $userId
     * @param int $perPage
     * @param int $page
     * @return array
     */
    private function getSubscribedGroups(string $userId, int $perPage, int $page): array
    {
        // Check for group members table (could be Wo_Group_Members or Wo_GroupMembers)
        $groupMembersTable = null;
        if (Schema::hasTable('Wo_Group_Members')) {
            $groupMembersTable = 'Wo_Group_Members';
        } elseif (Schema::hasTable('Wo_GroupMembers')) {
            $groupMembersTable = 'Wo_GroupMembers';
        }

        if (!$groupMembersTable || !Schema::hasTable('Wo_Groups')) {
            return ['data' => [], 'total' => 0];
        }

        $offset = ($page - 1) * $perPage;

        $query = DB::table($groupMembersTable . ' as gm')
            ->join('Wo_Groups as g', 'gm.group_id', '=', 'g.id')
            ->where('gm.user_id', $userId)
            ->whereIn('g.active', ['1', 1]) // Only active groups
            ->select(
                'g.id',
                'g.group_name',
                'g.group_title',
                'g.about',
                'g.avatar',
                'g.cover',
                'g.category',
                'g.user_id as owner_id',
                'g.privacy',
                'gm.time as subscribed_at'
            )
            ->orderByDesc('gm.time');

        $total = $query->count();
        $groups = $query->offset($offset)->limit($perPage)->get();

        $formattedGroups = $groups->map(function ($group) use ($groupMembersTable) {
            // Get group category name
            $categoryName = '';
            if (Schema::hasTable('Wo_GroupCategories') && $group->category) {
                $category = DB::table('Wo_GroupCategories')
                    ->where('id', $group->category)
                    ->first();
                $categoryName = $category->category_name ?? '';
            }

            // Get group owner
            $owner = null;
            if ($group->owner_id) {
                $owner = DB::table('Wo_Users')
                    ->where('user_id', $group->owner_id)
                    ->first();
            }

            // Get group members count
            $membersCount = 0;
            if ($groupMembersTable && Schema::hasTable($groupMembersTable)) {
                $membersCount = DB::table($groupMembersTable)
                    ->where('group_id', $group->id)
                    ->count();
            }

            return [
                'id' => $group->id,
                'type' => 'group',
                'group_id' => $group->id,
                'group_name' => $group->group_name ?? '',
                'group_title' => $group->group_title ?? '',
                'about' => $group->about ?? '',
                'avatar' => $group->avatar ?? '',
                'avatar_url' => $group->avatar ? asset('storage/' . $group->avatar) : null,
                'cover' => $group->cover ?? '',
                'cover_url' => $group->cover ? asset('storage/' . $group->cover) : null,
                'category' => $group->category ?? 0,
                'category_name' => $categoryName,
                'privacy' => $group->privacy ?? '0',
                'privacy_text' => $this->getGroupPrivacyText($group->privacy ?? '0'),
                'members_count' => $membersCount,
                'owner' => $owner ? [
                    'user_id' => $owner->user_id,
                    'username' => $owner->username ?? 'Unknown',
                    'name' => $this->getUserName($owner),
                ] : null,
                'subscribed_at' => $group->subscribed_at ? date('c', $group->subscribed_at) : null,
                'subscribed_at_human' => $group->subscribed_at ? $this->getHumanTime($group->subscribed_at) : null,
            ];
        })->toArray();

        return [
            'data' => $formattedGroups,
            'total' => $total,
        ];
    }

    /**
     * Get user name helper
     * 
     * @param object $user
     * @return string
     */
    private function getUserName($user): string
    {
        if (!$user) {
            return 'Unknown User';
        }
        
        $firstName = $user->first_name ?? '';
        $lastName = $user->last_name ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        if (!empty($fullName)) {
            return $fullName;
        }
        
        return $user->username ?? 'Unknown User';
    }

    /**
     * Get group privacy text
     * 
     * @param string $privacy
     * @return string
     */
    private function getGroupPrivacyText(string $privacy): string
    {
        return match($privacy) {
            '0' => 'Public',
            '1' => 'Private',
            '2' => 'Closed',
            default => 'Public'
        };
    }

    /**
     * Get human readable time
     * 
     * @param int $timestamp
     * @return string
     */
    private function getHumanTime(int $timestamp): string
    {
        $time = time() - $timestamp;
        
        if ($time < 60) return 'Just now';
        if ($time < 3600) return floor($time / 60) . 'm';
        if ($time < 86400) return floor($time / 3600) . 'h';
        if ($time < 2592000) return floor($time / 86400) . 'd';
        if ($time < 31536000) return floor($time / 2592000) . 'mo';
        return floor($time / 31536000) . 'y';
    }
}

