<?php

namespace App\Http\Controllers\Api\V1\News;

use App\Models\NewsArticle;
use App\Models\NewsPressProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NewsPressController extends Controller
{
    /**
     * Public press profile by slug (branded mini-site payload).
     */
    public function show(string $slug): JsonResponse
    {
        $press = NewsPressProfile::query()
            ->with(['categories' => fn ($q) => $q->ordered()])
            ->where('slug', NewsPressProfile::normalizeSlug($slug))
            ->first();

        if (!$press) {
            return response()->json([
                'status' => 'error',
                'message' => 'Press page not found',
            ], 404);
        }

        if ($press->isSuspended()) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'unavailable' => true,
                    'message' => 'This page is temporarily unavailable',
                    'name' => $press->name,
                    'slug' => $press->slug,
                    'status' => $press->status,
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatPublicPress($press),
        ]);
    }

    /**
     * Published articles for a press page.
     */
    public function articles(Request $request, string $slug): JsonResponse
    {
        $press = NewsPressProfile::query()
            ->where('slug', NewsPressProfile::normalizeSlug($slug))
            ->first();

        if (!$press) {
            return response()->json([
                'status' => 'error',
                'message' => 'Press page not found',
            ], 404);
        }

        if ($press->isSuspended()) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'unavailable' => true,
                    'message' => 'This page is temporarily unavailable',
                    'articles' => [],
                    'pagination' => null,
                ],
            ]);
        }

        $query = NewsArticle::published()
            ->with('categories')
            ->where('press_id', $press->id)
            ->latest();

        if ($request->filled('category')) {
            $category = $request->input('category');
            $query->whereHas('categories', function ($q) use ($category) {
                $q->where('news_categories.id', $category)
                    ->orWhere('news_categories.slug', $category);
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $articles = $query->paginate(max(1, min($perPage, 50)));

        return response()->json([
            'status' => 'success',
            'data' => [
                'unavailable' => false,
                'press' => [
                    'id' => $press->id,
                    'name' => $press->name,
                    'slug' => $press->slug,
                    'logo' => $press->logo,
                    'publicPath' => $press->publicPath(),
                ],
                'articles' => collect($articles->items())->map(
                    fn (NewsArticle $article) => $this->formatPublicArticle($article, $press)
                )->values()->all(),
                'pagination' => [
                    'total' => $articles->total(),
                    'per_page' => $articles->perPage(),
                    'current_page' => $articles->currentPage(),
                    'last_page' => $articles->lastPage(),
                    'from' => $articles->firstItem(),
                    'to' => $articles->lastItem(),
                ],
            ],
        ]);
    }

    /**
     * Single published article under a press slug.
     */
    public function article(string $slug, string $articleSlug): JsonResponse
    {
        $press = NewsPressProfile::query()
            ->where('slug', NewsPressProfile::normalizeSlug($slug))
            ->first();

        if (!$press) {
            return response()->json([
                'status' => 'error',
                'message' => 'Press page not found',
            ], 404);
        }

        if ($press->isSuspended()) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'unavailable' => true,
                    'message' => 'This page is temporarily unavailable',
                ],
            ]);
        }

        $article = NewsArticle::published()
            ->with('categories')
            ->where('press_id', $press->id)
            ->where(function ($q) use ($articleSlug) {
                $q->where('slug', $articleSlug)->orWhere('id', $articleSlug);
            })
            ->first();

        if (!$article) {
            return response()->json([
                'status' => 'error',
                'message' => 'Article not found',
            ], 404);
        }

        $article->increment('views');

        $more = NewsArticle::published()
            ->with('categories')
            ->where('press_id', $press->id)
            ->where('id', '!=', $article->id)
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (NewsArticle $a) => $this->formatPublicArticle($a, $press));

        return response()->json([
            'status' => 'success',
            'data' => [
                'unavailable' => false,
                'press' => $this->formatPublicPress($press->loadMissing(['categories' => fn ($q) => $q->ordered()])),
                'article' => $this->formatPublicArticle($article->fresh('categories'), $press),
                'moreFromPress' => $more,
            ],
        ]);
    }

    protected function formatPublicPress(NewsPressProfile $press): array
    {
        $categories = $press->relationLoaded('categories')
            ? $press->categories
            : $press->categories()->ordered()->get();

        return [
            'id' => $press->id,
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
            'seo' => [
                'title' => $press->name,
                'description' => $press->tagline ?: ($press->name . ' on Ouptel News'),
            ],
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'color' => $c->color,
            ])->values()->all(),
            'articleCount' => $press->articles()->published()->count(),
        ];
    }

    protected function formatPublicArticle(NewsArticle $article, NewsPressProfile $press): array
    {
        $categories = $article->relationLoaded('categories')
            ? $article->categories
            : $article->categories()->get();

        $primary = $categories->sortBy('display_order')->first();

        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'content' => $article->content,
            'featuredImage' => $article->featured_image,
            'authorName' => $article->author_name,
            'pressId' => $press->id,
            'pressName' => $press->name,
            'pressSlug' => $press->slug,
            'pressLogo' => $press->logo,
            'pressPath' => $press->publicPath(),
            'articlePath' => $press->publicPath() . '/' . $article->slug,
            'category' => $primary?->name,
            'categorySlug' => $primary?->slug,
            'categoryColor' => $primary?->color,
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'color' => $c->color,
            ])->values()->all(),
            'views' => (int) $article->views,
            'publishedAt' => optional($article->published_at)?->toIso8601String(),
        ];
    }
}
