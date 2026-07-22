<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_article_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_article_id')->constrained('news_articles')->cascadeOnDelete();
            $table->foreignId('news_category_id')->constrained('news_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['news_article_id', 'news_category_id'], 'news_article_category_unique');
        });

        // Preserve existing single-category assignments on the pivot.
        if (Schema::hasColumn('news_articles', 'category_id')) {
            $rows = DB::table('news_articles')
                ->whereNotNull('category_id')
                ->select('id', 'category_id', 'created_at', 'updated_at')
                ->get();

            $now = now();
            $payload = $rows->map(fn ($row) => [
                'news_article_id' => $row->id,
                'news_category_id' => $row->category_id,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ])->all();

            if (!empty($payload)) {
                DB::table('news_article_category')->insert($payload);
            }

            Schema::table('news_articles', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('content')
                ->constrained('news_categories')
                ->nullOnDelete();
        });

        $rows = DB::table('news_article_category')
            ->orderBy('id')
            ->get()
            ->groupBy('news_article_id');

        foreach ($rows as $articleId => $pivots) {
            $first = $pivots->first();
            DB::table('news_articles')
                ->where('id', $articleId)
                ->update(['category_id' => $first->news_category_id]);
        }

        Schema::dropIfExists('news_article_category');
    }
};
