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
                          ->orWhere('page_title', 'like', $like)
                          ->orWhere('about', 'like', $like);
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
                        $q->where('group_name', 'like', $like)
                          ->orWhere('about', 'like', $like);
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
}


