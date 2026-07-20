<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Users')) {
            return;
        }

        Schema::table('Wo_Users', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Users', 'favourite_tv_shows')) {
                $table->text('favourite_tv_shows')->nullable();
            }
            if (!Schema::hasColumn('Wo_Users', 'favourite_music_bands')) {
                $table->text('favourite_music_bands')->nullable();
            }
            if (!Schema::hasColumn('Wo_Users', 'favourite_movies')) {
                $table->text('favourite_movies')->nullable();
            }
            if (!Schema::hasColumn('Wo_Users', 'favourite_books')) {
                $table->text('favourite_books')->nullable();
            }
            if (!Schema::hasColumn('Wo_Users', 'favourite_games')) {
                $table->text('favourite_games')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_Users')) {
            return;
        }

        Schema::table('Wo_Users', function (Blueprint $table) {
            foreach ([
                'favourite_tv_shows',
                'favourite_music_bands',
                'favourite_movies',
                'favourite_books',
                'favourite_games',
            ] as $column) {
                if (Schema::hasColumn('Wo_Users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
