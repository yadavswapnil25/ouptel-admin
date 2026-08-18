<?php

namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class SeasonalAdsHelper
{
    /**
     * Load seasonal ads for the admin repeater.
     */
    public static function loadForForm(): array
    {
        $json = trim((string) Setting::get('seasonal_ad_items', ''));
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
     * Persist seasonal ad repeater rows as JSON in settings.
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
            $startDate = self::normalizeDate($item['start_date'] ?? '');
            $endDate = self::normalizeDate($item['end_date'] ?? '');
            $enabled = self::toBool($item['enabled'] ?? true);
            $showOnProfile = self::toBool($item['show_on_profile'] ?? true);
            $showOnHome = self::toBool($item['show_on_home'] ?? true);

            if ($name === '' && $url === '' && $imageUpload === '' && $imageUrl === '') {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'url' => $url,
                'image_upload' => $imageUpload,
                'image_url' => $imageUrl,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'enabled' => $enabled,
                'show_on_profile' => $showOnProfile,
                'show_on_home' => $showOnHome,
            ];
        }

        Setting::set('seasonal_ad_items', json_encode(array_values($normalized)));
    }

    /**
     * Active seasonal ads for the public API.
     *
     * @param  string  $placement  profile|home
     */
    public static function getForApi(string $placement = 'profile'): array
    {
        if (!self::toBool(Setting::get('seasonal_ads_enabled', '1'))) {
            return [];
        }

        $json = trim((string) Setting::get('seasonal_ad_items', ''));
        if ($json === '') {
            return [];
        }

        $items = json_decode($json, true);
        if (!is_array($items)) {
            return [];
        }

        $placement = $placement === 'home' ? 'home' : 'profile';
        $today = now()->toDateString();

        return array_values(array_filter(array_map(
            function ($item) use ($placement, $today) {
                if (!is_array($item)) {
                    return [];
                }

                return self::formatApiItem($item, $placement, $today);
            },
            $items
        )));
    }

    /**
     * First matching seasonal ad, or empty array.
     */
    public static function firstForPlacement(string $placement = 'profile'): array
    {
        $items = self::getForApi($placement);

        return $items[0] ?? [];
    }

    private static function formatApiItem(array $item, string $placement, string $today): array
    {
        if (!self::toBool($item['enabled'] ?? true)) {
            return [];
        }

        if ($placement === 'profile' && !self::toBool($item['show_on_profile'] ?? true)) {
            return [];
        }

        if ($placement === 'home' && !self::toBool($item['show_on_home'] ?? true)) {
            return [];
        }

        $startDate = self::normalizeDate($item['start_date'] ?? '');
        $endDate = self::normalizeDate($item['end_date'] ?? '');
        if ($startDate !== '' && $today < $startDate) {
            return [];
        }
        if ($endDate !== '' && $today > $endDate) {
            return [];
        }

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

    public static function normalizeUploadPath(mixed $value): string
    {
        if (is_array($value)) {
            $first = reset($value);

            return is_string($first) ? trim($first) : '';
        }

        return is_string($value) ? trim($value) : '';
    }

    public static function toPublicUrl(string $pathOrUrl): string
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

    private static function normalizeDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return substr($value, 0, 10);
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
