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
        Schema::table('Wo_Groups', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Groups', 'agreement_accepted')) {
                $table->boolean('agreement_accepted')->default(false);
            }
            if (!Schema::hasColumn('Wo_Groups', 'agreement_accepted_at')) {
                $table->dateTime('agreement_accepted_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Wo_Groups', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Groups', 'agreement_accepted_at')) {
                $table->dropColumn('agreement_accepted_at');
            }
            if (Schema::hasColumn('Wo_Groups', 'agreement_accepted')) {
                $table->dropColumn('agreement_accepted');
            }
        });
    }
};
