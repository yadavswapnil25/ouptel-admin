<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who can see friend list on profile:
     * 0 = Everyone, 1 = My Friends, 2 = Nobody
     */
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Users')) {
            return;
        }

        Schema::table('Wo_Users', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Users', 'friend_privacy')) {
                $table->string('friend_privacy', 1)->default('0')->after('follow_privacy');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_Users')) {
            return;
        }

        Schema::table('Wo_Users', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Users', 'friend_privacy')) {
                $table->dropColumn('friend_privacy');
            }
        });
    }
};
