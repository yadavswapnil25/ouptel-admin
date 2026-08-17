<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Pages') || Schema::hasColumn('Wo_Pages', 'registration_type')) {
            return;
        }

        Schema::table('Wo_Pages', function (Blueprint $table) {
            $table->string('registration_type', 40)->nullable()->after('gov_registered');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_Pages') || !Schema::hasColumn('Wo_Pages', 'registration_type')) {
            return;
        }

        Schema::table('Wo_Pages', function (Blueprint $table) {
            $table->dropColumn('registration_type');
        });
    }
};
