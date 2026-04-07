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
        Schema::table('Wo_Pages', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Pages', 'email')) {
                $table->string('email', 255)->nullable();
            }
            if (!Schema::hasColumn('Wo_Pages', 'social_link')) {
                $table->string('social_link', 500)->nullable();
            }
            if (!Schema::hasColumn('Wo_Pages', 'agreement_accepted')) {
                $table->boolean('agreement_accepted')->default(false);
            }
            if (!Schema::hasColumn('Wo_Pages', 'agreement_accepted_at')) {
                $table->dateTime('agreement_accepted_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Wo_Pages', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Pages', 'agreement_accepted_at')) {
                $table->dropColumn('agreement_accepted_at');
            }
            if (Schema::hasColumn('Wo_Pages', 'agreement_accepted')) {
                $table->dropColumn('agreement_accepted');
            }
            if (Schema::hasColumn('Wo_Pages', 'social_link')) {
                $table->dropColumn('social_link');
            }
            if (Schema::hasColumn('Wo_Pages', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};

