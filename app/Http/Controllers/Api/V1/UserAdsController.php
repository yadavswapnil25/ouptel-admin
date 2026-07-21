<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserAdsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return $this->unauthorized();
        }

        if (!Schema::hasTable('Wo_UserAds')) {
            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'data' => [],
            ]);
        }

        $status = $request->query('status');
        $query = DB::table('Wo_UserAds')->where('user_id', $userId)->orderByDesc('id');
        if ($status) {
            $this->applyStatusFilter($query, (string) $status);
        }

        $ads = $query->limit(50)->get()->map(fn ($ad) => $this->formatAd($ad))->values();

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'data' => $ads,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return $this->unauthorized();
        }

        if (!Schema::hasTable('Wo_UserAds')) {
            return response()->json(['api_status' => '404', 'message' => 'Not found'], 404);
        }

        $ad = DB::table('Wo_UserAds')->where('id', $id)->where('user_id', $userId)->first();
        if (!$ad) {
            return response()->json(['api_status' => '404', 'message' => 'Ad not found'], 404);
        }

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'data' => $this->formatAd($ad),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return $this->unauthorized();
        }

        if (!Schema::hasTable('Wo_UserAds')) {
            return response()->json([
                'api_status' => '500',
                'message' => 'Advertisements are not available yet. Run database migrations.',
            ], 500);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:100',
            'website' => 'required|url|max:512',
            'headline' => 'required|string|min:5|max:200',
            'description' => 'required|string|min:5|max:2000',
            'location' => 'nullable|string|max:255',
            'audience_countries' => 'nullable|array',
            'audience_countries.*' => 'integer|min:1',
            'gender' => 'nullable|in:all,male,female',
            'age_group' => 'nullable|in:all,18-24,25-34,35-44,45-54,55+',
            'community_preferences' => 'nullable|array',
            'community_preferences.*' => 'integer|min:1',
            'bidding' => 'nullable|in:clicks,views',
            'appears' => 'nullable|in:post,sidebar,video',
            'budget' => 'nullable|numeric|min:100|max:25000',
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
            'media' => 'required|file|mimes:jpeg,jpg,png,gif,webp,mp4,mov,avi,webm|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $appears = $request->input('appears', 'post');
        $media = $request->file('media');
        if ($media) {
            $mime = $media->getMimeType() ?? '';
            $isImage = str_starts_with($mime, 'image/');
            $isVideo = str_starts_with($mime, 'video/');
            if (in_array($appears, ['post', 'sidebar'], true) && !$isImage) {
                return response()->json([
                    'api_status' => '422',
                    'message' => 'Post and sidebar ads require an image.',
                ], 422);
            }
            if ($appears === 'video' && !$isVideo) {
                return response()->json([
                    'api_status' => '422',
                    'message' => 'Video placement requires a video file.',
                ], 422);
            }
        }

        $mediaPath = $this->storeMedia($request->file('media'));
        $countries = $request->input('audience_countries', []);
        $audience = is_array($countries) ? implode(',', array_map('intval', $countries)) : '';
        $communityPrefs = $this->normalizeCommunityPreferences($request->input('community_preferences', []));

        $insert = [
            'user_id' => $userId,
            'page_id' => (int) $request->input('page_id', 0),
            'name' => trim($request->input('name')),
            'url' => trim($request->input('website')),
            'headline' => trim($request->input('headline')),
            'description' => trim($request->input('description')),
            'location' => trim((string) $request->input('location', '')),
            'audience' => $audience,
            'gender' => $request->input('gender', 'all'),
            'bidding' => $request->input('bidding', 'views'),
            'appears' => $appears,
            'ad_media' => $mediaPath,
            'budget' => (float) $request->input('budget', 0),
            'start' => $request->input('start', ''),
            'end' => $request->input('end', ''),
            'posted' => time(),
            'status' => $this->encodeStatus('active'),
            'views' => 0,
            'clicks' => 0,
        ];

        if (Schema::hasColumn('Wo_UserAds', 'age_group')) {
            $insert['age_group'] = $request->input('age_group', 'all');
        }
        if (Schema::hasColumn('Wo_UserAds', 'community_preferences')) {
            $insert['community_preferences'] = $communityPrefs;
        }

        $adId = DB::table('Wo_UserAds')->insertGetId($insert);

        $ad = DB::table('Wo_UserAds')->where('id', $adId)->first();

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'message' => 'Advertisement created successfully.',
            'data' => $this->formatAd($ad),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return $this->unauthorized();
        }

        if (!Schema::hasTable('Wo_UserAds')) {
            return response()->json(['api_status' => '404', 'message' => 'Not found'], 404);
        }

        $ad = DB::table('Wo_UserAds')->where('id', $id)->where('user_id', $userId)->first();
        if (!$ad) {
            return response()->json(['api_status' => '404', 'message' => 'Ad not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|min:3|max:100',
            'website' => 'sometimes|required|url|max:512',
            'headline' => 'sometimes|required|string|min:5|max:200',
            'description' => 'sometimes|required|string|min:5|max:2000',
            'location' => 'nullable|string|max:255',
            'audience_countries' => 'nullable|array',
            'gender' => 'nullable|in:all,male,female',
            'age_group' => 'nullable|in:all,18-24,25-34,35-44,45-54,55+',
            'community_preferences' => 'nullable|array',
            'community_preferences.*' => 'integer|min:1',
            'bidding' => 'nullable|in:clicks,views',
            'appears' => 'nullable|in:post,sidebar,video',
            'budget' => 'nullable|numeric|min:100|max:25000',
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'media' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,mp4,mov,avi,webm|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => '400',
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $update = [];
        foreach (['name', 'headline', 'description', 'location', 'gender', 'bidding', 'appears', 'start', 'end'] as $field) {
            if ($request->has($field)) {
                $update[$field === 'name' ? 'name' : $field] = trim((string) $request->input($field));
            }
        }
        if ($request->has('website')) {
            $update['url'] = trim($request->input('website'));
        }
        if ($request->has('budget')) {
            $update['budget'] = (float) $request->input('budget');
        }
        if ($request->has('audience_countries')) {
            $countries = $request->input('audience_countries', []);
            $update['audience'] = is_array($countries) ? implode(',', array_map('intval', $countries)) : '';
        }
        if ($request->has('age_group') && Schema::hasColumn('Wo_UserAds', 'age_group')) {
            $update['age_group'] = $request->input('age_group', 'all');
        }
        if ($request->has('community_preferences') && Schema::hasColumn('Wo_UserAds', 'community_preferences')) {
            $update['community_preferences'] = $this->normalizeCommunityPreferences(
                $request->input('community_preferences', [])
            );
        }
        if ($request->hasFile('media')) {
            $this->deleteMedia($ad->ad_media ?? '');
            $update['ad_media'] = $this->storeMedia($request->file('media'));
        }

        if (!empty($update)) {
            DB::table('Wo_UserAds')->where('id', $id)->update($update);
        }

        $fresh = DB::table('Wo_UserAds')->where('id', $id)->first();

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'message' => 'Advertisement updated successfully.',
            'data' => $this->formatAd($fresh),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return $this->unauthorized();
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,paused',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => '400',
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $updated = DB::table('Wo_UserAds')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->update(['status' => $this->encodeStatus($request->input('status'))]);

        if (!$updated) {
            return response()->json(['api_status' => '404', 'message' => 'Ad not found'], 404);
        }

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'message' => 'Advertisement status updated.',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return $this->unauthorized();
        }

        $ad = DB::table('Wo_UserAds')->where('id', $id)->where('user_id', $userId)->first();
        if (!$ad) {
            return response()->json(['api_status' => '404', 'message' => 'Ad not found'], 404);
        }

        $this->deleteMedia($ad->ad_media ?? '');
        DB::table('Wo_UserAds')->where('id', $id)->delete();

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'message' => 'Advertisement deleted.',
        ]);
    }

    private function formatAd(object $ad): array
    {
        $media = $ad->ad_media ?? '';

        return [
            'id' => $ad->id,
            'name' => $ad->name ?? '',
            'website' => $ad->url ?? '',
            'headline' => $ad->headline ?? '',
            'description' => $ad->description ?? '',
            'location' => $ad->location ?? '',
            'audience_countries' => !empty($ad->audience)
                ? array_map('intval', explode(',', $ad->audience))
                : [],
            'gender' => $ad->gender ?? 'all',
            'age_group' => $ad->age_group ?? 'all',
            'community_preferences' => $this->parseCommunityPreferences($ad->community_preferences ?? ''),
            'bidding' => $ad->bidding ?? 'views',
            'appears' => $ad->appears ?? 'post',
            'ad_media_url' => $this->mediaUrl($media),
            'budget' => (float) ($ad->budget ?? 0),
            'start' => $ad->start ?? '',
            'end' => $ad->end ?? '',
            'status' => $this->decodeStatus($ad->status ?? null),
            'views' => (int) ($ad->views ?? 0),
            'clicks' => (int) ($ad->clicks ?? 0),
            'posted' => (int) ($ad->posted ?? 0),
        ];
    }

    private function normalizeCommunityPreferences(mixed $preferences): string
    {
        if (!is_array($preferences)) {
            return '';
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $preferences))));

        return implode(',', $ids);
    }

    private function statusColumnIsInteger(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        if (!Schema::hasTable('Wo_UserAds') || !Schema::hasColumn('Wo_UserAds', 'status')) {
            $cached = false;

            return $cached;
        }

        $type = Schema::getColumnType('Wo_UserAds', 'status');
        $cached = in_array($type, ['integer', 'bigint', 'smallint', 'tinyint', 'int'], true);

        return $cached;
    }

    private function encodeStatus(string $status): int|string
    {
        $normalized = $status === 'paused' ? 'paused' : 'active';

        if ($this->statusColumnIsInteger()) {
            return $normalized === 'active' ? 1 : 0;
        }

        return $normalized;
    }

    private function decodeStatus(mixed $status): string
    {
        if ($status === 1 || $status === '1') {
            return 'active';
        }

        if ($status === 0 || $status === '0') {
            return 'paused';
        }

        return in_array($status, ['active', 'paused'], true) ? $status : 'paused';
    }

    private function applyStatusFilter($query, string $status): void
    {
        if ($status === 'active') {
            $query->whereIn('status', $this->statusColumnIsInteger() ? [1, '1'] : ['active']);

            return;
        }

        if ($status === 'paused') {
            $query->whereIn('status', $this->statusColumnIsInteger() ? [0, '0'] : ['paused']);
        }
    }

    private function parseCommunityPreferences(?string $value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $value))));
    }

    private function storeMedia($file): string
    {
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

        return $file->storeAs('ads', $filename, 'public') ?: '';
    }

    private function deleteMedia(string $path): void
    {
        $path = trim($path);
        if ($path === '' || str_starts_with($path, 'http')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function mediaUrl(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
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
}
