<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_community_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('Wo_Users.user_id');
            $table->unsignedBigInteger('preference_id');
            $table->timestamps();

            $table->foreign('preference_id')->references('id')->on('community_preferences')->cascadeOnDelete();
            $table->unique(['user_id', 'preference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_community_preferences');
    }
};
