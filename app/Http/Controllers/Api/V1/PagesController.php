<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Page;
use App\Models\PageCategory;
use App\Models\PageSubCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class PagesController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $type = $request->query('type', 'my_pages'); // my_pages, suggested, liked

        // Resolve user via token when needed
        $authHeader = $request->header('Authorization');
        $tokenUserId = null;
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        }

        $query = Page::query()->where('active', 1);

        if ($type === 'my_pages') {
            if (!$tokenUserId) {
                return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
            }
            $query->where('user_id', $tokenUserId);
        } elseif ($type === 'liked') {
            if (!$tokenUserId) {
                return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
            }
            $likedPageIds = DB::table('Wo_Pages_Likes')
                ->where('user_id', $tokenUserId)
                ->pluck('page_id');
            $query->whereIn('page_id', $likedPageIds);
        } elseif ($type === 'suggested') {
            // Suggested pages: not owned by user and not liked by user
            if ($tokenUserId) {
                $likedPageIds = DB::table('Wo_Pages_Likes')
                    ->where('user_id', $tokenUserId)
                    ->pluck('page_id')
                    ->toArray();
                $query->where('user_id', '!=', $tokenUserId)
                      ->whereNotIn('page_id', $likedPageIds);
            }
        } else {
            // Default behavior: all active pages (optionally category filtered)
        }

        if ($request->filled('category')) {
            $query->where('page_category', $request->query('category'));
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Page $page) {
            return [
                'page_id' => $page->page_id,
                'page_name' => $page->page_name,
                'page_title' => $page->page_title,
                'description' => $page->page_description,
                'category' => $page->page_category,
                'category_name' => $page->category_name,
                'verified' => $page->verified,
                'avatar_url' => $page->avatar,
                'cover_url' => \App\Helpers\ImageHelper::getCoverUrl($page->cover ?? ''),
                'website' => $page->website,
                'phone' => $page->phone,
                'address' => $page->address,
                'url' => $page->url,
                'owner' => [
                    'user_id' => optional($page->owner)->user_id,
                    'username' => optional($page->owner)->username,
                    'avatar_url' => optional($page->owner)->avatar_url,
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
            ],
        ]);
    }

    public function meta(): JsonResponse
    {
        $categories = PageCategory::query()
            ->orderBy('id')
            ->get()
            ->map(fn (PageCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]);

        $categoryId = request()->query('category_id');
        $subsQuery = PageSubCategory::query();
        if (!empty($categoryId)) {
            $subsQuery->where('category_id', (int) $categoryId);
        }
        $subCategories = $subsQuery
            ->orderBy('id')
            ->get()
            ->map(fn (PageSubCategory $s) => [
                'id' => $s->id,
                'category_id' => $s->category_id,
                'name' => $s->name,
            ]);

        return response()->json([
            'ok' => true,
            'data' => [
                'categories' => $categories,
                'sub_categories' => $subCategories,
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
            'page_name' => ['required', 'string', 'max:32'],
            'page_title' => ['required', 'string', 'max:100'],
            'page_description' => ['nullable', 'string', 'max:500'],
            'page_category' => ['required'],
            'website' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        // Ensure unique page_name
        $exists = Page::where('page_name', $validated['page_name'])->exists();
        if ($exists) {
            return response()->json(['ok' => false, 'message' => 'Page name is already taken'], 400);
        }

        $page = new Page();
        $page->page_name = $validated['page_name'];
        $page->page_title = $validated['page_title'];
        $page->page_description = $validated['page_description'] ?? '';
        $page->page_category = $validated['page_category'];
        $page->user_id = $userId;
        $page->verified = false;
        $page->active = true;
        $page->website = $validated['website'] ?? '';
        $page->phone = $validated['phone'] ?? '';
        $page->address = $validated['address'] ?? '';
        $page->save();

        return response()->json([
            'ok' => true,
            'message' => 'Page created successfully',
            'data' => [
                'page_id' => $page->page_id,
                'page_name' => $page->page_name,
                'page_title' => $page->page_title,
                'description' => $page->page_description,
                'category' => $page->page_category,
                'verified' => (bool) $page->verified,
                'avatar_url' => $page->avatar,
                'cover_url' => \App\Helpers\ImageHelper::getCoverUrl($page->cover ?? ''),
                'website' => $page->website,
                'phone' => $page->phone,
                'address' => $page->address,
                'url' => $page->url,
            ],
        ], 201);
    }
}


