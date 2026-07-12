<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-only age restriction for joining groups.
     * Values: 0_17, 18_24, 25_34, 35_44, 45_54, 55_64, 65_plus (null = no restriction).
     */
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Groups')) {
            return;
        }

        Schema::table('Wo_Groups', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Groups', 'age_group')) {
                $table->string('age_group', 20)->nullable()->after('join_privacy');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_Groups')) {
            return;
        }

        Schema::table('Wo_Groups', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Groups', 'age_group')) {
                $table->dropColumn('age_group');
            }
        });
    }
};
