<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Wo_Users', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Users', 'custom_status_text')) {
                $table->string('custom_status_text', 120)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('Wo_Users', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Users', 'custom_status_text')) {
                $table->dropColumn('custom_status_text');
            }
        });
    }
};
