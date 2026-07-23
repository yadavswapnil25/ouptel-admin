<?php

namespace App\Http\Controllers\Api\V1\News;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsEditor;
use App\Models\NewsPressProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NewsEditorArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $query = NewsArticle::query()
            ->with('categories')
            ->where('author_id', $userId)
            ->orderByDesc('updated_at');

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $articles = $query->get()->map(fn (NewsArticle $a) => $this->formatArticle($a));

        return response()->json([
            'status' => 'success',
            'data' => $articles,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $mine = NewsArticle::query()->where('author_id', $userId);

        return response()->json([
            'status' => 'success',
            'data' => [
                'total' => (clone $mine)->count(),
                'published' => (clone $mine)->where('status', 'published')->count(),
                'pendingReview' => (clone $mine)->where('status', 'pending_review')->count(),
                'drafts' => (clone $mine)->where('status', 'draft')->count(),
                'rejected' => (clone $mine)->where('status', 'rejected')->count(),
                'totalViews' => (int) (clone $mine)->sum('views'),
            ],
        ]);
    }

    public function show(Request $request, int $article): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $model = NewsArticle::with('categories')
            ->where('author_id', $userId)
            ->find($article);

        if (!$model) {
            return response()->json(['status' => 'error', 'message' => 'Article not found'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatArticle($model),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $validated = $this->validatePayload($request);
        $submit = (bool) $request->boolean('submit_for_review');

        $status = 'draft';
        if ($submit) {
            $status = 'pending_review';
        }

        $user = User::find($userId);
        $authorName = $user?->name
            ?? (trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')) ?: ($user?->username ?? 'Editor'));

        $article = NewsArticle::create([
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($validated['title']),
            'excerpt' => $validated['excerpt'],
            'content' => $validated['content'],
            'featured_image' => $validated['featured_image'] ?? null,
            'tags' => $validated['tags'] ?? [],
            'seo_meta_title' => $validated['seo_meta_title'] ?? null,
            'seo_meta_description' => $validated['seo_meta_description'] ?? null,
            'author_id' => $userId,
            'press_id' => $this->resolvePressId($userId),
            'author_name' => $authorName,
            'status' => $status,
            'submitted_at' => $submit ? now() : null,
            'published_at' => null,
        ]);

        $this->syncCategories($article, $validated['category'] ?? null, $validated['category_ids'] ?? []);

        return response()->json([
            'status' => 'success',
            'message' => $submit ? 'Submitted for review.' : 'Draft saved.',
            'data' => $this->formatArticle($article->fresh('categories')),
        ], 201);
    }

    public function update(Request $request, int $article): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $model = NewsArticle::where('author_id', $userId)->find($article);
        if (!$model) {
            return response()->json(['status' => 'error', 'message' => 'Article not found'], 404);
        }

        if (in_array($model->status, ['published', 'pending_review'], true) && !$request->boolean('submit_for_review')) {
            // Allow edits only for draft/rejected unless submitting again
            if ($model->status === 'published') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Published articles cannot be edited here.',
                ], 422);
            }
        }

        if ($model->status === 'pending_review' && !$request->boolean('force')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Article is awaiting review and cannot be edited.',
            ], 422);
        }

        $validated = $this->validatePayload($request, partial: true);
        $submit = (bool) $request->boolean('submit_for_review');

        $patch = array_filter([
            'title' => $validated['title'] ?? null,
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $validated['content'] ?? null,
            'featured_image' => array_key_exists('featured_image', $validated) ? $validated['featured_image'] : null,
            'tags' => $validated['tags'] ?? null,
            'seo_meta_title' => $validated['seo_meta_title'] ?? null,
            'seo_meta_description' => $validated['seo_meta_description'] ?? null,
        ], fn ($v) => $v !== null);

        if (isset($patch['title'])) {
            $patch['slug'] = $this->uniqueSlug($patch['title'], $model->id);
        }

        if (!$model->press_id) {
            $pressId = $this->resolvePressId($userId);
            if ($pressId) {
                $patch['press_id'] = $pressId;
            }
        }

        if ($submit) {
            $patch['status'] = 'pending_review';
            $patch['submitted_at'] = now();
            $patch['review_feedback'] = null;
        }

        $model->update($patch);

        if (isset($validated['category']) || isset($validated['category_ids'])) {
            $this->syncCategories($model, $validated['category'] ?? null, $validated['category_ids'] ?? []);
        }

        return response()->json([
            'status' => 'success',
            'message' => $submit ? 'Submitted for review.' : 'Article updated.',
            'data' => $this->formatArticle($model->fresh('categories')),
        ]);
    }

    public function destroy(Request $request, int $article): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $model = NewsArticle::where('author_id', $userId)->find($article);
        if (!$model) {
            return response()->json(['status' => 'error', 'message' => 'Article not found'], 404);
        }

        if ($model->status === 'published') {
            return response()->json([
                'status' => 'error',
                'message' => 'Published articles cannot be deleted.',
            ], 422);
        }

        $model->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Article deleted.',
        ]);
    }

    protected function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'min:8', 'max:255'],
            'excerpt' => [$required, 'string', 'min:20', 'max:1000'],
            'content' => [$required, 'string', 'min:40'],
            'category' => [$partial ? 'sometimes' : 'required_without:category_ids', 'string', 'max:100'],
            'category_ids' => [$partial ? 'sometimes' : 'required_without:category', 'array'],
            'category_ids.*' => ['integer', Rule::exists('news_categories', 'id')],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'featured_image' => ['nullable', 'string', 'max:512'],
            'seo_meta_title' => ['nullable', 'string', 'max:255'],
            'seo_meta_description' => ['nullable', 'string', 'max:500'],
            'submit_for_review' => ['sometimes', 'boolean'],
        ]);
    }

    protected function syncCategories(NewsArticle $article, ?string $categoryName, array $categoryIds): void
    {
        $ids = $categoryIds;

        if ($categoryName) {
            $found = NewsCategory::query()
                ->where(function ($q) use ($categoryName) {
                    $q->whereRaw('LOWER(name) = ?', [strtolower($categoryName)])
                        ->orWhere('slug', Str::slug($categoryName));
                })
                ->value('id');

            if ($found) {
                $ids[] = (int) $found;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (!empty($ids)) {
            $article->categories()->sync($ids);
        }
    }

    protected function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'article-' . time();
        $slug = $base;
        $i = 1;

        while (
            NewsArticle::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    protected function formatArticle(NewsArticle $article): array
    {
        $categories = $article->relationLoaded('categories')
            ? $article->categories
            : $article->categories()->get();

        $primary = $categories->sortBy('display_order')->first();

        return [
            'id' => $article->id,
            'authorUserId' => (string) $article->author_id,
            'authorName' => $article->author_name,
            'pressId' => $article->press_id,
            'title' => $article->title,
            'slug' => $article->slug,
            'category' => $primary?->name ?? '',
            'categoryIds' => $categories->pluck('id')->values()->all(),
            'tags' => $article->tags ?? [],
            'excerpt' => $article->excerpt,
            'content' => $article->content,
            'featuredImage' => $article->featured_image,
            'seoMetaTitle' => $article->seo_meta_title,
            'seoMetaDescription' => $article->seo_meta_description,
            'status' => $article->status,
            'reviewFeedback' => $article->review_feedback,
            'views' => (int) $article->views,
            'submittedAt' => optional($article->submitted_at)?->toIso8601String(),
            'publishedAt' => optional($article->published_at)?->toIso8601String(),
            'createdAt' => optional($article->created_at)?->toIso8601String(),
            'updatedAt' => optional($article->updated_at)?->toIso8601String(),
        ];
    }

    protected function resolvePressId(string|int $userId): ?int
    {
        return NewsPressProfile::query()
            ->where('user_id', $userId)
            ->value('id');
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
