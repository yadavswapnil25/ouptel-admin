<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Pages')) {
            return;
        }

        Schema::table('Wo_Pages', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Pages', 'gov_registered')) {
                $table->boolean('gov_registered')->default(false)->after('verified');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_Pages')) {
            return;
        }

        Schema::table('Wo_Pages', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Pages', 'gov_registered')) {
                $table->dropColumn('gov_registered');
            }
        });
    }
};
