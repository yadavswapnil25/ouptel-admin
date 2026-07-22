<?php

namespace App\Http\Controllers\Api\V1\News;

use App\Models\NewsArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NewsArticleController extends Controller
{
    /**
     * Get all articles with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = NewsArticle::published()
            ->with('categories')
            ->latest();

        if ($request->filled('category')) {
            $query->byCategory($request->input('category'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        if ($request->boolean('breaking')) {
            $query->breaking();
        }

        $perPage = $request->input('per_page', 15);
        $articles = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $articles->items(),
            'pagination' => [
                'total' => $articles->total(),
                'per_page' => $articles->perPage(),
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'from' => $articles->firstItem(),
                'to' => $articles->lastItem(),
            ],
        ]);
    }

    /**
     * Get a single article by ID or slug
     */
    public function show(string $identifier): JsonResponse
    {
        $article = NewsArticle::published()
            ->with('categories')
            ->where(function ($q) use ($identifier) {
                $q->where('id', $identifier)->orWhere('slug', $identifier);
            })
            ->first();

        if (!$article) {
            return response()->json([
                'status' => 'error',
                'message' => 'Article not found',
            ], 404);
        }

        $article->increment('views');

        $categoryIds = $article->categories->pluck('id');

        $related = NewsArticle::published()
            ->with('categories')
            ->where('id', '!=', $article->id)
            ->when($categoryIds->isNotEmpty(), function ($q) use ($categoryIds) {
                $q->whereHas('categories', function ($cq) use ($categoryIds) {
                    $cq->whereIn('news_categories.id', $categoryIds);
                });
            })
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $article,
            'related' => $related,
        ]);
    }

    public function trending(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);

        $trending = NewsArticle::published()
            ->recentTwentyFourHours()
            ->trending()
            ->with('categories')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $trending,
        ]);
    }

    public function featured(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 5);

        $featured = NewsArticle::featured()
            ->with('categories')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $featured,
        ]);
    }

    public function breaking(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 5);

        $breaking = NewsArticle::breaking()
            ->with('categories')
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $breaking,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:news_articles|max:255',
            'excerpt' => 'required|string',
            'content' => 'required|string',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'integer|exists:news_categories,id',
            'featured_image' => 'nullable|url',
            'author_name' => 'nullable|string|max:255',
            'featured' => 'boolean',
            'breaking' => 'boolean',
            'published_at' => 'nullable|date_format:Y-m-d H:i:s',
            'status' => 'required|in:draft,published,archived',
        ]);

        $categoryIds = $validated['category_ids'];
        unset($validated['category_ids']);

        $validated['author_id'] = auth()->id();

        $article = NewsArticle::create($validated);
        $article->categories()->sync($categoryIds);
        $article->load('categories');

        return response()->json([
            'status' => 'success',
            'message' => 'Article created successfully',
            'data' => $article,
        ], 201);
    }

    public function update(Request $request, NewsArticle $article): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'string|max:255',
            'slug' => 'string|unique:news_articles,slug,' . $article->id . '|max:255',
            'excerpt' => 'string',
            'content' => 'string',
            'category_ids' => 'sometimes|array|min:1',
            'category_ids.*' => 'integer|exists:news_categories,id',
            'featured_image' => 'nullable|url',
            'author_name' => 'nullable|string|max:255',
            'featured' => 'boolean',
            'breaking' => 'boolean',
            'published_at' => 'nullable|date_format:Y-m-d H:i:s',
            'status' => 'in:draft,published,archived',
        ]);

        $categoryIds = $validated['category_ids'] ?? null;
        unset($validated['category_ids']);

        $article->update($validated);

        if (is_array($categoryIds)) {
            $article->categories()->sync($categoryIds);
        }

        $article->load('categories');

        return response()->json([
            'status' => 'success',
            'message' => 'Article updated successfully',
            'data' => $article,
        ]);
    }

    public function destroy(NewsArticle $article): JsonResponse
    {
        $article->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Article deleted successfully',
        ]);
    }

    public function share(NewsArticle $article): JsonResponse
    {
        $article->increment('shares');

        return response()->json([
            'status' => 'success',
            'message' => 'Share count incremented',
        ]);
    }
}
