<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique()->index();
            $table->text('excerpt');
            $table->longText('content');
            $table->foreignId('category_id')->constrained('news_categories')->onDelete('cascade');
            $table->string('featured_image')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('Wo_users', 'user_id')->onDelete('set null');
            $table->string('author_name')->default('News Team');
            $table->integer('views')->default(0)->index();
            $table->integer('shares')->default(0);
            $table->boolean('featured')->default(false)->index();
            $table->boolean('breaking')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->timestamps();

            // Indexes for better query performance
            $table->index('featured');
            $table->index('breaking');
            $table->index('status');
            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_articles');
    }
};
