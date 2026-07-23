<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_press_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_press_profile_id')->constrained('news_press_profiles')->cascadeOnDelete();
            $table->foreignId('news_category_id')->constrained('news_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['news_press_profile_id', 'news_category_id'],
                'news_press_category_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_press_category');
    }
};
