<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('Wo_Colored_Posts')) {
            return;
        }

        Schema::create('Wo_Colored_Posts', function (Blueprint $table) {
            $table->id();
            $table->string('color_1', 32)->nullable();
            $table->string('color_2', 32)->nullable();
            $table->string('text_color', 32)->nullable();
            $table->string('image', 500)->nullable();
            $table->unsignedInteger('time')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Wo_Colored_Posts');
    }
};
