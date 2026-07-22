<?php

namespace App\Http\Controllers\Api\V1\News;

use App\Models\NewsCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NewsCategoryController extends Controller
{
    /**
     * Get all news categories
     */
    public function index(): JsonResponse
    {
        $categories = NewsCategory::active()
            ->ordered()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    /**
     * Get a single category with its articles
     */
    public function show(string $identifier): JsonResponse
    {
        $category = NewsCategory::where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->first();

        if (!$category) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found',
            ], 404);
        }

        $articles = $category->publishedArticles()
            ->with('categories')
            ->latest()
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'category' => $category,
            'articles' => $articles->items(),
            'pagination' => [
                'total' => $articles->total(),
                'per_page' => $articles->perPage(),
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new category (Admin only)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:news_categories|max:255',
            'slug' => 'required|string|unique:news_categories|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'color' => 'nullable|string|size:7',
            'display_order' => 'integer',
            'status' => 'boolean',
        ]);

        $category = NewsCategory::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }

    /**
     * Update a category (Admin only)
     */
    public function update(Request $request, NewsCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|unique:news_categories,name,' . $category->id . '|max:255',
            'slug' => 'string|unique:news_categories,slug,' . $category->id . '|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'color' => 'nullable|string|size:7',
            'display_order' => 'integer',
            'status' => 'boolean',
        ]);

        $category->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Category updated successfully',
            'data' => $category,
        ]);
    }

    /**
     * Delete a category (Admin only)
     */
    public function destroy(NewsCategory $category): JsonResponse
    {
        $category->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Category deleted successfully',
        ]);
    }
}
