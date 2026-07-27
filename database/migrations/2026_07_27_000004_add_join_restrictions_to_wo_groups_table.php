<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Groups')) {
            return;
        }

        Schema::table('Wo_Groups', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Groups', 'allowed_gender')) {
                $table->string('allowed_gender', 20)->nullable()->after('join_privacy');
            }

            if (!Schema::hasColumn('Wo_Groups', 'community_preferences')) {
                $table->text('community_preferences')->nullable()->after('allowed_gender');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_Groups')) {
            return;
        }

        Schema::table('Wo_Groups', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Groups', 'community_preferences')) {
                $table->dropColumn('community_preferences');
            }

            if (Schema::hasColumn('Wo_Groups', 'allowed_gender')) {
                $table->dropColumn('allowed_gender');
            }
        });
    }
};
