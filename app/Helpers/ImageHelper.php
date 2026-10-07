<?php

namespace App\Helpers;

use App\Support\MediaUrl;

class ImageHelper
{
    /**
     * Get placeholder image URL for different types
     */
    public static function getPlaceholder(string $type = 'default'): string
    {
        $placeholders = [
            'user' => 'images/placeholders/user-avatar.svg',
            'group' => 'images/placeholders/group-avatar.svg',
            'page' => 'images/placeholders/page-avatar.svg',
            'post' => 'images/placeholders/post-avatar.svg',
            'funding' => 'images/placeholders/funding-avatar.svg',
            'job' => 'images/placeholders/job-avatar.svg',
            'blog' => 'images/placeholders/blog-image.svg',
            'event' => 'images/placeholders/event-cover.svg',
            'product' => 'images/placeholders/product-image.svg',
            'game' => 'images/placeholders/game-avatar.svg',
            'default' => 'images/placeholders/default-avatar.svg',
        ];

        return asset($placeholders[$type] ?? $placeholders['default']);
    }

    /**
     * Get image URL with fallback to placeholder
     */
    public static function getImageUrl(?string $imagePath, string $type = 'default'): string
    {
        return self::resolve($imagePath) ?? self::getPlaceholder($type);
    }

    /**
     * Get cover image URL with fallback to placeholder
     */
    public static function getCoverUrl(?string $coverPath): string
    {
        return self::resolve($coverPath) ?? asset('images/placeholders/group-cover.svg');
    }

    /**
     * Stored path -> URL. On local disk, missing files fall back to the
     * placeholder (as before). On S3 there is no existence check: that would
     * be a network round-trip per image, and migrated files keep their paths.
     */
    private static function resolve(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $normalized = ltrim($path, '/');

        if (!MediaUrl::onS3()) {
            // Legacy files served straight from public/ (e.g. public/upload/...).
            if (file_exists(public_path($normalized))) {
                return asset($normalized);
            }
            $storagePath = str_starts_with($normalized, 'storage/') ? substr($normalized, 8) : $normalized;
            return file_exists(public_path('storage/' . $storagePath)) ? MediaUrl::url($storagePath) : null;
        }

        return MediaUrl::url($normalized);
    }
}
