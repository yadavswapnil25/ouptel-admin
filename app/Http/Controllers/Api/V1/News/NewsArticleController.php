<?php

namespace App\Http\Controllers\Api\V1\News;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
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
            ->with('category')
            ->latest();

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->input('category'));
        }

        // Filter by search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%");
        }

        // Filter by featured
        if ($request->boolean('featured')) {
            $query->featured();
        }

        // Filter by breaking news
        if ($request->boolean('breaking')) {
            $query->breaking();
        }

        // Pagination
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
        // Try to find by ID first, then by slug
        $article = NewsArticle::published()
            ->where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->with('category')
            ->first();

        if (!$article) {
            return response()->json([
                'status' => 'error',
                'message' => 'Article not found',
            ], 404);
        }

        // Increment views count
        $article->increment('views');

        // Get related articles
        $related = NewsArticle::published()
            ->where('category_id', $article->category_id)
            ->where('id', '!=', $article->id)
            ->with('category')
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $article,
            'related' => $related,
        ]);
    }

    /**
     * Get trending articles (most viewed in last 24 hours)
     */
    public function trending(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);

        $trending = NewsArticle::published()
            ->recentTwentyFourHours()
            ->trending()
            ->with('category')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $trending,
        ]);
    }

    /**
     * Get featured articles
     */
    public function featured(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 5);

        $featured = NewsArticle::featured()
            ->with('category')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $featured,
        ]);
    }

    /**
     * Get breaking news
     */
    public function breaking(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 5);

        $breaking = NewsArticle::breaking()
            ->with('category')
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $breaking,
        ]);
    }

    /**
     * Create a new article (Admin only)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:news_articles|max:255',
            'excerpt' => 'required|string',
            'content' => 'required|string',
            'category_id' => 'required|exists:news_categories,id',
            'featured_image' => 'nullable|url',
            'author_name' => 'nullable|string|max:255',
            'featured' => 'boolean',
            'breaking' => 'boolean',
            'published_at' => 'nullable|date_format:Y-m-d H:i:s',
            'status' => 'required|in:draft,published,archived',
        ]);

        $validated['author_id'] = auth()->id();

        $article = NewsArticle::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Article created successfully',
            'data' => $article,
        ], 201);
    }

    /**
     * Update an article (Admin only)
     */
    public function update(Request $request, NewsArticle $article): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'string|max:255',
            'slug' => 'string|unique:news_articles,slug,' . $article->id . '|max:255',
            'excerpt' => 'string',
            'content' => 'string',
            'category_id' => 'exists:news_categories,id',
            'featured_image' => 'nullable|url',
            'author_name' => 'nullable|string|max:255',
            'featured' => 'boolean',
            'breaking' => 'boolean',
            'published_at' => 'nullable|date_format:Y-m-d H:i:s',
            'status' => 'in:draft,published,archived',
        ]);

        $article->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Article updated successfully',
            'data' => $article,
        ]);
    }

    /**
     * Delete an article (Admin only)
     */
    public function destroy(NewsArticle $article): JsonResponse
    {
        $article->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Article deleted successfully',
        ]);
    }

    /**
     * Increment share count
     */
    public function share(NewsArticle $article): JsonResponse
    {
        $article->increment('shares');

        return response()->json([
            'status' => 'success',
            'message' => 'Share count incremented',
        ]);
    }
}
