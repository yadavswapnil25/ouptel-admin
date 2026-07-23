<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_press_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editor_id')->unique()->constrained('news_editors')->cascadeOnDelete();
            // Wo_Users.user_id is signed int
            $table->integer('user_id')->index();
            $table->foreign('user_id')->references('user_id')->on('Wo_Users')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 120)->unique();
            $table->string('logo')->nullable();
            $table->string('banner_image')->nullable();
            $table->string('tagline', 255)->nullable();
            $table->string('contact_email', 191)->nullable();
            $table->json('social_links')->nullable();
            $table->enum('status', ['active', 'suspended'])->default('active')->index();
            $table->text('suspend_reason')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->integer('suspended_by')->nullable();
            $table->foreign('suspended_by')->references('user_id')->on('Wo_Users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_press_profiles');
    }
};
