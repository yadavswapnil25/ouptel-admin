<?php

namespace App\Helpers;

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
        $resolved = null;

        if ($imagePath) {
            $normalized = ltrim($imagePath, '/');

            // Direct public path (e.g. images/..., upload/...)
            if (file_exists(public_path($normalized))) {
                $resolved = asset($normalized);
            }
            // Storage symlink path (e.g. blog/... saved on public disk → public/storage/blog/...)
            elseif (file_exists(public_path('storage/' . $normalized))) {
                $resolved = asset('storage/' . $normalized);
            }
            // If already a full URL, just return it
            elseif (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                $resolved = $imagePath;
            }
        }

        return $resolved ?? self::getPlaceholder($type);
    }

    /**
     * Get cover image URL with fallback to placeholder
     */
    public static function getCoverUrl(?string $coverPath): string
    {
        if ($coverPath && file_exists(public_path($coverPath))) {
            return asset($coverPath);
        }

        return asset('images/placeholders/group-cover.svg');
    }
}
