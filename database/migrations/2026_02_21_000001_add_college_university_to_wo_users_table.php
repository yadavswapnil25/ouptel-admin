<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('Wo_Users', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Users', 'college')) {
                $table->string('college', 255)->nullable();
            }
            if (!Schema::hasColumn('Wo_Users', 'university')) {
                $table->string('university', 255)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Wo_Users', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Users', 'college')) {
                $table->dropColumn('college');
            }
            if (Schema::hasColumn('Wo_Users', 'university')) {
                $table->dropColumn('university');
            }
        });
    }
};
