<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use Illuminate\Database\Seeder;

class BlogCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'technology',
            'lifestyle',
            'business',
            'health',
            'travel',
            'food',
            'entertainment',
            'sports',
            'education',
            'news',
        ];

        foreach ($categories as $key) {
            BlogCategory::query()->updateOrCreate(
                ['lang_key' => $key],
                ['lang_key' => $key]
            );
        }

        $this->command->info('Blog categories seeded successfully.');
    }
}

