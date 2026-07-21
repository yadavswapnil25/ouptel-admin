<?php

namespace App\Services;

use App\Models\ProfileInterestField;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ProfileInterestService
{
    /** @var array<string, array<int, string>> */
    private const LEGACY_ALIASES = [
        'favourite_tv_shows' => ['favorite_tv_shows', 'favourite_tv_shows', 'tv_shows'],
        'favourite_music_bands' => ['favorite_music_bands', 'favourite_music_bands', 'music_bands', 'music_artists'],
        'favourite_movies' => ['favorite_movies', 'favourite_movies', 'movies', 'films'],
        'favourite_books' => ['favorite_books', 'favourite_books', 'books'],
        'favourite_games' => ['favorite_games', 'favourite_games', 'games'],
    ];

    public static function tableReady(): bool
    {
        return Schema::hasTable('Wo_Profile_Interest_Fields');
    }

    public static function getActiveFields(): Collection
    {
        if (!self::tableReady()) {
            return collect(self::fallbackFieldDefinitions());
        }

        return ProfileInterestField::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public static function getAllFields(): Collection
    {
        if (!self::tableReady()) {
            return collect(self::fallbackFieldDefinitions());
        }

        return ProfileInterestField::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function buildProfileInterestsPayload(object $userRaw): array
    {
        $extra = self::decodeExtra($userRaw->profile_interests_extra ?? null);

        return self::getActiveFields()
            ->map(function ($field) use ($userRaw, $extra) {
                $fieldKey = (string) ($field->field_key ?? $field['field_key'] ?? '');
                $storageColumn = (string) ($field->storage_column ?? $field['storage_column'] ?? '');

                return [
                    'field_key' => $fieldKey,
                    'label' => (string) ($field->label ?? $field['label'] ?? ''),
                    'placeholder' => (string) ($field->placeholder ?? $field['placeholder'] ?? ''),
                    'sort_order' => (int) ($field->sort_order ?? $field['sort_order'] ?? 0),
                    'value' => self::readFieldValue($userRaw, $fieldKey, $storageColumn, $extra),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $userData
     */
    public static function applyLegacyInterestAliases(object $userRaw, array &$userData): void
    {
        $extra = self::decodeExtra($userRaw->profile_interests_extra ?? null);

        foreach (self::LEGACY_ALIASES as $column => $aliases) {
            $value = self::readFieldValue($userRaw, $column, $column, $extra);
            foreach ($aliases as $alias) {
                $userData[$alias] = $value;
            }
        }

        foreach ($extra as $fieldKey => $value) {
            if (!isset($userData[$fieldKey])) {
                $userData[$fieldKey] = $value;
            }
        }
    }

    /**
     * @param array<string, mixed> $userData
     * @return array<string, string>
     */
    public static function buildUpdatePayload(array $userData, ?object $userRaw = null): array
    {
        $fields = self::getActiveFields();
        $columnUpdates = [];
        $extraUpdates = self::decodeExtra($userRaw->profile_interests_extra ?? null);

        foreach ($fields as $field) {
            $fieldKey = (string) $field->field_key;
            $rawValue = self::resolveIncomingValue($fieldKey, $userData);
            if ($rawValue === null) {
                continue;
            }

            $value = self::normalizeValue($rawValue);
            $storageColumn = trim((string) ($field->storage_column ?? ''));

            if ($storageColumn !== '' && Schema::hasColumn('Wo_Users', $storageColumn)) {
                $columnUpdates[$storageColumn] = $value;
            } else {
                $extraUpdates[$fieldKey] = $value;
            }
        }

        if (Schema::hasColumn('Wo_Users', 'profile_interests_extra')) {
            $columnUpdates['profile_interests_extra'] = json_encode($extraUpdates);
        }

        return $columnUpdates;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fallbackFieldDefinitions(): array
    {
        return [
            [
                'field_key' => 'favourite_tv_shows',
                'label' => 'Favourite TV Shows',
                'placeholder' => 'e.g. Breaking Bad, Friends',
                'sort_order' => 1,
                'storage_column' => 'favourite_tv_shows',
            ],
            [
                'field_key' => 'favourite_music_bands',
                'label' => 'Favourite Music Bands / Artists',
                'placeholder' => 'e.g. The Beatles, AR Rahman',
                'sort_order' => 2,
                'storage_column' => 'favourite_music_bands',
            ],
            [
                'field_key' => 'favourite_movies',
                'label' => 'Favourite Movies',
                'placeholder' => 'e.g. Inception, 3 Idiots',
                'sort_order' => 3,
                'storage_column' => 'favourite_movies',
            ],
            [
                'field_key' => 'favourite_books',
                'label' => 'Favourite Books',
                'placeholder' => 'e.g. Atomic Habits, Harry Potter',
                'sort_order' => 4,
                'storage_column' => 'favourite_books',
            ],
            [
                'field_key' => 'favourite_games',
                'label' => 'Favourite Games',
                'placeholder' => 'e.g. FIFA, Minecraft',
                'sort_order' => 5,
                'storage_column' => 'favourite_games',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function decodeExtra(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_map(static fn ($value) => trim((string) $value), $raw);
        }

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $key => $value) {
            $normalized[(string) $key] = trim((string) $value);
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $extra
     */
    private static function readFieldValue(object $userRaw, string $fieldKey, string $storageColumn, array $extra): string
    {
        if ($storageColumn !== '' && Schema::hasColumn('Wo_Users', $storageColumn)) {
            return trim((string) ($userRaw->{$storageColumn} ?? ''));
        }

        return trim((string) ($extra[$fieldKey] ?? ''));
    }

    /**
     * @param array<string, mixed> $userData
     */
    private static function resolveIncomingValue(string $fieldKey, array $userData): ?string
    {
        if (array_key_exists($fieldKey, $userData)) {
            return (string) $userData[$fieldKey];
        }

        $aliases = self::LEGACY_ALIASES[$fieldKey] ?? [];
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $userData)) {
                return (string) $userData[$alias];
            }
        }

        return null;
    }

    private static function normalizeValue(string $value): string
    {
        $value = trim($value);
        if (mb_strlen($value) > 500) {
            return mb_substr($value, 0, 497) . '...';
        }

        return $value;
    }
}
