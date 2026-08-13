<?php

namespace Database\Seeders;

use App\Models\CommunityPreference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CommunityPreferencesSeeder extends Seeder
{
    public function run(): void
    {
        $preferences = [
            ['name' => 'Technology', 'description' => 'Gadgets, software, and digital culture', 'sort_order' => 10],
            ['name' => 'Business', 'description' => 'Startups, careers, and entrepreneurship', 'sort_order' => 20],
            ['name' => 'Education', 'description' => 'Learning, campuses, and skills', 'sort_order' => 30],
            ['name' => 'Sports', 'description' => 'Games, fitness, and athletics', 'sort_order' => 40],
            ['name' => 'Entertainment', 'description' => 'Movies, TV, and pop culture', 'sort_order' => 50],
            ['name' => 'Music', 'description' => 'Artists, playlists, and live shows', 'sort_order' => 60],
            ['name' => 'Travel', 'description' => 'Places, trips, and local discoveries', 'sort_order' => 70],
            ['name' => 'Health & Fitness', 'description' => 'Wellness, workouts, and lifestyle', 'sort_order' => 80],
            ['name' => 'Food & Dining', 'description' => 'Recipes, restaurants, and cooking', 'sort_order' => 90],
            ['name' => 'Art & Culture', 'description' => 'Design, heritage, and creative work', 'sort_order' => 100],
            ['name' => 'News & Politics', 'description' => 'Current affairs and civic life', 'sort_order' => 110],
            ['name' => 'Fashion', 'description' => 'Style, trends, and personal look', 'sort_order' => 120],
        ];

        foreach ($preferences as $preference) {
            CommunityPreference::query()->updateOrCreate(
                ['slug' => Str::slug($preference['name'])],
                [
                    'name' => $preference['name'],
                    'description' => $preference['description'],
                    'sort_order' => $preference['sort_order'],
                ],
            );
        }

        Cache::store('file')->forget(CommunityPreference::PUBLIC_LIST_CACHE_KEY);
    }
}
