<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_article_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_article_id')->constrained('news_articles')->cascadeOnDelete();
            // Wo_Users.user_id is signed int
            $table->integer('user_id');
            $table->text('text');
            $table->timestamps();

            $table->index(['news_article_id', 'created_at']);
            $table->foreign('user_id')->references('user_id')->on('Wo_Users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_article_comments');
    }
};
