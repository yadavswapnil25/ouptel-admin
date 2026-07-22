<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            if (!Schema::hasColumn('news_articles', 'tags')) {
                $table->json('tags')->nullable()->after('featured_image');
            }
            if (!Schema::hasColumn('news_articles', 'seo_meta_title')) {
                $table->string('seo_meta_title')->nullable()->after('tags');
            }
            if (!Schema::hasColumn('news_articles', 'seo_meta_description')) {
                $table->text('seo_meta_description')->nullable()->after('seo_meta_title');
            }
            if (!Schema::hasColumn('news_articles', 'review_feedback')) {
                $table->text('review_feedback')->nullable()->after('status');
            }
            if (!Schema::hasColumn('news_articles', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->index()->after('review_feedback');
            }
            if (!Schema::hasColumn('news_articles', 'reviewed_by')) {
                $table->integer('reviewed_by')->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('news_articles', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });

        // Expand status enum for editor workflow (safe if already applied)
        try {
            DB::statement("ALTER TABLE news_articles MODIFY COLUMN status ENUM('draft', 'pending_review', 'published', 'rejected', 'archived') NOT NULL DEFAULT 'draft'");
        } catch (\Throwable $e) {
            // ignore if already modified
        }
    }

    public function down(): void
    {
        DB::table('news_articles')
            ->whereIn('status', ['pending_review', 'rejected'])
            ->update(['status' => 'draft']);

        DB::statement("ALTER TABLE news_articles MODIFY COLUMN status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft'");

        Schema::table('news_articles', function (Blueprint $table) {
            $cols = [
                'tags',
                'seo_meta_title',
                'seo_meta_description',
                'review_feedback',
                'submitted_at',
                'reviewed_by',
                'reviewed_at',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('news_articles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
