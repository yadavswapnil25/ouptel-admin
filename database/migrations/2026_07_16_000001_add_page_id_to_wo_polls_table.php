<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional tagged page on a poll option (followed-page tagging).
     */
    public function up(): void
    {
        if (! Schema::hasTable('Wo_Polls')) {
            return;
        }

        if (! Schema::hasColumn('Wo_Polls', 'page_id')) {
            Schema::table('Wo_Polls', function (Blueprint $table) {
                $table->unsignedBigInteger('page_id')->nullable()->default(0)->after('text');
                $table->index('page_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('Wo_Polls') || ! Schema::hasColumn('Wo_Polls', 'page_id')) {
            return;
        }

        Schema::table('Wo_Polls', function (Blueprint $table) {
            $table->dropIndex(['page_id']);
            $table->dropColumn('page_id');
        });
    }
};
