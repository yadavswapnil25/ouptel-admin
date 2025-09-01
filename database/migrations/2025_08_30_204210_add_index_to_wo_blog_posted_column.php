<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('Wo_Blog', function (Blueprint $table) {
            $table->index('posted');
            $table->index('active');
            $table->index('user');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Wo_Blog', function (Blueprint $table) {
            $table->dropIndex(['posted']);
            $table->dropIndex(['active']);
            $table->dropIndex(['user']);
            $table->dropIndex(['category']);
        });
    }
};