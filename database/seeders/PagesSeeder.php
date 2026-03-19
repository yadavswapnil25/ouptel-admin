<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PageCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class PagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Pick an owner for seeded pages
        $owner = User::query()
            ->where('email', 'admin@ouptel.com')
            ->orWhere('admin', '1')
            ->first()
            ?: User::query()->first();

        if (!$owner) {
            $this->command->warn('No users found, skipping PagesSeeder.');
            return;
        }

        $ownerId = (string) $owner->user_id;

        // Map a few existing categories by label so we attach valid category IDs
        $categoriesByLabel = PageCategory::query()
            ->get()
            ->keyBy(fn (PageCategory $cat) => strtolower($cat->name));

        $getCategoryId = function (string $label) use ($categoriesByLabel): int {
            $key = strtolower($label);
            $cat = $categoriesByLabel->get($key) ?: $categoriesByLabel->first();
            return $cat?->id ?? 1;
        };

        $pages = [
            [
                'page_name' => 'ouptel_official',
                'page_title' => 'Ouptel Official',
                'page_description' => 'Official Ouptel announcements, feature updates, and product news.',
                'category_label' => 'Business',
                'website' => 'https://ouptel.in',
            ],
            [
                'page_name' => 'upsc_2026_aspirants',
                'page_title' => 'UPSC 2026 Aspirants',
                'page_description' => 'Community page for UPSC 2026 aspirants to discuss strategy, books, and preparation tips.',
                'category_label' => 'Education',
                'website' => 'https://example.com/upsc-2026',
            ],
            [
                'page_name' => 'ouptel_tech_insights',
                'page_title' => 'Ouptel Tech Insights',
                'page_description' => 'Technology, engineering, and product deep-dives from the Ouptel team.',
                'category_label' => 'Technology',
                'website' => 'https://example.com/ouptel-tech',
            ],
            [
                'page_name' => 'fitness_and_wellness_hub',
                'page_title' => 'Fitness & Wellness Hub',
                'page_description' => 'Tips, routines, and discussions around fitness, nutrition, and wellness.',
                'category_label' => 'Health & Fitness',
                'website' => 'https://example.com/fitness-hub',
            ],
        ];

        foreach ($pages as $data) {
            $categoryId = $getCategoryId($data['category_label']);

            Page::query()->updateOrCreate(
                ['page_name' => $data['page_name']],
                [
                    'page_title' => $data['page_title'],
                    'page_description' => $data['page_description'],
                    'page_category' => $categoryId,
                    'user_id' => $ownerId,
                    'verified' => false,
                    'active' => true,
                    'website' => $data['website'],
                    'phone' => '',
                    'address' => '',
                    // Use placeholder avatar/cover paths so admin panel can render them
                    'avatar' => 'images/placeholders/page-avatar.svg',
                    'cover' => 'images/placeholders/group-cover.svg',
                ]
            );
        }

        $this->command->info('Sample pages seeded successfully.');
    }
}

