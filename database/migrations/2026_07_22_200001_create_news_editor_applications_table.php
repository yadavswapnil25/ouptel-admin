<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_editor_applications', function (Blueprint $table) {
            $table->id();
            // Wo_Users.user_id is signed int
            $table->integer('user_id')->index();
            $table->foreign('user_id')->references('user_id')->on('Wo_Users')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 50);
            $table->string('city');
            $table->string('state');
            $table->json('preferred_categories')->nullable();
            $table->text('bio');
            $table->string('portfolio_link')->nullable();
            $table->text('reason');
            $table->string('id_proof_name')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->integer('reviewed_by')->nullable();
            $table->foreign('reviewed_by')->references('user_id')->on('Wo_Users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_editor_applications');
    }
};
