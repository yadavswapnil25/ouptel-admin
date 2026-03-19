<?php

namespace Database\Seeders;

use App\Models\GroupCategory;
use App\Models\GroupSubCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define base group categories and their sub categories
        $categories = [
            'business_groups' => [
                'label' => 'Business Groups',
                'subs' => [
                    'startup' => 'Startup',
                    'finance' => 'Finance',
                    'marketing' => 'Marketing',
                    'local' => 'Local Business',
                    'international' => 'International Business',
                ],
            ],
            'entertainment_groups' => [
                'label' => 'Entertainment Groups',
                'subs' => [
                    'movies' => 'Movies',
                    'music' => 'Music',
                    'games' => 'Games',
                ],
            ],
            'sports_groups' => [
                'label' => 'Sports Groups',
                'subs' => [
                    'football' => 'Football',
                    'basketball' => 'Basketball',
                    'tennis' => 'Tennis',
                ],
            ],
            'tech_groups' => [
                'label' => 'Technology Groups',
                'subs' => [
                    'mobile' => 'Mobile',
                    'software' => 'Software',
                    'hardware' => 'Hardware',
                ],
            ],
            'education_groups' => [
                'label' => 'Education Groups',
                'subs' => [
                    'school' => 'School',
                    'university' => 'University',
                    'online' => 'Online Learning',
                ],
            ],
            'health_groups' => [
                'label' => 'Health & Fitness Groups',
                'subs' => [
                    'fitness' => 'Fitness',
                    'medical' => 'Medical',
                    'wellness' => 'Wellness',
                ],
            ],
            'travel_groups' => [
                'label' => 'Travel Groups',
                'subs' => [
                    'destinations' => 'Destinations',
                    'hotels' => 'Hotels',
                    'restaurants' => 'Restaurants',
                ],
            ],
            'lifestyle_groups' => [
                'label' => 'Lifestyle Groups',
                'subs' => [
                    'fashion' => 'Fashion',
                    'beauty' => 'Beauty',
                    'recipes' => 'Recipes',
                ],
            ],
        ];

        foreach ($categories as $langKey => $config) {
            // 1) Ensure lang key exists in Wo_Langs for this group category
            $langId = $this->upsertLangKey($langKey, $config['label'], 'category');

            // 2) Upsert group category row
            /** @var GroupCategory $category */
            $category = GroupCategory::query()->updateOrCreate(
                ['lang_key' => $langId],
                ['lang_key' => $langId]
            );

            // 3) Create group sub categories
            foreach ($config['subs'] as $subKey => $subLabel) {
                // Ensure lang key exists for this subcategory
                $this->upsertLangKey($subKey, $subLabel, 'category');

                GroupSubCategory::query()->updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'lang_key' => $subKey,
                        'type' => 'group',
                    ],
                    [
                        'category_id' => $category->id,
                        'lang_key' => $subKey,
                        'type' => 'group',
                    ]
                );
            }
        }

        $this->command->info('Group categories and sub categories seeded successfully.');
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

