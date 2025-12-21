<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SearchController extends Controller
{
    /**
     * Search API (mimics old API: search.php)
     * Searches for users, pages, groups, and channels
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
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

        // Get parameters (matching old API structure)
        $limit = (int) ($request->input('limit', $request->query('limit', 35)));
        $limit = max(1, min($limit, 50));
        
        $searchKey = trim((string) ($request->input('search_key', $request->query('query', ''))));
        $gender = (string) ($request->input('gender', ''));
        $status = (string) ($request->input('status', ''));
        $image = (string) ($request->input('image', ''));
        $country = (string) ($request->input('country', ''));
        $verified = (string) ($request->input('verified', ''));
        $filterByAge = (string) ($request->input('filterbyage', ''));
        $ageFrom = (int) $request->input('age_from', 0);
        $ageTo = (int) $request->input('age_to', 0);

        // Offsets for pagination
        $userOffset = (int) ($request->input('user_offset', $request->query('user_offset', 0)));
        $pageOffset = (int) ($request->input('page_offset', $request->query('page_offset', 0)));
        $groupOffset = (int) ($request->input('group_offset', $request->query('group_offset', 0)));
        $channelsOffset = (int) ($request->input('channels_offset', $request->query('channels_offset', 0)));

        // Search users
        $users = $this->searchUsers($tokenUserId, $searchKey, $gender, $status, $image, $country, $verified, $filterByAge, $ageFrom, $ageTo, $limit, $userOffset);

        // Search pages
        $pages = $this->searchPages($tokenUserId, $searchKey, $limit, $pageOffset);

        // Search groups
        $groups = $this->searchGroups($tokenUserId, $searchKey, $limit, $groupOffset);

        // Search channels (if applicable)
        $channels = $this->searchChannels($searchKey, $limit, $channelsOffset);

        return response()->json([
            'api_status' => 200,
            'users' => $users,
            'pages' => $pages,
            'groups' => $groups,
            'channels' => $channels
        ]);
    }

    /**
     * Search for posts (mimics old API: search_for_posts.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function searchForPosts(Request $request): JsonResponse
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

        // Validate request
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'type' => 'required|in:page,user,group',
            'id' => 'required|integer|min:1',
            'search_query' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'please check your details'
                ]
            ], 400);
        }

        $type = $request->input('type');
        $id = (int) $request->input('id');
        $searchQuery = trim($request->input('search_query'));
        $limit = (int) ($request->input('limit', 20));
        $limit = max(1, min($limit, 50));

        // Search posts
        $posts = $this->searchPosts($tokenUserId, $id, $searchQuery, $limit, $type);

        return response()->json([
            'api_status' => 200,
            'data' => $posts
        ]);
    }

    /**
     * Get recent searches (mimics old API: recent_search.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function recentSearches(Request $request): JsonResponse
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

        // Check if recent searches table exists
        if (!Schema::hasTable('Wo_RecentSearches')) {
            return response()->json([
                'api_status' => 200,
                'data' => []
            ]);
        }

        // Get recent searches - check for time column or use id/created_at
        $query = DB::table('Wo_RecentSearches')
            ->where('user_id', $tokenUserId);
        
        // Order by time if column exists, otherwise by id desc
        if (Schema::hasColumn('Wo_RecentSearches', 'time')) {
            $query->orderByDesc('time');
        } elseif (Schema::hasColumn('Wo_RecentSearches', 'created_at')) {
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('id');
        }
        
        $recentSearches = $query->limit(20)
            ->get()
            ->map(function($search) {
                return [
                    'id' => $search->id,
                    'search_key' => $search->search_key ?? '',
                    'time' => $search->time ?? $search->created_at ?? time(),
                ];
            })
            ->toArray();

        return response()->json([
            'api_status' => 200,
            'data' => $recentSearches
        ]);
    }

    /**
     * Explore search API (users, pages, groups) similar to site search filters.
     *
     * Supported query params:
     * - verified: all|verified|unverified
     * - status: all|online|offline
     * - image: all|yes|no
     * - filterbyage: yes|no
     * - age_from: int
     * - age_to: int
     * - query: string
     * - country: all|country_name (ignored if not applicable)
     * - page: int (pagination)
     * - per_page: int (max 50)
     */
    public function explore(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions (optional for public explore – keep consistent with other APIs)
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
            // If token invalid we still allow public exploration; do not hard fail
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 50));

        $queryString = trim((string) $request->input('query', ''));
        $verified = strtolower((string) $request->input('verified', 'all'));
        $status = strtolower((string) $request->input('status', 'all'));
        $image = strtolower((string) $request->input('image', 'all'));
        $filterByAge = strtolower((string) $request->input('filterbyage', 'no')) === 'yes';
        $ageFrom = (int) $request->input('age_from', 0);
        $ageTo = (int) $request->input('age_to', 0);
        $country = (string) $request->input('country', 'all');

        try {
            // Users search
            $usersQuery = DB::table('Wo_Users')->where('active', '1');

            if ($queryString !== '') {
                $usersQuery->where(function ($q) use ($queryString) {
                    $like = '%' . $queryString . '%';
                    $q->where('username', 'like', $like)
                      ->orWhere('first_name', 'like', $like)
                      ->orWhere('last_name', 'like', $like)
                      ->orWhere('about', 'like', $like);
                });
            }

            if ($verified === 'verified') {
                $usersQuery->where('verified', '1');
            } elseif ($verified === 'unverified') {
                $usersQuery->where(function ($q) {
                    $q->whereNull('verified')->orWhere('verified', '!=', '1');
                });
            }

            if ($status === 'online') {
                $usersQuery->where('lastseen', '>', time() - 60);
            } elseif ($status === 'offline') {
                $usersQuery->where(function ($q) {
                    $q->whereNull('lastseen')->orWhere('lastseen', '<=', time() - 60);
                });
            }

            if ($image === 'yes') {
                $usersQuery->whereNotNull('avatar')->where('avatar', '!=', '');
            } elseif ($image === 'no') {
                $usersQuery->where(function ($q) {
                    $q->whereNull('avatar')->orWhere('avatar', '=', '');
                });
            }

            if ($filterByAge && $ageFrom > 0 && $ageTo > 0 && $ageTo >= $ageFrom) {
                // Attempt age filtering if birthday is a valid date column
                // Falls back gracefully if column or data invalid
                $usersQuery->whereNotNull('birthday')->where('birthday', '!=', '');
                $usersQuery->whereRaw("CASE WHEN STR_TO_DATE(birthday, '%Y-%m-%d') IS NOT NULL THEN TIMESTAMPDIFF(YEAR, STR_TO_DATE(birthday, '%Y-%m-%d'), CURDATE()) BETWEEN ? AND ? ELSE 0 END", [$ageFrom, $ageTo]);
            }

            if ($country !== '' && strtolower($country) !== 'all') {
                // Try to match by country text column if present; otherwise ignore
                if (Schema::hasColumn('Wo_Users', 'country')) {
                    $usersQuery->where('country', $country);
                }
            }

            $users = $usersQuery
                ->select('user_id', 'username', 'first_name', 'last_name', 'avatar', 'verified', 'lastseen')
                ->orderBy('user_id', 'desc')
                ->paginate($perPage);

            $formattedUsers = $users->map(function ($u) {
                $name = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->username;
                return [
                    'user_id' => $u->user_id,
                    'username' => $u->username,
                    'name' => $name,
                    'avatar_url' => $u->avatar ? asset('storage/' . $u->avatar) : null,
                    'verified' => ($u->verified === '1'),
                    'is_online' => $u->lastseen && $u->lastseen > (time() - 60),
                    'profile_url' => url('/user/' . $u->username),
                ];
            });

            // Pages search (best-effort)
            $pages = [];
            try {
                $pagesQuery = DB::table('Wo_Pages');
                if ($queryString !== '') {
                    $pagesQuery->where(function ($q) use ($queryString) {
                        $like = '%' . $queryString . '%';
                        $q->where('page_name', 'like', $like)
                          ->orWhere('page_title', 'like', $like);
                        
                        // Only search in about column if it exists
                        if (Schema::hasColumn('Wo_Pages', 'about')) {
                            $q->orWhere('about', 'like', $like);
                        }
                    });
                }
                $pages = $pagesQuery
                    ->select('page_id', 'page_name', 'page_title', 'avatar', 'verified')
                    ->orderBy('page_id', 'desc')
                    ->limit($perPage)
                    ->get()
                    ->map(function ($p) {
                        return [
                            'page_id' => $p->page_id,
                            'name' => $p->page_title ?? $p->page_name,
                            'slug' => $p->page_name,
                            'avatar_url' => $p->avatar ? asset('storage/' . $p->avatar) : null,
                            'verified' => ($p->verified === '1'),
                        ];
                    })
                    ->toArray();
            } catch (\Exception $e) {
                $pages = [];
            }

            // Groups search (best-effort)
            $groups = [];
            try {
                $groupsQuery = DB::table('Wo_Groups');
                if ($queryString !== '') {
                    $groupsQuery->where(function ($q) use ($queryString) {
                        $like = '%' . $queryString . '%';
                        $q->where('group_name', 'like', $like);
                        
                        // Only search in about column if it exists
                        if (Schema::hasColumn('Wo_Groups', 'about')) {
                            $q->orWhere('about', 'like', $like);
                        }
                    });
                }
                $groups = $groupsQuery
                    ->select('id', 'group_name', 'avatar', 'privacy')
                    ->orderBy('id', 'desc')
                    ->limit($perPage)
                    ->get()
                    ->map(function ($g) {
                        return [
                            'group_id' => $g->id,
                            'name' => $g->group_name,
                            'avatar_url' => $g->avatar ? asset('storage/' . $g->avatar) : null,
                            'privacy' => $g->privacy,
                        ];
                    })
                    ->toArray();
            } catch (\Exception $e) {
                $groups = [];
            }

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'filters' => [
                    'query' => $queryString,
                    'verified' => $verified,
                    'status' => $status,
                    'image' => $image,
                    'filterbyage' => $filterByAge ? 'yes' : 'no',
                    'age_from' => $ageFrom,
                    'age_to' => $ageTo,
                    'country' => $country,
                ],
                'results' => [
                    'users' => [
                        'data' => $formattedUsers,
                        'pagination' => [
                            'current_page' => $users->currentPage(),
                            'per_page' => $users->perPage(),
                            'total' => $users->total(),
                            'last_page' => $users->lastPage(),
                        ],
                    ],
                    'pages' => $pages,
                    'groups' => $groups,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => 'SEARCH_1',
                    'error_text' => 'Failed to run explore search: ' . $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Search users
     * 
     * @param string $tokenUserId
     * @param string $searchKey
     * @param string $gender
     * @param string $status
     * @param string $image
     * @param string $country
     * @param string $verified
     * @param string $filterByAge
     * @param int $ageFrom
     * @param int $ageTo
     * @param int $limit
     * @param int $offset
     * @return array
     */
    private function searchUsers(string $tokenUserId, string $searchKey, string $gender, string $status, string $image, string $country, string $verified, string $filterByAge, int $ageFrom, int $ageTo, int $limit, int $offset): array
    {
        $query = DB::table('Wo_Users')->where('active', '1');

        if (!empty($searchKey)) {
            $like = '%' . $searchKey . '%';
            $query->where(function($q) use ($like) {
                $q->where('username', 'like', $like);
                
                // Only search in columns that exist
                if (Schema::hasColumn('Wo_Users', 'first_name')) {
                    $q->orWhere('first_name', 'like', $like);
                }
                if (Schema::hasColumn('Wo_Users', 'last_name')) {
                    $q->orWhere('last_name', 'like', $like);
                }
                if (Schema::hasColumn('Wo_Users', 'name')) {
                    $q->orWhere('name', 'like', $like);
                }
                if (Schema::hasColumn('Wo_Users', 'about')) {
                    $q->orWhere('about', 'like', $like);
                }
            });
        }

        if (!empty($gender)) {
            $query->where('gender', $gender);
        }

        if ($status === 'online') {
            $query->where('lastseen', '>', time() - 60);
        } elseif ($status === 'offline') {
            $query->where(function($q) {
                $q->whereNull('lastseen')->orWhere('lastseen', '<=', time() - 60);
            });
        }

        if ($image === 'yes') {
            $query->whereNotNull('avatar')->where('avatar', '!=', '');
        } elseif ($image === 'no') {
            $query->where(function($q) {
                $q->whereNull('avatar')->orWhere('avatar', '=', '');
            });
        }

        if (!empty($country) && $country !== 'all') {
            if (Schema::hasColumn('Wo_Users', 'country')) {
                $query->where('country', $country);
            }
        }

        if ($verified === 'verified') {
            $query->where('verified', '1');
        } elseif ($verified === 'unverified') {
            $query->where(function($q) {
                $q->whereNull('verified')->orWhere('verified', '!=', '1');
            });
        }

        if ($filterByAge === 'yes' && $ageFrom > 0 && $ageTo > 0 && $ageTo >= $ageFrom) {
            if (Schema::hasColumn('Wo_Users', 'birthday')) {
                $query->whereNotNull('birthday')->where('birthday', '!=', '');
                $query->whereRaw("CASE WHEN STR_TO_DATE(birthday, '%Y-%m-%d') IS NOT NULL THEN TIMESTAMPDIFF(YEAR, STR_TO_DATE(birthday, '%Y-%m-%d'), CURDATE()) BETWEEN ? AND ? ELSE 0 END", [$ageFrom, $ageTo]);
            }
        }

        if ($offset > 0) {
            $query->where('user_id', '<', $offset);
        }

        $users = $query->orderByDesc('user_id')
            ->limit($limit)
            ->get();

        $formatted = [];
        foreach ($users as $user) {
            $isFollowing = DB::table('Wo_Followers')
                ->where('follower_id', $tokenUserId)
                ->where('following_id', $user->user_id)
                ->exists();

            $formatted[] = [
                'user_id' => $user->user_id,
                'username' => $user->username ?? 'Unknown',
                'name' => $this->getUserName($user),
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'email' => $user->email ?? '',
                'avatar' => $user->avatar ?? '',
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'cover' => $user->cover ?? '',
                'cover_url' => $user->cover ? asset('storage/' . $user->cover) : null,
                'verified' => (bool) ($user->verified ?? false),
                'is_following' => $isFollowing ? 1 : 0,
            ];
        }

        return $formatted;
    }

    /**
     * Search pages
     * 
     * @param string $tokenUserId
     * @param string $searchKey
     * @param int $limit
     * @param int $offset
     * @return array
     */
    private function searchPages(string $tokenUserId, string $searchKey, int $limit, int $offset): array
    {
        if (!Schema::hasTable('Wo_Pages')) {
            return [];
        }

        $query = DB::table('Wo_Pages');

        if (!empty($searchKey)) {
            $like = '%' . $searchKey . '%';
            $query->where(function($q) use ($like) {
                $q->where('page_name', 'like', $like)
                  ->orWhere('page_title', 'like', $like);
                
                // Only search in about column if it exists
                if (Schema::hasColumn('Wo_Pages', 'about')) {
                    $q->orWhere('about', 'like', $like);
                }
            });
        }

        if ($offset > 0) {
            $query->where('page_id', '<', $offset);
        }

        $pages = $query->orderByDesc('page_id')
            ->limit($limit)
            ->get();

        $formatted = [];
        $hasPageLikesTable = Schema::hasTable('Wo_PageLikes');
        
        foreach ($pages as $page) {
            $isLiked = false;
            if ($hasPageLikesTable) {
                try {
                    $isLiked = DB::table('Wo_PageLikes')
                        ->where('user_id', $tokenUserId)
                        ->where('page_id', $page->page_id)
                        ->exists();
                } catch (\Exception $e) {
                    // Table doesn't exist or query failed, assume not liked
                    $isLiked = false;
                }
            }

            $formatted[] = [
                'id' => $page->page_id,
                'page_id' => $page->page_id,
                'page_name' => $page->page_name ?? '',
                'page_title' => $page->page_title ?? $page->page_name ?? '',
                'name' => $page->page_title ?? $page->page_name ?? '',
                'about' => $page->about ?? '',
                'avatar' => $page->avatar ?? '',
                'avatar_url' => $page->avatar ? asset('storage/' . $page->avatar) : null,
                'cover' => $page->cover ?? '',
                'cover_url' => $page->cover ? asset('storage/' . $page->cover) : null,
                'verified' => (bool) ($page->verified ?? false),
                'is_liked' => $isLiked ? 'yes' : 'no',
            ];
        }

        return $formatted;
    }

    /**
     * Search groups
     * 
     * @param string $tokenUserId
     * @param string $searchKey
     * @param int $limit
     * @param int $offset
     * @return array
     */
    private function searchGroups(string $tokenUserId, string $searchKey, int $limit, int $offset): array
    {
        if (!Schema::hasTable('Wo_Groups')) {
            return [];
        }

        $query = DB::table('Wo_Groups');

        if (!empty($searchKey)) {
            $like = '%' . $searchKey . '%';
            $query->where(function($q) use ($like) {
                $q->where('group_name', 'like', $like);
                
                // Only search in about column if it exists
                if (Schema::hasColumn('Wo_Groups', 'about')) {
                    $q->orWhere('about', 'like', $like);
                }
            });
        }

        if ($offset > 0) {
            $query->where('id', '<', $offset);
        }

        $groups = $query->orderByDesc('id')
            ->limit($limit)
            ->get();

        $formatted = [];
        foreach ($groups as $group) {
            $isJoined = DB::table('Wo_GroupMembers')
                ->where('user_id', $tokenUserId)
                ->where('group_id', $group->id)
                ->exists();

            $formatted[] = [
                'id' => $group->id,
                'group_id' => $group->id,
                'group_name' => $group->group_name ?? '',
                'name' => $group->group_name ?? '',
                'about' => $group->about ?? '',
                'avatar' => $group->avatar ?? '',
                'avatar_url' => $group->avatar ? asset('storage/' . $group->avatar) : null,
                'cover' => $group->cover ?? '',
                'cover_url' => $group->cover ? asset('storage/' . $group->cover) : null,
                'privacy' => $group->privacy ?? 'public',
                'is_joined' => $isJoined ? 'yes' : 'no',
            ];
        }

        return $formatted;
    }

    /**
     * Search channels
     * 
     * @param string $searchKey
     * @param int $limit
     * @param int $offset
     * @return array
     */
    private function searchChannels(string $searchKey, int $limit, int $offset): array
    {
        // Channels functionality - return empty for now
        // Can be implemented if channels table exists
        return [];
    }

    /**
     * Search posts
     * 
     * @param string $tokenUserId
     * @param int $id
     * @param string $searchQuery
     * @param int $limit
     * @param string $type
     * @return array
     */
    private function searchPosts(string $tokenUserId, int $id, string $searchQuery, int $limit, string $type): array
    {
        $query = DB::table('Wo_Posts')
            ->where('active', '1')
            ->where(function($q) use ($searchQuery) {
                $like = '%' . $searchQuery . '%';
                $q->where('postText', 'like', $like)
                  ->orWhere('postLink', 'like', $like);
            });

        if ($type === 'user') {
            $query->where('user_id', $id);
        } elseif ($type === 'page') {
            $query->where('page_id', $id);
        } elseif ($type === 'group') {
            $query->where('group_id', $id);
        }

        $posts = $query->orderByDesc('id')
            ->limit($limit)
            ->get();

        $formatted = [];
        foreach ($posts as $post) {
            // Get user data
            $user = DB::table('Wo_Users')->where('user_id', $post->user_id)->first();
            
            $formatted[] = [
                'id' => $post->id,
                'post_id' => $post->id,
                'user_id' => $post->user_id,
                'postText' => $post->postText ?? '',
                'postFile' => $post->postFile ? asset('storage/' . $post->postFile) : null,
                'postFileThumb' => $post->postFileThumb ? asset('storage/' . $post->postFileThumb) : null,
                'postLink' => $post->postLink ?? '',
                'postYoutube' => $post->postYoutube ?? '',
                'postPlaytube' => $post->postPlaytube ?? '',
                'postType' => $post->postType ?? 'post',
                'time' => $post->time ?? time(),
                'publisher' => $user ? [
                    'user_id' => $user->user_id,
                    'username' => $user->username ?? 'Unknown',
                    'name' => $this->getUserName($user),
                    'avatar' => $user->avatar ?? '',
                    'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'verified' => (bool) ($user->verified ?? false),
                ] : null,
                'user_data' => null, // Same as publisher
                'shared_info' => null,
            ];
        }

        return $formatted;
    }

    /**
     * Get user name from user object (handles different column structures)
     * 
     * @param object $user
     * @return string
     */
    private function getUserName($user): string
    {
        // Try name column first
        if (isset($user->name) && !empty($user->name)) {
            return $user->name;
        }
        
        // Try first_name + last_name
        $firstName = $user->first_name ?? '';
        $lastName = $user->last_name ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        if (!empty($fullName)) {
            return $fullName;
        }
        
        // Fallback to username
        return $user->username ?? 'Unknown User';
    }
}


