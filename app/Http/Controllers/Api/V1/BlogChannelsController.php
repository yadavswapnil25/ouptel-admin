<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Article;
use App\Models\BlogChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BlogChannelsController extends BaseController
{
    private function authUserId(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        return $userId ? (string) $userId : null;
    }

    private function requireAuth(Request $request): JsonResponse|string
    {
        $userId = $this->authUserId($request);
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        return $userId;
    }

    private function uploadImage($file, string $prefix): string
    {
        $year = date('Y');
        $month = date('m');
        $dir = "upload/photos/{$year}/{$month}";
        if (!is_dir(public_path($dir))) {
            @mkdir(public_path($dir), 0755, true);
        }
        $filename = uniqid($prefix) . '.' . $file->getClientOriginalExtension();
        $file->move(public_path($dir), $filename);
        return "{$dir}/{$filename}";
    }

    /**
     * Discover public channels.
     */
    public function index(Request $request): JsonResponse
    {
        if (!Schema::hasTable('Wo_Blog_Channels')) {
            return response()->json([
                'ok' => true,
                'data' => [],
                'meta' => ['current_page' => 1, 'per_page' => 12, 'total' => 0, 'last_page' => 1],
            ]);
        }

        $viewerId = $this->authUserId($request);
        $perPage = max(1, min(50, (int) $request->query('per_page', 12)));
        $term = trim((string) $request->query('term', $request->query('q', '')));

        $query = BlogChannel::query()->where('active', '1')->orderByDesc('id');
        if ($term !== '') {
            $like = '%' . str_replace('%', '\\%', $term) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('description', 'like', $like)
                  ->orWhere('slug', 'like', $like);
            });
        }

        $paginator = $query->paginate($perPage);
        $data = $paginator->getCollection()->map(
            fn (BlogChannel $channel) => $channel->toSummaryArray($viewerId)
        );

        return response()->json([
            'ok' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Channels owned by the authenticated user.
     */
    public function my(Request $request): JsonResponse
    {
        $auth = $this->requireAuth($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }
        $userId = $auth;

        if (!Schema::hasTable('Wo_Blog_Channels')) {
            return response()->json([
                'ok' => true,
                'data' => [],
                'meta' => ['current_page' => 1, 'per_page' => 50, 'total' => 0, 'last_page' => 1],
            ]);
        }

        $perPage = max(1, min(50, (int) $request->query('per_page', 50)));
        $paginator = BlogChannel::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = $paginator->getCollection()->map(
            fn (BlogChannel $channel) => $channel->toSummaryArray($userId)
        );

        return response()->json([
            'ok' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        if (!Schema::hasTable('Wo_Blog_Channels')) {
            return response()->json(['ok' => false, 'message' => 'Channel not found'], 404);
        }

        $channel = BlogChannel::query()->where('id', (int) $id)->first();
        if (!$channel || ($channel->active != '1' && $channel->active != 1)) {
            $viewerId = $this->authUserId($request);
            if (!$channel || !$viewerId || (string) $channel->user_id !== (string) $viewerId) {
                return response()->json(['ok' => false, 'message' => 'Channel not found'], 404);
            }
        }

        $viewerId = $this->authUserId($request);
        return response()->json([
            'ok' => true,
            'data' => $channel->toSummaryArray($viewerId),
        ]);
    }

    /**
     * Owner dashboard: channel stats and recent blogs.
     */
    public function dashboard(Request $request, $id): JsonResponse
    {
        $auth = $this->requireAuth($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }
        $userId = $auth;

        if (!Schema::hasTable('Wo_Blog_Channels')) {
            return response()->json(['ok' => false, 'message' => 'Channel not found'], 404);
        }

        $channel = BlogChannel::query()->where('id', (int) $id)->first();
        if (!$channel) {
            return response()->json(['ok' => false, 'message' => 'Channel not found'], 404);
        }
        if ((string) $channel->user_id !== (string) $userId) {
            return response()->json(['ok' => false, 'message' => 'You are not the channel owner'], 403);
        }

        $blogQuery = Article::query()
            ->where('channel_id', $channel->id)
            ->where('active', '1');

        $totalViews = (int) (clone $blogQuery)->sum('view');
        $blogIds = (clone $blogQuery)->pluck('id');
        $totalComments = 0;
        if ($blogIds->isNotEmpty() && Schema::hasTable('Wo_BlogComments')) {
            $totalComments = (int) DB::table('Wo_BlogComments')
                ->whereIn('blog_id', $blogIds->all())
                ->count();
        }

        $recentBlogs = Article::query()
            ->where('channel_id', $channel->id)
            ->where('active', '1')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (Article $article) {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'thumbnail' => $article->thumbnail_url,
                    'posted_at' => $article->posted_date,
                    'views_count' => $article->views_count,
                    'comments_count' => $article->comments_count,
                    'url' => $article->url,
                ];
            });

        // Top blogs by views for performance chart
        $topBlogs = Article::query()
            ->where('channel_id', $channel->id)
            ->where('active', '1')
            ->orderByDesc('view')
            ->limit(8)
            ->get()
            ->map(function (Article $article) {
                $title = (string) ($article->title ?? 'Untitled');
                if (mb_strlen($title) > 28) {
                    $title = mb_substr($title, 0, 28) . '…';
                }
                return [
                    'id' => $article->id,
                    'title' => $title,
                    'views' => (int) ($article->view ?? 0),
                    'comments' => (int) ($article->comments_count ?? 0),
                ];
            })
            ->values()
            ->all();

        // Monthly publish + views trend (last 6 months, based on posted timestamp)
        $monthlyMap = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = strtotime(date('Y-m-01', strtotime("-{$i} months")));
            $key = date('Y-m', $monthStart);
            $monthlyMap[$key] = [
                'month' => date('M Y', $monthStart),
                'key' => $key,
                'blogs' => 0,
                'views' => 0,
            ];
        }

        $allChannelBlogs = Article::query()
            ->where('channel_id', $channel->id)
            ->where('active', '1')
            ->get(['id', 'posted', 'view']);

        foreach ($allChannelBlogs as $article) {
            $posted = (int) ($article->posted ?? 0);
            if ($posted <= 0) {
                continue;
            }
            $key = date('Y-m', $posted);
            if (!isset($monthlyMap[$key])) {
                continue;
            }
            $monthlyMap[$key]['blogs'] += 1;
            $monthlyMap[$key]['views'] += (int) ($article->view ?? 0);
        }

        $monthlyActivity = array_values($monthlyMap);

        return response()->json([
            'ok' => true,
            'data' => [
                'channel' => $channel->toSummaryArray($userId),
                'stats' => [
                    'followers_count' => $channel->followers_count,
                    'blogs_count' => $channel->blogs_count,
                    'total_views' => $totalViews,
                    'total_comments' => $totalComments,
                ],
                'charts' => [
                    'overview' => [
                        ['name' => 'Followers', 'value' => (int) $channel->followers_count],
                        ['name' => 'Blogs', 'value' => (int) $channel->blogs_count],
                        ['name' => 'Views', 'value' => $totalViews],
                        ['name' => 'Comments', 'value' => $totalComments],
                    ],
                    'blog_performance' => $topBlogs,
                    'monthly_activity' => $monthlyActivity,
                ],
                'recent_blogs' => $recentBlogs,
            ],
        ]);
    }

    public function blogs(Request $request, $id): JsonResponse
    {
        if (!Schema::hasTable('Wo_Blog_Channels')) {
            return response()->json(['ok' => false, 'message' => 'Channel not found'], 404);
        }

        $channel = BlogChannel::query()->where('id', (int) $id)->where('active', '1')->first();
        if (!$channel) {
            return response()->json(['ok' => false, 'message' => 'Channel not found'], 404);
        }

        $viewerId = $this->authUserId($request);
        $perPage = max(1, min(50, (int) $request->query('per_page', 10)));
        $paginator = Article::query()
            ->where('channel_id', $channel->id)
            ->where('active', '1')
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Article $article) use ($viewerId, $channel) {
            $ownerId = optional($article->user)->user_id ?? $article->user ?? null;
            return [
                'id' => $article->id,
                'title' => $article->title,
                'description' => $article->description,
                'excerpt' => $article->excerpt,
                'thumbnail' => $article->thumbnail_url,
                'posted_at' => $article->posted_date,
                'views_count' => $article->views_count,
                'comments_count' => $article->comments_count,
                'url' => $article->url,
                'is_owner' => $viewerId && (string) $ownerId === (string) $viewerId,
                'channel' => [
                    'id' => (int) $channel->id,
                    'name' => $channel->name,
                    'slug' => $channel->slug,
                    'url' => $channel->url,
                ],
                'user' => [
                    'user_id' => $ownerId,
                    'username' => optional($article->user)->username,
                    'avatar_url' => optional($article->user)->avatar_url ?? null,
                ],
            ];
        });

        return response()->json([
            'ok' => true,
            'data' => $data,
            'channel' => $channel->toSummaryArray($viewerId),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $auth = $this->requireAuth($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }
        $userId = $auth;

        if (!Schema::hasTable('Wo_Blog_Channels')) {
            return response()->json(['ok' => false, 'message' => 'Channels are not available'], 500);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ]);

        $avatarPath = null;
        $coverPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $this->uploadImage($request->file('avatar'), 'channel_av_');
        }
        if ($request->hasFile('cover')) {
            $coverPath = $this->uploadImage($request->file('cover'), 'channel_cv_');
        }

        $channel = new BlogChannel();
        $channel->user_id = $userId;
        $channel->name = trim($validated['name']);
        $channel->slug = BlogChannel::makeUniqueSlug($channel->name);
        $channel->description = $validated['description'] ?? '';
        $channel->avatar = $avatarPath ?? '';
        $channel->cover = $coverPath ?? '';
        $channel->active = '1';
        $channel->time = time();
        $channel->save();

        return response()->json([
            'ok' => true,
            'api_status' => 200,
            'message' => 'Channel created successfully',
            'data' => $channel->toSummaryArray($userId),
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $auth = $this->requireAuth($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }
        $userId = $auth;

        $channel = BlogChannel::query()->where('id', (int) $id)->first();
        if (!$channel) {
            return response()->json(['ok' => false, 'message' => 'Channel not found'], 404);
        }
        if ((string) $channel->user_id !== (string) $userId) {
            return response()->json(['ok' => false, 'message' => 'You are not the channel owner'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'active' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('name', $validated) && trim($validated['name']) !== '') {
            $newName = trim($validated['name']);
            if ($newName !== $channel->name) {
                $channel->name = $newName;
                $channel->slug = BlogChannel::makeUniqueSlug($newName, (int) $channel->id);
            }
        }
        if (array_key_exists('description', $validated)) {
            $channel->description = $validated['description'] ?? '';
        }
        if (array_key_exists('active', $validated)) {
            $channel->active = $validated['active'] ? '1' : '0';
        }
        if ($request->hasFile('avatar')) {
            $channel->avatar = $this->uploadImage($request->file('avatar'), 'channel_av_');
        }
        if ($request->hasFile('cover')) {
            $channel->cover = $this->uploadImage($request->file('cover'), 'channel_cv_');
        }
        $channel->save();

        return response()->json([
            'ok' => true,
            'api_status' => 200,
            'message' => 'Channel updated successfully',
            'data' => $channel->fresh()->toSummaryArray($userId),
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $auth = $this->requireAuth($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }
        $userId = $auth;

        $channel = BlogChannel::query()->where('id', (int) $id)->first();
        if (!$channel) {
            return response()->json(['ok' => false, 'message' => 'Channel not found'], 404);
        }
        if ((string) $channel->user_id !== (string) $userId) {
            return response()->json(['ok' => false, 'message' => 'You are not the channel owner'], 403);
        }

        // Null out blogs' channel_id, remove followers, then delete channel
        if (Schema::hasTable('Wo_Blog') && Schema::hasColumn('Wo_Blog', 'channel_id')) {
            DB::table('Wo_Blog')->where('channel_id', $channel->id)->update(['channel_id' => null]);
        }
        if (Schema::hasTable('Wo_Blog_Channel_Followers')) {
            DB::table('Wo_Blog_Channel_Followers')->where('channel_id', $channel->id)->delete();
        }
        $channel->delete();

        return response()->json([
            'ok' => true,
            'api_status' => 200,
            'message' => 'Channel deleted successfully',
        ]);
    }

    public function toggleFollow(Request $request, $id): JsonResponse
    {
        $auth = $this->requireAuth($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }
        $userId = $auth;

        if (!Schema::hasTable('Wo_Blog_Channel_Followers')) {
            return response()->json(['ok' => false, 'message' => 'Follow system unavailable'], 500);
        }

        $channel = BlogChannel::query()->where('id', (int) $id)->where('active', '1')->first();
        if (!$channel) {
            return response()->json(['ok' => false, 'message' => 'Channel not found'], 404);
        }

        if ((string) $channel->user_id === (string) $userId) {
            return response()->json(['ok' => false, 'message' => 'You cannot follow your own channel'], 422);
        }

        $existing = DB::table('Wo_Blog_Channel_Followers')
            ->where('channel_id', $channel->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            DB::table('Wo_Blog_Channel_Followers')
                ->where('channel_id', $channel->id)
                ->where('user_id', $userId)
                ->delete();
            $following = false;
            $message = 'Unfollowed channel';
        } else {
            DB::table('Wo_Blog_Channel_Followers')->insert([
                'channel_id' => $channel->id,
                'user_id' => $userId,
                'time' => time(),
            ]);
            $following = true;
            $message = 'Following channel';
        }

        return response()->json([
            'ok' => true,
            'api_status' => 200,
            'message' => $message,
            'is_following' => $following,
            'followers_count' => $channel->followers_count,
            'data' => $channel->fresh()->toSummaryArray($userId),
        ]);
    }
}
