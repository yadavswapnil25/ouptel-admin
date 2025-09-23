<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\Page;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class DirectoryController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));
        $type = $request->query('type', 'users'); // users, pages, groups
        $term = $request->query('term', $request->query('q'));

        if ($type === 'pages') {
            $query = Page::query()->where('active', true);
            if (!empty($term)) {
                $like = '%' . str_replace('%', '\\%', $term) . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('page_title', 'like', $like)
                      ->orWhere('page_name', 'like', $like)
                      ->orWhere('page_description', 'like', $like);
                });
            }
            $paginator = $query->orderByDesc('page_id')->paginate($perPage);
            $data = $paginator->getCollection()->map(function (Page $page) {
                return [
                    'id' => $page->page_id,
                    'name' => $page->page_title,
                    'username' => $page->page_name,
                    'avatar_url' => $page->avatar,
                    'cover_url' => \App\Helpers\ImageHelper::getCoverUrl($page->cover ?? ''),
                    'verified' => (bool) $page->verified,
                    'category' => $page->page_category,
                    'url' => $page->url,
                ];
            });
        } elseif ($type === 'groups') {
            $query = Group::query()->where('active', 1);
            if (!empty($term)) {
                $like = '%' . str_replace('%', '\\%', $term) . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('group_title', 'like', $like)
                      ->orWhere('group_name', 'like', $like)
                      ->orWhere('about', 'like', $like);
                });
            }
            $paginator = $query->orderByDesc('id')->paginate($perPage);
            $data = $paginator->getCollection()->map(function (Group $group) {
                return [
                    'id' => $group->id,
                    'name' => $group->group_title,
                    'username' => $group->group_name,
                    'avatar_url' => $group->avatar_url,
                    'cover_url' => $group->cover_url,
                    'category' => $group->category,
                ];
            });
        } else { // users
            $query = User::query();
            if (!empty($term)) {
                $like = '%' . str_replace('%', '\\%', $term) . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('username', 'like', $like)
                      ->orWhere('email', 'like', $like)
                      ->orWhere('first_name', 'like', $like)
                      ->orWhere('last_name', 'like', $like);
                });
            }
            $paginator = $query->orderByDesc('user_id')->paginate($perPage);
            $data = $paginator->getCollection()->map(function (User $user) {
                return [
                    'user_id' => $user->user_id,
                    'username' => $user->username,
                    'full_name' => $user->full_name,
                    'avatar_url' => $user->avatar_url,
                    'verified' => $user->verified === '1',
                ];
            });
        }

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
}


