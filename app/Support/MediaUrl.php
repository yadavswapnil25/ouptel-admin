<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Turns a stored media path into a public URL, wherever the "public" disk
 * points (local storage/app/public or S3 - see MEDIA_DISK).
 *
 * Paths are stored relative (e.g. "posts/photos/x.jpg"); some legacy rows
 * hold "storage/..." or a full URL, and both are handled here.
 */
class MediaUrl
{
    public const DISK = 'public';

    /** Prefixes holding personal documents: stored private, served via signed URLs. */
    private const PRIVATE_PREFIXES = [
        'upload/verification/',
        'upload/resumes/',
        'upload/files/',
    ];

    public static function url(?string $path): ?string
    {
        $path = self::normalize($path);
        if ($path === null) {
            return null;
        }
        if (self::isAbsolute($path)) {
            return $path;
        }

        // Local disk: identical to the old asset('storage/...') URLs (host taken
        // from the request, not APP_URL), so MEDIA_DISK=local changes nothing.
        return self::onS3()
            ? Storage::disk(self::DISK)->url($path)
            : asset('storage/' . $path);
    }

    /**
     * Short-lived signed URL (S3) for private files; plain URL on local disk.
     */
    public static function temporary(?string $path, int $minutes = 30): ?string
    {
        $path = self::normalize($path);
        if ($path === null) {
            return null;
        }
        if (self::isAbsolute($path)) {
            return $path;
        }
        if (!self::onS3()) {
            return asset('storage/' . $path);
        }

        return Storage::disk(self::DISK)->temporaryUrl($path, now()->addMinutes($minutes));
    }

    /** Use for file paths that may be private (verification docs, resumes, exports). */
    public static function forPath(?string $path): ?string
    {
        $normalized = self::normalize($path);

        return $normalized !== null && !self::isAbsolute($normalized) && self::isPrivate($normalized)
            ? self::temporary($normalized)
            : self::url($normalized);
    }

    public static function isPrivate(string $path): bool
    {
        $path = ltrim($path, '/');
        foreach (self::PRIVATE_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** Visibility to store a new file with, based on its path. */
    public static function visibilityFor(string $path): string
    {
        return self::isPrivate($path) ? 'private' : 'public';
    }

    /**
     * storeAs()/put() options for a file going under $dir. Private only on S3:
     * on local disk "private" means 0600 permissions, which would stop the web
     * server serving /storage/... where it runs as a different user.
     */
    public static function storeOptions(string $dir): array
    {
        return [
            'disk' => self::DISK,
            'visibility' => self::onS3() ? self::visibilityFor(rtrim($dir, '/') . '/') : 'public',
        ];
    }

    public static function onS3(): bool
    {
        return config('filesystems.disks.' . self::DISK . '.driver') === 's3';
    }

    private static function normalize(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (self::isAbsolute($path)) {
            return $path;
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path === '' ? null : $path;
    }

    private static function isAbsolute(string $path): bool
    {
        return (bool) preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:');
    }
}
