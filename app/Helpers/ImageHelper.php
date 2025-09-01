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
        if ($imagePath && file_exists(public_path($imagePath))) {
            return asset($imagePath);
        }

        return self::getPlaceholder($type);
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
