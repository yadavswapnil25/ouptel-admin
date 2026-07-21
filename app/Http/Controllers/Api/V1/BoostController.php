<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class BoostController extends Controller
{
    private const DEFAULT_BOOST_LIMIT = 3;

    public function stats(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return $this->unauthorized();
        }

        $limit = $this->getBoostLimit();
        $boostedCount = $this->getBoostedPostCount($userId);
        $campaignCount = Schema::hasTable('Wo_Boost_Campaigns')
            ? DB::table('Wo_Boost_Campaigns')->where('user_id', $userId)->where('status', 'active')->count()
            : 0;

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'data' => [
                'boost_limit' => $limit,
                'boosted_posts' => $boostedCount,
                'active_campaigns' => $campaignCount,
                'remaining' => max(0, $limit - $boostedCount),
            ],
        ]);
    }

    public function posts(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return $this->unauthorized();
        }

        $perPage = max(1, min((int) $request->query('per_page', 20), 50));
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $posts = DB::table('Wo_Posts')
            ->where('user_id', $userId)
            ->where('active', 1)
            ->orderByDesc('time')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $items = $posts->map(function ($post) {
            $photoUrl = $this->resolvePostThumbnailUrl($post);

            return [
                'id' => $post->id,
                'post_id' => $post->post_id ?? $post->id,
                'post_text' => $post->postText ?? '',
                'post_type' => $post->postType ?? 'text',
                'post_photo_url' => $photoUrl,
                'is_boosted' => (bool) ($post->boosted ?? 0),
                'created_at' => !empty($post->time) ? date('c', (int) $post->time) : null,
            ];
        })->values();

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'data' => $items,
        ]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return $this->unauthorized();
        }

        if (!Schema::hasTable('Wo_Boost_Campaigns')) {
            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'data' => [],
            ]);
        }

        $status = $request->query('status');
        $query = DB::table('Wo_Boost_Campaigns')->where('user_id', $userId)->orderByDesc('id');
        if ($status) {
            $query->where('status', $status);
        }

        $campaigns = $query->limit(50)->get();
        $postIds = $campaigns->pluck('post_id')->filter()->unique()->values()->all();
        $postsById = $postIds
            ? DB::table('Wo_Posts')->whereIn('id', $postIds)->get()->keyBy('id')
            : collect();

        $items = $campaigns->map(function ($campaign) use ($postsById) {
            $post = $postsById->get($campaign->post_id);
            $photoUrl = $post ? $this->resolvePostThumbnailUrl($post) : null;

            return [
                'id' => $campaign->id,
                'post_id' => $campaign->post_id,
                'post_text' => $post->postText ?? '',
                'post_photo_url' => $photoUrl,
                'goal' => $campaign->goal,
                'audience_gender' => $campaign->audience_gender,
                'audience_countries' => $campaign->audience_countries
                    ? explode(',', $campaign->audience_countries)
                    : [],
                'duration_days' => (int) $campaign->duration_days,
                'budget' => (float) $campaign->budget,
                'status' => $campaign->status,
                'starts_at' => (int) $campaign->starts_at,
                'ends_at' => (int) $campaign->ends_at,
                'created_at' => (int) $campaign->created_at,
            ];
        })->values();

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return $this->unauthorized();
        }

        if (!Schema::hasTable('Wo_Boost_Campaigns')) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'message' => 'Boost campaigns are not available yet. Run database migrations.',
            ], 500);
        }

        $validator = Validator::make($request->all(), [
            'post_id' => 'required|integer|min:1',
            'goal' => 'nullable|in:reach,engagement,traffic',
            'audience_gender' => 'nullable|in:all,male,female',
            'audience_countries' => 'nullable|array',
            'audience_countries.*' => 'integer|min:1',
            'duration_days' => 'nullable|integer|min:1|max:90',
            'budget' => 'nullable|numeric|min:100|max:25000',
            'activate' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $postId = (int) $request->input('post_id');
        $post = DB::table('Wo_Posts')
            ->where('id', $postId)
            ->where('user_id', $userId)
            ->where('active', 1)
            ->first();

        if (!$post) {
            return response()->json([
                'api_status' => '404',
                'api_text' => 'failed',
                'message' => 'Post not found or you are not the owner.',
            ], 404);
        }

        $activate = filter_var($request->input('activate', true), FILTER_VALIDATE_BOOLEAN);
        $durationDays = (int) ($request->input('duration_days', 7));
        $now = time();
        $status = $activate ? 'active' : 'draft';
        $startsAt = $activate ? $now : 0;
        $endsAt = $activate ? $now + ($durationDays * 86400) : 0;

        if ($activate && !$this->canBoostMore($userId, $postId)) {
            $this->unboostOldestPost($userId);
        }

        $countries = $request->input('audience_countries', []);
        $countryCsv = is_array($countries) ? implode(',', array_map('intval', $countries)) : '';

        $campaignId = DB::table('Wo_Boost_Campaigns')->insertGetId([
            'user_id' => $userId,
            'post_id' => $postId,
            'goal' => $request->input('goal', 'reach'),
            'audience_gender' => $request->input('audience_gender', 'all'),
            'audience_countries' => $countryCsv,
            'duration_days' => $durationDays,
            'budget' => (float) $request->input('budget', 0),
            'status' => $status,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($activate) {
            DB::table('Wo_Posts')->where('id', $postId)->update(['boosted' => '1']);
        }

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'message' => $activate ? 'Post boosted successfully.' : 'Boost campaign saved as draft.',
            'data' => ['campaign_id' => $campaignId],
        ], 201);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return $this->unauthorized();
        }

        if (!Schema::hasTable('Wo_Boost_Campaigns')) {
            return response()->json(['api_status' => '404', 'message' => 'Not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,paused,completed,draft',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $campaign = DB::table('Wo_Boost_Campaigns')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$campaign) {
            return response()->json(['api_status' => '404', 'message' => 'Campaign not found'], 404);
        }

        $status = $request->input('status');
        $now = time();
        $update = [
            'status' => $status,
            'updated_at' => $now,
        ];

        if ($status === 'active') {
            if (!$this->canBoostMore($userId, (int) $campaign->post_id)) {
                $this->unboostOldestPost($userId);
            }
            $update['starts_at'] = $now;
            $update['ends_at'] = $now + ((int) $campaign->duration_days * 86400);
            DB::table('Wo_Posts')->where('id', $campaign->post_id)->update(['boosted' => '1']);
        } else {
            DB::table('Wo_Posts')->where('id', $campaign->post_id)->update(['boosted' => '0']);
        }

        DB::table('Wo_Boost_Campaigns')->where('id', $id)->update($update);

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'message' => 'Campaign status updated.',
        ]);
    }

    public function togglePost(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return $this->unauthorized();
        }

        $validator = Validator::make($request->all(), [
            'post_id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $postId = (int) $request->input('post_id');
        $post = DB::table('Wo_Posts')
            ->where('id', $postId)
            ->where('user_id', $userId)
            ->first();

        if (!$post) {
            return response()->json([
                'api_status' => '404',
                'api_text' => 'failed',
                'message' => 'Post not found or you are not the owner.',
            ], 404);
        }

        $isBoosted = (string) ($post->boosted ?? '0') === '1';
        if ($isBoosted) {
            DB::table('Wo_Posts')->where('id', $postId)->update(['boosted' => '0']);
            if (Schema::hasTable('Wo_Boost_Campaigns')) {
                DB::table('Wo_Boost_Campaigns')
                    ->where('user_id', $userId)
                    ->where('post_id', $postId)
                    ->where('status', 'active')
                    ->update(['status' => 'paused', 'updated_at' => time()]);
            }

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'action' => 'unboosted',
                'is_boosted' => false,
            ]);
        }

        if (!$this->canBoostMore($userId, $postId)) {
            $this->unboostOldestPost($userId);
        }

        DB::table('Wo_Posts')->where('id', $postId)->update(['boosted' => '1']);

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'action' => 'boosted',
            'is_boosted' => true,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return $this->unauthorized();
        }

        if (!Schema::hasTable('Wo_Boost_Campaigns')) {
            return response()->json(['api_status' => '404', 'message' => 'Not found'], 404);
        }

        $campaign = DB::table('Wo_Boost_Campaigns')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$campaign) {
            return response()->json(['api_status' => '404', 'message' => 'Campaign not found'], 404);
        }

        DB::table('Wo_Posts')->where('id', $campaign->post_id)->update(['boosted' => '0']);
        DB::table('Wo_Boost_Campaigns')->where('id', $id)->delete();

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'message' => 'Boost campaign deleted.',
        ]);
    }

    private function resolveUserId(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);

        return DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'api_status' => '401',
            'api_text' => 'failed',
            'message' => 'Unauthorized',
        ], 401);
    }

    private function getBoostLimit(): int
    {
        try {
            $config = DB::table('Wo_Config')->pluck('value', 'name');
            foreach (['lifetime_boosts', 'yearly_boosts', 'monthly_boosts'] as $key) {
                $value = (int) ($config[$key] ?? 0);
                if ($value > 0) {
                    return $value;
                }
            }
        } catch (\Throwable $e) {
            // use default
        }

        return self::DEFAULT_BOOST_LIMIT;
    }

    private function getBoostedPostCount(string $userId): int
    {
        return (int) DB::table('Wo_Posts')
            ->where('user_id', $userId)
            ->where(function ($q) {
                $q->where('boosted', '1')->orWhere('boosted', 1);
            })
            ->count();
    }

    private function canBoostMore(string $userId, int $excludePostId = 0): bool
    {
        $query = DB::table('Wo_Posts')
            ->where('user_id', $userId)
            ->where(function ($q) {
                $q->where('boosted', '1')->orWhere('boosted', 1);
            });

        if ($excludePostId > 0) {
            $query->where('id', '!=', $excludePostId);
        }

        return $query->count() < $this->getBoostLimit();
    }

    private function unboostOldestPost(string $userId): void
    {
        $oldest = DB::table('Wo_Posts')
            ->where('user_id', $userId)
            ->where(function ($q) {
                $q->where('boosted', '1')->orWhere('boosted', 1);
            })
            ->orderBy('id', 'asc')
            ->first();

        if ($oldest) {
            DB::table('Wo_Posts')->where('id', $oldest->id)->update(['boosted' => '0']);
            if (Schema::hasTable('Wo_Boost_Campaigns')) {
                DB::table('Wo_Boost_Campaigns')
                    ->where('user_id', $userId)
                    ->where('post_id', $oldest->id)
                    ->where('status', 'active')
                    ->update(['status' => 'paused', 'updated_at' => time()]);
            }
        }
    }

    private function resolvePostThumbnailUrl(object $post): ?string
    {
        $postType = strtolower((string) ($post->postType ?? ''));
        $photo = trim((string) ($post->postPhoto ?? ''));

        if ($photo !== '') {
            if ($postType === 'gif' || filter_var($photo, FILTER_VALIDATE_URL)) {
                return preg_replace('#([^:])//+#', '$1/', $photo);
            }

            $resolved = $this->resolveStorageMediaUrl($photo);
            if ($resolved) {
                return $resolved;
            }
        }

        $albumImage = $this->firstAlbumImagePath((int) ($post->id ?? 0));
        if ($albumImage) {
            return $this->resolveStorageMediaUrl($albumImage);
        }

        $file = trim((string) ($post->postFile ?? ''));
        if ($file !== '' && $this->isImagePath($file)) {
            return $this->resolveStorageMediaUrl($file);
        }

        return null;
    }

    private function firstAlbumImagePath(int $postId): ?string
    {
        if ($postId <= 0 || !Schema::hasTable('Wo_Albums_Media')) {
            return null;
        }

        $image = DB::table('Wo_Albums_Media')
            ->where('post_id', $postId)
            ->orderBy('id')
            ->value('image');

        $image = trim((string) $image);

        return $image !== '' ? $image : null;
    }

    private function resolveStorageMediaUrl(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return preg_replace('#([^:])//+#', '$1/', $path);
        }

        $normalized = ltrim($path, '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, 8);
        }

        $url = ImageHelper::getImageUrl($normalized, 'post');
        $placeholder = ImageHelper::getPlaceholder('post');

        return $url !== $placeholder ? $url : null;
    }

    private function isImagePath(string $path): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|gif|webp|bmp|svg)$/i', $path);
    }
}
