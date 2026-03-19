<?php

namespace Database\Seeders;

use App\Models\PageCategory;
use App\Models\PageSubCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PageCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define base page categories and their sub categories
        $categories = [
            'business' => [
                'label' => 'Business',
                'subs' => [
                    'startup' => 'Startup',
                    'finance' => 'Finance',
                    'marketing' => 'Marketing',
                    'local' => 'Local Business',
                    'international' => 'International Business',
                ],
            ],
            'entertainment' => [
                'label' => 'Entertainment',
                'subs' => [
                    'movies' => 'Movies',
                    'music' => 'Music',
                    'games' => 'Games',
                ],
            ],
            'sports' => [
                'label' => 'Sports',
                'subs' => [
                    'football' => 'Football',
                    'basketball' => 'Basketball',
                    'tennis' => 'Tennis',
                ],
            ],
            'technology' => [
                'label' => 'Technology',
                'subs' => [
                    'mobile' => 'Mobile',
                    'software' => 'Software',
                    'hardware' => 'Hardware',
                ],
            ],
            'education' => [
                'label' => 'Education',
                'subs' => [
                    'school' => 'School',
                    'university' => 'University',
                    'online' => 'Online Learning',
                ],
            ],
            'health' => [
                'label' => 'Health & Fitness',
                'subs' => [
                    'fitness' => 'Fitness',
                    'medical' => 'Medical',
                    'wellness' => 'Wellness',
                ],
            ],
            'travel' => [
                'label' => 'Travel & Places',
                'subs' => [
                    'destinations' => 'Destinations',
                    'hotels' => 'Hotels',
                    'restaurants' => 'Restaurants',
                ],
            ],
            'lifestyle' => [
                'label' => 'Lifestyle',
                'subs' => [
                    'fashion' => 'Fashion',
                    'beauty' => 'Beauty',
                    'recipes' => 'Recipes',
                ],
            ],
        ];

        foreach ($categories as $langKey => $config) {
            // 1) Ensure lang key exists in Wo_Langs for this category
            $langId = $this->upsertLangKey($langKey, $config['label'], 'category');

            // 2) Upsert category row
            /** @var PageCategory $category */
            $category = PageCategory::query()->updateOrCreate(
                ['lang_key' => $langId],
                ['lang_key' => $langId]
            );

            // 3) Create sub categories
            foreach ($config['subs'] as $subKey => $subLabel) {
                // Ensure lang key exists for this subcategory
                $this->upsertLangKey($subKey, $subLabel, 'category');

                PageSubCategory::query()->updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'lang_key' => $subKey,
                        'type' => 'page',
                    ],
                    [
                        'category_id' => $category->id,
                        'lang_key' => $subKey,
                        'type' => 'page',
                    ]
                );
            }
        }

        $this->command->info('Page categories and sub categories seeded successfully.');
    }

    /**
     * Create or update a Wo_Langs entry and return its ID.
     */
    private function upsertLangKey(string $langKey, string $englishLabel, string $type): int
    {
        if (!DB::getSchemaBuilder()->hasTable('Wo_Langs')) {
            // If Wo_Langs table does not exist, just return a pseudo ID
            return crc32($langKey) & 0xfffffff;
        }

        $existing = DB::table('Wo_Langs')
            ->where('lang_key', $langKey)
            ->where('type', $type)
            ->first();

        if ($existing) {
            // Update english label if changed
            DB::table('Wo_Langs')
                ->where('id', $existing->id)
                ->update(['english' => $englishLabel]);

            return (int) $existing->id;
        }

        return (int) DB::table('Wo_Langs')->insertGetId([
            'lang_key' => $langKey,
            'english' => $englishLabel,
            'type' => $type,
        ]);
    }
}

