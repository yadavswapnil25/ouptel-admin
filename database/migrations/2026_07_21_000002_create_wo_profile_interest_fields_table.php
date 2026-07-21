<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Profile_Interest_Fields')) {
            Schema::create('Wo_Profile_Interest_Fields', function (Blueprint $table) {
                $table->id();
                $table->string('field_key', 100)->unique();
                $table->string('label', 191);
                $table->string('placeholder', 255)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('storage_column', 100)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('Wo_Users') && !Schema::hasColumn('Wo_Users', 'profile_interests_extra')) {
            Schema::table('Wo_Users', function (Blueprint $table) {
                $table->json('profile_interests_extra')->nullable();
            });
        }

        if (!Schema::hasTable('Wo_Profile_Interest_Fields')) {
            return;
        }

        $defaults = [
            [
                'field_key' => 'favourite_tv_shows',
                'label' => 'Favourite TV Shows',
                'placeholder' => 'e.g. Breaking Bad, Friends',
                'sort_order' => 1,
                'storage_column' => 'favourite_tv_shows',
            ],
            [
                'field_key' => 'favourite_music_bands',
                'label' => 'Favourite Music Bands / Artists',
                'placeholder' => 'e.g. The Beatles, AR Rahman',
                'sort_order' => 2,
                'storage_column' => 'favourite_music_bands',
            ],
            [
                'field_key' => 'favourite_movies',
                'label' => 'Favourite Movies',
                'placeholder' => 'e.g. Inception, 3 Idiots',
                'sort_order' => 3,
                'storage_column' => 'favourite_movies',
            ],
            [
                'field_key' => 'favourite_books',
                'label' => 'Favourite Books',
                'placeholder' => 'e.g. Atomic Habits, Harry Potter',
                'sort_order' => 4,
                'storage_column' => 'favourite_books',
            ],
            [
                'field_key' => 'favourite_games',
                'label' => 'Favourite Games',
                'placeholder' => 'e.g. FIFA, Minecraft',
                'sort_order' => 5,
                'storage_column' => 'favourite_games',
            ],
        ];

        $now = now();
        foreach ($defaults as $row) {
            $exists = DB::table('Wo_Profile_Interest_Fields')
                ->where('field_key', $row['field_key'])
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('Wo_Profile_Interest_Fields')->insert([
                'field_key' => $row['field_key'],
                'label' => $row['label'],
                'placeholder' => $row['placeholder'],
                'sort_order' => $row['sort_order'],
                'is_active' => true,
                'storage_column' => $row['storage_column'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('Wo_Users') && Schema::hasColumn('Wo_Users', 'profile_interests_extra')) {
            Schema::table('Wo_Users', function (Blueprint $table) {
                $table->dropColumn('profile_interests_extra');
            });
        }

        Schema::dropIfExists('Wo_Profile_Interest_Fields');
    }
};
