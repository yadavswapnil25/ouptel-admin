<?php

namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class BlogAdsHelper
{
    /**
     * Load blog ads for the admin repeater.
     */
    public static function loadForForm(): array
    {
        $json = trim((string) Setting::get('blog_ad_items', ''));
        if ($json === '') {
            return [];
        }

        $items = json_decode($json, true);
        if (!is_array($items) || $items === []) {
            return [];
        }

        return array_values($items);
    }

    /**
     * Persist blog ad repeater rows as JSON in settings.
     */
    public static function saveFromForm(array $items): void
    {
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));
            $imageUpload = self::normalizeUploadPath($item['image_upload'] ?? '');
            $imageUrl = trim((string) ($item['image_url'] ?? ''));

            if ($name === '' && $url === '' && $imageUpload === '' && $imageUrl === '') {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'url' => $url,
                'image_upload' => $imageUpload,
                'image_url' => $imageUrl,
            ];
        }

        Setting::set('blog_ad_items', json_encode(array_values($normalized)));
    }

    /**
     * Blog ads for the public API / frontend sidebar.
     */
    public static function getForApi(): array
    {
        $json = trim((string) Setting::get('blog_ad_items', ''));
        if ($json === '') {
            return [];
        }

        $items = json_decode($json, true);
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => self::formatApiItem(is_array($item) ? $item : []),
            $items
        )));
    }

    private static function formatApiItem(array $item): array
    {
        $name = trim((string) ($item['name'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));
        $imageUpload = self::normalizeUploadPath($item['image_upload'] ?? '');
        $imageUrl = trim((string) ($item['image_url'] ?? ''));
        $resolvedImage = self::toPublicUrl($imageUpload !== '' ? $imageUpload : $imageUrl);

        if ($name === '' && $url === '' && $resolvedImage === '') {
            return [];
        }

        return [
            'name' => $name !== '' ? $name : 'Advertisement',
            'url' => $url !== '' ? $url : '#',
            'image_url' => $resolvedImage,
        ];
    }

    private static function normalizeUploadPath(mixed $value): string
    {
        if (is_array($value)) {
            $first = reset($value);

            return is_string($first) ? trim($first) : '';
        }

        return is_string($value) ? trim($value) : '';
    }

    private static function toPublicUrl(string $pathOrUrl): string
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
