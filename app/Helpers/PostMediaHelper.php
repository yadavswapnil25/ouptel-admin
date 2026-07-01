<?php

namespace App\Helpers;

class PostMediaHelper
{
    /**
     * Only expose embeddable YouTube/Vimeo URLs for non-photo posts.
     * Photo posts may have stale junk in postYoutube (e.g. website URLs).
     */
    public static function sanitizeYoutubeForResponse(object $post, string $postType): string
    {
        $value = trim((string) ($post->postYoutube ?? ''));
        if ($value === '') {
            return '';
        }

        if (in_array($postType, ['photo', 'gif', 'image'], true)) {
            return '';
        }

        if (!preg_match('/youtube\.com|youtu\.be|vimeo\.com/i', $value)) {
            return '';
        }

        return $value;
    }
}
