<?php

namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class SponsoredAdsHelper
{
    /**
     * Load sponsored ads for the admin repeater (JSON + legacy fallback).
     */
    public static function loadForForm(): array
    {
        $json = trim((string) Setting::get('sponsored_items', ''));
        if ($json !== '') {
            $items = json_decode($json, true);
            if (is_array($items) && $items !== []) {
                return array_values($items);
            }
        }

        $legacy = [];
        for ($i = 1; $i <= 2; $i++) {
            $name = trim((string) Setting::get("sponsored_{$i}_name", ''));
            $url = trim((string) Setting::get("sponsored_{$i}_url", ''));
            $imageUpload = trim((string) Setting::get("sponsored_{$i}_image_upload", ''));
            $imageUrl = trim((string) Setting::get("sponsored_{$i}_image", ''));

            if ($name === '' && $url === '' && $imageUpload === '' && $imageUrl === '') {
                continue;
            }

            $legacy[] = [
                'name' => $name,
                'url' => $url,
                'image_upload' => $imageUpload,
                'image_url' => $imageUrl,
            ];
        }

        return $legacy;
    }

    /**
     * Persist repeater rows as JSON in settings.
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

        Setting::set('sponsored_items', json_encode(array_values($normalized)));
    }

    /**
     * Sponsored ads for the public API / frontend slider.
     */
    public static function getForApi(): array
    {
        $json = trim((string) Setting::get('sponsored_items', ''));
        if ($json !== '') {
            $items = json_decode($json, true);
            if (is_array($items)) {
                return array_values(array_filter(array_map(
                    fn ($item) => self::formatApiItem(is_array($item) ? $item : []),
                    $items
                )));
            }
        }

        $legacy = [];
        for ($i = 1; $i <= 2; $i++) {
            $item = self::buildLegacyItem($i);
            if ($item !== []) {
                $legacy[] = $item;
            }
        }

        return $legacy;
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
            'name' => $name !== '' ? $name : 'Sponsored',
            'url' => $url !== '' ? $url : '#',
            'image_url' => $resolvedImage,
        ];
    }

    private static function buildLegacyItem(int $index): array
    {
        $name = trim((string) Setting::get("sponsored_{$index}_name", ''));
        $url = trim((string) Setting::get("sponsored_{$index}_url", ''));
        $imageUpload = trim((string) Setting::get("sponsored_{$index}_image_upload", ''));
        $image = trim((string) Setting::get("sponsored_{$index}_image", ''));
        $imageUrl = self::toPublicUrl($imageUpload !== '' ? $imageUpload : $image);

        if ($name === '' && $url === '' && $imageUrl === '') {
            return [];
        }

        return [
            'name' => $name !== '' ? $name : 'Sponsored',
            'url' => $url !== '' ? $url : '#',
            'image_url' => $imageUrl,
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
