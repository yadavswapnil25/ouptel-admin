<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupCategory;
use App\Models\GroupSubCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class GroupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = User::query()
            ->where('email', 'admin@ouptel.com')
            ->orWhere('admin', '1')
            ->first()
            ?: User::query()->first();

        if (!$owner) {
            $this->command->warn('No users found, skipping GroupsSeeder.');
            return;
        }

        $ownerId = (string) $owner->user_id;
        $now = time();

        $categories = GroupCategory::query()->get();
        if ($categories->isEmpty()) {
            $this->command->warn('No group categories found, run GroupCategoriesSeeder first.');
            return;
        }

        $subCategoriesByCategory = GroupSubCategory::query()
            ->get()
            ->groupBy('category_id');

        foreach ($categories as $category) {
            $baseLabel = $category->name;
            $subs = $subCategoriesByCategory->get($category->id) ?? collect();

            // Ensure at least 2 groups per category
            $groupsData = [
                [
                    'name' => "{$baseLabel} Community",
                    'title' => "{$baseLabel} Community Group",
                    'about' => "Discussion space for {$baseLabel} related topics.",
                ],
                [
                    'name' => "{$baseLabel} Experts",
                    'title' => "{$baseLabel} Experts Group",
                    'about' => "Advanced group for experts and professionals in {$baseLabel}.",
                ],
            ];

            foreach ($groupsData as $index => $data) {
                $subCategoryId = null;
                if ($subs->isNotEmpty()) {
                    $sub = $subs->values()->get($index % $subs->count());
                    $subCategoryId = $sub?->id;
                }

                // Build a safe slug for group_name (letters/numbers/underscore, max 50 chars)
                $baseSlug = strtolower($data['name']);
                $baseSlug = preg_replace('/[^a-z0-9_]+/i', '_', $baseSlug);
                $baseSlug = trim($baseSlug, '_');
                $baseSlug = substr($baseSlug, 0, 50);

                if ($baseSlug === '') {
                    $baseSlug = 'group_' . $category->id . '_' . ($index + 1);
                }

                Group::query()->updateOrCreate(
                    [
                        'group_name' => $baseSlug,
                        'user_id' => $ownerId,
                    ],
                    [
                        'group_title' => $data['title'],
                        'about' => $data['about'],
                        // GroupCategory uses lang_key pointing to Wo_Langs.id, but groups
                        // store numeric category IDs directly.
                        'category' => $category->id,
                        'sub_category' => $subCategoryId,
                        'privacy' => 'public',
                        'join_privacy' => 'public',
                        'active' => true,
                        'avatar' => 'images/placeholders/group-avatar.svg',
                        'cover' => 'images/placeholders/group-cover.svg',
                        'registered' => date('Y-m-d', $now),
                        'time' => $now - ($index * 600),
                    ]
                );
            }
        }

        $this->command->info('Sample groups seeded: at least 2 per category.');
    }
}

