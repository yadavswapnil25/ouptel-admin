<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instagram-style story music: store third-party track reference only
     * (e.g. deezer:{id} + title/artist). Audio files are never hosted locally.
     */
    public function up(): void
    {
        if (!Schema::hasTable('Wo_UserStory')) {
            return;
        }

        Schema::table('Wo_UserStory', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_UserStory', 'music')) {
                $table->string('music', 500)->nullable()->after('description');
            }
            if (!Schema::hasColumn('Wo_UserStory', 'music_title')) {
                $table->string('music_title', 150)->nullable()->after('music');
            }
            if (!Schema::hasColumn('Wo_UserStory', 'music_artist')) {
                $table->string('music_artist', 150)->nullable()->after('music_title');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_UserStory')) {
            return;
        }

        Schema::table('Wo_UserStory', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('Wo_UserStory', 'music_artist')) {
                $cols[] = 'music_artist';
            }
            if (Schema::hasColumn('Wo_UserStory', 'music_title')) {
                $cols[] = 'music_title';
            }
            if (Schema::hasColumn('Wo_UserStory', 'music')) {
                $cols[] = 'music';
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
