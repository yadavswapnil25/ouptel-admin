<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who can send friend requests by age group:
     * all | 0_17 | 18_24 | 25_34 | 35_44 | 45_54 | 55_64 | 65_plus | nobody
     */
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Users')) {
            return;
        }

        Schema::table('Wo_Users', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Users', 'friend_request_age_group')) {
                $after = Schema::hasColumn('Wo_Users', 'friend_privacy')
                    ? 'friend_privacy'
                    : 'follow_privacy';
                $table->string('friend_request_age_group', 20)->default('all')->after($after);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_Users')) {
            return;
        }

        Schema::table('Wo_Users', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Users', 'friend_request_age_group')) {
                $table->dropColumn('friend_request_age_group');
            }
        });
    }
};
