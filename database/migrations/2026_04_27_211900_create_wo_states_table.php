<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('Wo_States')) {
            return;
        }

        Schema::create('Wo_States', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('country_id')->nullable();
            $table->string('name', 191);
            $table->string('photo')->nullable();
            $table->unique(['country_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Wo_States');
    }
};

