<?php

namespace App\Http\Controllers\Api\V1\News;

use App\Models\NewsAd;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NewsAdController extends Controller
{
    /**
     * List active news ads (public). Optional ?placement=sidebar
     */
    public function index(Request $request): JsonResponse
    {
        $query = NewsAd::query()
            ->active()
            ->currentlyRunning()
            ->ordered();

        if ($request->filled('placement')) {
            $query->forPlacement($request->query('placement'));
        }

        $ads = $query->get()->map(fn (NewsAd $ad) => $this->formatAd($ad));

        return response()->json([
            'status' => 'success',
            'data' => $ads,
        ]);
    }

    /**
     * Track a click and return the destination URL.
     */
    public function click(int $ad): JsonResponse
    {
        $newsAd = NewsAd::query()
            ->active()
            ->currentlyRunning()
            ->find($ad);

        if (!$newsAd) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ad not found',
            ], 404);
        }

        $newsAd->increment('clicks');

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $newsAd->id,
                'link_url' => $newsAd->link_url,
                'clicks' => $newsAd->clicks,
            ],
        ]);
    }

    protected function formatAd(NewsAd $ad): array
    {
        return [
            'id' => $ad->id,
            'title' => $ad->title,
            'headline' => $ad->headline,
            'image' => $ad->image_url,
            'link_url' => $ad->link_url,
            'placement' => $ad->placement,
            'display_order' => $ad->display_order,
        ];
    }
}
