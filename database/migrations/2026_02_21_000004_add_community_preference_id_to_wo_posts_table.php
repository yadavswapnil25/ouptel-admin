<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Wo_Posts', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Posts', 'community_preference_id')) {
                $table->unsignedBigInteger('community_preference_id')->nullable()->after('active');
                $table->foreign('community_preference_id')->references('id')->on('community_preferences')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('Wo_Posts', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Posts', 'community_preference_id')) {
                $table->dropForeign(['community_preference_id']);
                $table->dropColumn('community_preference_id');
            }
        });
    }
};
