<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('Wo_UserSurveyResponses')) {
            return;
        }

        Schema::create('Wo_UserSurveyResponses', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 64)->unique();
            $table->text('struggling_with');
            $table->string('hear_about', 64);
            $table->string('hear_about_other', 255)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Wo_UserSurveyResponses');
    }
};

