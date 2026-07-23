<?php

namespace Database\Seeders;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsEditor;
use App\Models\NewsPressProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class NewsPressSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('news_press_profiles')) {
            $this->command?->warn('news_press_profiles table missing — run migrations first.');
            return;
        }

        $editor = NewsEditor::query()->where('status', 'active')->first();

        if (!$editor) {
            $user = User::query()->orderBy('user_id')->first();
            if (!$user) {
                $this->command?->warn('No users found — skip NewsPressSeeder.');
                return;
            }

            $editor = NewsEditor::query()->updateOrCreate(
                ['user_id' => $user->user_id],
                [
                    'preferred_categories' => ['India', 'Politics'],
                    'status' => 'active',
                    'approved_at' => now(),
                ]
            );
        }

        $press = NewsPressProfile::query()->updateOrCreate(
            ['editor_id' => $editor->id],
            [
                'user_id' => $editor->user_id,
                'name' => 'Janta Press',
                'slug' => 'janta-press',
                'logo' => 'https://placehold.co/160x160/1e3a5f/ffffff?text=JP',
                'banner_image' => 'https://placehold.co/1200x280/0f172a/ffffff?text=Janta+Press',
                'tagline' => 'Local news for every neighbourhood',
                'contact_email' => 'desk@jantapress.example',
                'social_links' => [
                    'twitter' => 'https://twitter.com/',
                    'facebook' => 'https://facebook.com/',
                    'instagram' => null,
                    'youtube' => null,
                    'website' => null,
                ],
                'status' => NewsPressProfile::STATUS_ACTIVE,
                'suspend_reason' => null,
                'suspended_at' => null,
                'suspended_by' => null,
            ]
        );

        $categoryIds = NewsCategory::query()
            ->whereIn('slug', ['india', 'politics', 'business', 'sports'])
            ->pluck('id')
            ->all();

        if (empty($categoryIds)) {
            $categoryIds = NewsCategory::query()->ordered()->limit(3)->pluck('id')->all();
        }

        if (!empty($categoryIds)) {
            $press->categories()->sync($categoryIds);
        }

        if (Schema::hasColumn('news_articles', 'press_id')) {
            $linked = NewsArticle::query()
                ->where('author_id', $editor->user_id)
                ->whereNull('press_id')
                ->limit(5)
                ->update(['press_id' => $press->id]);

            if ($linked === 0) {
                NewsArticle::query()
                    ->where('status', 'published')
                    ->whereNull('press_id')
                    ->orderByDesc('published_at')
                    ->limit(3)
                    ->update(['press_id' => $press->id]);
            }
        }

        $this->command?->info("Seeded press: {$press->name} → {$press->publicPath()}");
    }
}
