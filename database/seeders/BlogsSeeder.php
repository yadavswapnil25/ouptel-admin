<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\BlogCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlogsSeeder extends Seeder
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
            $this->command->warn('No users found, skipping BlogsSeeder.');
            return;
        }

        $ownerId = (string) $owner->user_id;

        $categories = BlogCategory::query()->get();
        if ($categories->isEmpty()) {
            $this->command->warn('No blog categories found, run BlogCategoriesSeeder first.');
            return;
        }

        $now = time();

        foreach ($categories as $category) {
            $baseKey = $category->lang_key;
            $readable = $category->name;

            $posts = [
                [
                    'title' => "{$readable} Insights 2026",
                    'description' => "Latest updates, trends, and insights in {$readable} for the year 2026.",
                    'content' => "<p>This is a sample article for the <strong>{$readable}</strong> category. Replace this with real content.</p>",
                    'tags' => [$baseKey, 'ouptel', 'insights'],
                ],
                [
                    'title' => "Getting Started with {$readable}",
                    'description' => "Beginner friendly introduction to {$readable} for new community members.",
                    'content' => "<p>Another sample article focused on helping new readers understand the basics of <strong>{$readable}</strong>.</p>",
                    'tags' => [$baseKey, 'guide', 'getting-started'],
                ],
            ];

            foreach ($posts as $index => $data) {
                Article::query()->updateOrCreate(
                    [
                        'user' => $ownerId,
                        'title' => $data['title'],
                        'category' => $category->id,
                    ],
                    [
                        'description' => $data['description'],
                        'content' => $data['content'],
                        'category' => $category->id,
                        'thumbnail' => 'images/placeholders/blog-image.svg',
                        'posted' => $now - ($index * 3600),
                        'active' => true,
                        'view' => 0,
                        'shared' => 0,
                        'tags' => $data['tags'],
                    ]
                );
            }
        }

        $this->command->info('Sample blogs seeded: at least 2 per category.');
    }
}

