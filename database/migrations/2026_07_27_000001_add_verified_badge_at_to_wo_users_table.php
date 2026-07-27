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
            if (!Schema::hasColumn('Wo_Users', 'verified_badge_at')) {
                $table->timestamp('verified_badge_at')->nullable()->after('verified');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_Users')) {
            return;
        }

        Schema::table('Wo_Users', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Users', 'verified_badge_at')) {
                $table->dropColumn('verified_badge_at');
            }
        });
    }
};
