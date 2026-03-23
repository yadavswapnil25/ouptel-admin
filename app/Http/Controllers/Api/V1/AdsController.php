<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdsController extends Controller
{
    /**
     * Return left sidebar ad config for web clients.
     */
    public function sidebar(): JsonResponse
    {
        try {
            $config = DB::table('Wo_Config')->pluck('value', 'name');
            // Filament settings UI writes to Setting model keys.
            $settings = [
                'sidebar_ad_image_upload' => Setting::get('sidebar_ad_image_upload', ''),
                'sidebar_ad_image' => Setting::get('sidebar_ad_image', ''),
                'sidebar_ad_url' => Setting::get('sidebar_ad_url', ''),
            ];

            $imageUrl = $this->firstNonEmpty($config, [
                'sidebar_ad_image',
                'left_sidebar_ad_image',
                'left_sidebar_banner',
                'ads_left_sidebar',
                'ad_image',
                'ads_image',
                'sidebar_ad',
            ]);
            if ($imageUrl === '' && !empty($settings['sidebar_ad_image_upload'])) {
                $imageUrl = $settings['sidebar_ad_image_upload'];
            }
            if ($imageUrl === '' && !empty($settings['sidebar_ad_image'])) {
                $imageUrl = $settings['sidebar_ad_image'];
            }
            $imageUrl = $this->toPublicUrl($imageUrl);

            $linkUrl = $this->firstNonEmpty($config, [
                'sidebar_ad_url',
                'left_sidebar_ad_url',
                'left_sidebar_banner_link',
                'ad_url',
                'ads_url',
                'sidebar_ad_link',
            ], '#');
            if (($linkUrl === '#' || $linkUrl === '') && !empty($settings['sidebar_ad_url'])) {
                $linkUrl = $settings['sidebar_ad_url'];
            }

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'data' => [
                    'placement' => 'left_sidebar',
                    'image_url' => $imageUrl,
                    'link_url' => $linkUrl,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => 'ads_500',
                    'error_text' => 'Failed to load sidebar ad.',
                ],
            ], 500);
        }
    }

    /**
     * @param \Illuminate\Support\Collection|array $config
     */
    private function firstNonEmpty($config, array $keys, string $fallback = ''): string
    {
        foreach ($keys as $key) {
            $value = data_get($config, $key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return $fallback;
    }

    private function toPublicUrl(string $pathOrUrl): string
    {
        $value = trim($pathOrUrl);
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }
}

