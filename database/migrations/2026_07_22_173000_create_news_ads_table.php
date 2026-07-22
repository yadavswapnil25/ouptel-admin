<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_ads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('headline')->nullable();
            $table->string('image')->nullable();
            $table->string('link_url', 512)->nullable();
            $table->string('placement', 50)->default('sidebar');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['placement', 'status', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_ads');
    }
};
