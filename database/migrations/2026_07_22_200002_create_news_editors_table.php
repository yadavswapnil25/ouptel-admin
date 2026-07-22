<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_editors', function (Blueprint $table) {
            $table->id();
            // Wo_Users.user_id is signed int
            $table->integer('user_id')->unique();
            $table->foreign('user_id')->references('user_id')->on('Wo_Users')->cascadeOnDelete();
            $table->json('preferred_categories')->nullable();
            $table->enum('status', ['active', 'revoked'])->default('active')->index();
            $table->integer('approved_by')->nullable();
            $table->foreign('approved_by')->references('user_id')->on('Wo_Users')->nullOnDelete();
            $table->unsignedBigInteger('application_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoke_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_editors');
    }
};
