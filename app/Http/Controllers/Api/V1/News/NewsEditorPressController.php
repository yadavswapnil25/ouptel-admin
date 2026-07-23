<?php

namespace App\Http\Controllers\Api\V1\News;

use App\Models\NewsCategory;
use App\Models\NewsEditor;
use App\Models\NewsPressProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NewsEditorPressController extends Controller
{
    /**
     * Live slug availability check for press setup/settings.
     */
    public function checkSlug(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $raw = (string) $request->query('slug', '');
        $slug = NewsPressProfile::normalizeSlug($raw);
        $mine = $this->findMyPress($userId);

        $available = NewsPressProfile::isSlugAvailable($slug, $mine?->id);
        $reason = null;

        if ($slug === '') {
            $reason = 'Slug is required.';
        } elseif (NewsPressProfile::isReservedSlug($slug)) {
            $reason = 'This slug is reserved.';
        } elseif (!$available) {
            $reason = 'This slug is already taken.';
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'slug' => $slug,
                'suggested' => $slug,
                'available' => $available,
                'reason' => $reason,
                'previewPath' => $slug !== '' ? '/news/press/' . $slug : null,
                'previewUrlHint' => $slug !== '' ? 'ouptel.in/news/press/' . $slug : null,
            ],
        ]);
    }

    /**
     * Current editor's press profile (null if not set up yet).
     */
    public function me(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $press = $this->findMyPress($userId);

        return response()->json([
            'status' => 'success',
            'data' => $press
                ? $this->formatEditorPress($press->load(['categories' => fn ($q) => $q->ordered()]))
                : null,
        ]);
    }

    /**
     * First-time press page setup.
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        if ($this->findMyPress($userId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Press profile already exists. Use press settings to update it.',
            ], 422);
        }

        $editor = NewsEditor::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$editor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Editor access required.',
            ], 403);
        }

        $validated = $this->validateProfilePayload($request, null);
        $slug = NewsPressProfile::normalizeSlug($validated['slug']);

        if (!NewsPressProfile::isSlugAvailable($slug)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This slug is already taken or reserved.',
                'errors' => ['slug' => ['This slug is already taken or reserved.']],
            ], 422);
        }

        $press = NewsPressProfile::create([
            'editor_id' => $editor->id,
            'user_id' => (int) $userId,
            'name' => $validated['name'],
            'slug' => $slug,
            'logo' => $validated['logo'] ?? null,
            'banner_image' => $validated['banner_image'] ?? null,
            'tagline' => $validated['tagline'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'social_links' => $validated['social_links'] ?? [],
            'status' => NewsPressProfile::STATUS_ACTIVE,
        ]);

        $this->syncCategories($press, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Press page created.',
            'data' => $this->formatEditorPress($press->fresh(['categories' => fn ($q) => $q->ordered()])),
        ], 201);
    }

    /**
     * Update press settings (slug change requires confirmation).
     */
    public function update(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $press = $this->findMyPress($userId);
        if (!$press) {
            return response()->json([
                'status' => 'error',
                'message' => 'Press profile not found. Complete press setup first.',
            ], 404);
        }

        $validated = $this->validateProfilePayload($request, $press);
        $newSlug = NewsPressProfile::normalizeSlug($validated['slug']);
        $slugChanging = $newSlug !== $press->slug;

        if ($slugChanging) {
            if (!$request->boolean('confirm_slug_change')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Changing your slug will stop your old link from working. Confirm to continue.',
                    'code' => 'slug_change_confirmation_required',
                    'data' => [
                        'oldSlug' => $press->slug,
                        'newSlug' => $newSlug,
                        'oldPath' => $press->publicPath(),
                        'newPath' => '/news/press/' . $newSlug,
                    ],
                ], 422);
            }

            if (!NewsPressProfile::isSlugAvailable($newSlug, $press->id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This slug is already taken or reserved.',
                    'errors' => ['slug' => ['This slug is already taken or reserved.']],
                ], 422);
            }
        }

        $press->update([
            'name' => $validated['name'],
            'slug' => $newSlug,
            'logo' => $validated['logo'] ?? null,
            'banner_image' => array_key_exists('banner_image', $validated)
                ? ($validated['banner_image'] ?? null)
                : $press->banner_image,
            'tagline' => $validated['tagline'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'social_links' => $validated['social_links'] ?? ($press->social_links ?? []),
        ]);

        if (
            array_key_exists('category_ids', $validated)
            || array_key_exists('categories', $validated)
            || array_key_exists('new_categories', $validated)
        ) {
            $this->syncCategories($press, $validated);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Press settings updated.',
            'data' => $this->formatEditorPress($press->fresh(['categories' => fn ($q) => $q->ordered()])),
        ]);
    }

    /**
     * Attach existing category or create a new one for this press.
     */
    public function addCategory(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $press = $this->findMyPress($userId);
        if (!$press) {
            return response()->json([
                'status' => 'error',
                'message' => 'Press profile not found. Complete press setup first.',
            ], 404);
        }

        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:news_categories,id'],
            'name' => ['nullable', 'string', 'max:100', 'required_without:category_id'],
        ]);

        $category = null;

        if (!empty($validated['category_id'])) {
            $category = NewsCategory::query()->find($validated['category_id']);
        } else {
            $name = trim($validated['name']);
            $slug = Str::slug($name) ?: 'category-' . time();
            $category = NewsCategory::query()
                ->where(function ($q) use ($name, $slug) {
                    $q->whereRaw('LOWER(name) = ?', [strtolower($name)])
                        ->orWhere('slug', $slug);
                })
                ->first();

            if (!$category) {
                $maxOrder = (int) NewsCategory::query()->max('display_order');
                $category = NewsCategory::create([
                    'name' => $name,
                    'slug' => $this->uniqueCategorySlug($slug),
                    'description' => null,
                    'icon' => null,
                    'color' => '#2563EB',
                    'display_order' => $maxOrder + 1,
                    'status' => true,
                ]);
            }
        }

        $press->categories()->syncWithoutDetaching([$category->id]);

        return response()->json([
            'status' => 'success',
            'message' => 'Category added to your press.',
            'data' => $this->formatEditorPress($press->fresh(['categories' => fn ($q) => $q->ordered()])),
        ]);
    }

    public function removeCategory(Request $request, int $categoryId): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $press = $this->findMyPress($userId);
        if (!$press) {
            return response()->json([
                'status' => 'error',
                'message' => 'Press profile not found. Complete press setup first.',
            ], 404);
        }

        $press->categories()->detach($categoryId);

        return response()->json([
            'status' => 'success',
            'message' => 'Category removed from your press.',
            'data' => $this->formatEditorPress($press->fresh(['categories' => fn ($q) => $q->ordered()])),
        ]);
    }

    protected function validateProfilePayload(Request $request, ?NewsPressProfile $existing): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(NewsPressProfile::RESERVED_SLUGS),
            ],
            'logo' => ['nullable', 'string', 'max:2048'],
            'banner_image' => ['nullable', 'string', 'max:2048'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'social_links' => ['nullable', 'array'],
            'social_links.facebook' => ['nullable', 'string', 'max:512'],
            'social_links.twitter' => ['nullable', 'string', 'max:512'],
            'social_links.instagram' => ['nullable', 'string', 'max:512'],
            'social_links.youtube' => ['nullable', 'string', 'max:512'],
            'social_links.website' => ['nullable', 'string', 'max:512'],
            'category_ids' => [$existing ? 'sometimes' : 'required', 'array', $existing ? 'nullable' : 'min:1'],
            'category_ids.*' => ['integer', 'exists:news_categories,id'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['string', 'max:100'],
            'new_categories' => ['sometimes', 'array'],
            'new_categories.*' => ['string', 'max:100'],
            'confirm_slug_change' => ['sometimes', 'boolean'],
        ]);
    }

    protected function syncCategories(NewsPressProfile $press, array $validated): void
    {
        $ids = [];

        foreach ($validated['category_ids'] ?? [] as $id) {
            $ids[] = (int) $id;
        }

        $names = array_merge(
            $validated['categories'] ?? [],
            $validated['new_categories'] ?? []
        );

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $slug = Str::slug($name) ?: 'category-' . time();
            $category = NewsCategory::query()
                ->where(function ($q) use ($name, $slug) {
                    $q->whereRaw('LOWER(name) = ?', [strtolower($name)])
                        ->orWhere('slug', $slug);
                })
                ->first();

            if (!$category) {
                $maxOrder = (int) NewsCategory::query()->max('display_order');
                $category = NewsCategory::create([
                    'name' => $name,
                    'slug' => $this->uniqueCategorySlug($slug),
                    'description' => null,
                    'icon' => null,
                    'color' => '#2563EB',
                    'display_order' => $maxOrder + 1,
                    'status' => true,
                ]);
            }

            $ids[] = (int) $category->id;
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (!empty($ids)) {
            $press->categories()->sync($ids);
        }
    }

    protected function uniqueCategorySlug(string $base): string
    {
        $slug = $base;
        $i = 1;

        while (NewsCategory::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    protected function formatEditorPress(NewsPressProfile $press): array
    {
        $categories = $press->relationLoaded('categories')
            ? $press->categories
            : $press->categories()->ordered()->get();

        return [
            'id' => $press->id,
            'editorId' => $press->editor_id,
            'userId' => (string) $press->user_id,
            'name' => $press->name,
            'slug' => $press->slug,
            'logo' => $press->logo,
            'bannerImage' => $press->banner_image,
            'tagline' => $press->tagline,
            'contactEmail' => $press->contact_email,
            'socialLinks' => $press->social_links ?? [],
            'status' => $press->status,
            'publicPath' => $press->publicPath(),
            'publicUrlHint' => 'ouptel.in' . $press->publicPath(),
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'color' => $c->color,
            ])->values()->all(),
            'articleCount' => $press->articles()->count(),
            'publishedArticleCount' => $press->articles()->published()->count(),
            'createdAt' => optional($press->created_at)?->toIso8601String(),
            'updatedAt' => optional($press->updated_at)?->toIso8601String(),
        ];
    }

    protected function findMyPress(string|int $userId): ?NewsPressProfile
    {
        return NewsPressProfile::query()
            ->where('user_id', $userId)
            ->first();
    }

    protected function requireEditor(Request $request): string|JsonResponse
    {
        $userId = $this->resolveTokenUserId($request);
        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        if (!NewsEditor::isActiveEditor($userId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Editor access required.',
            ], 403);
        }

        return (string) $userId;
    }

    protected function resolveTokenUserId(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);

        return DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
    }
}
