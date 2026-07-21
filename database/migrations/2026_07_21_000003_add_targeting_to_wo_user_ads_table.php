<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('Wo_UserAds')) {
            return;
        }

        Schema::table('Wo_UserAds', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_UserAds', 'age_group')) {
                $table->string('age_group', 32)->default('all')->after('gender');
            }
            if (!Schema::hasColumn('Wo_UserAds', 'community_preferences')) {
                $table->text('community_preferences')->nullable()->after('audience');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_UserAds')) {
            return;
        }

        Schema::table('Wo_UserAds', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_UserAds', 'age_group')) {
                $table->dropColumn('age_group');
            }
            if (Schema::hasColumn('Wo_UserAds', 'community_preferences')) {
                $table->dropColumn('community_preferences');
            }
        });
    }
};
