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
        if (!Schema::hasTable('Wo_Events')) {
            return;
        }

        Schema::table('Wo_Events', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Events', 'agreement_accepted')) {
                $table->boolean('agreement_accepted')->default(false);
            }
            if (!Schema::hasColumn('Wo_Events', 'agreement_accepted_at')) {
                $table->dateTime('agreement_accepted_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('Wo_Events')) {
            return;
        }

        Schema::table('Wo_Events', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Events', 'agreement_accepted_at')) {
                $table->dropColumn('agreement_accepted_at');
            }
            if (Schema::hasColumn('Wo_Events', 'agreement_accepted')) {
                $table->dropColumn('agreement_accepted');
            }
        });
    }
};
