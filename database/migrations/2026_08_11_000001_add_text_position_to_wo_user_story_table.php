<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instagram-style story text overlay position (percent of media frame).
     */
    public function up(): void
    {
        if (!Schema::hasTable('Wo_UserStory')) {
            return;
        }

        Schema::table('Wo_UserStory', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_UserStory', 'text_x')) {
                $table->decimal('text_x', 5, 2)->nullable()->after('description');
            }
            if (!Schema::hasColumn('Wo_UserStory', 'text_y')) {
                $table->decimal('text_y', 5, 2)->nullable()->after('text_x');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_UserStory')) {
            return;
        }

        Schema::table('Wo_UserStory', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('Wo_UserStory', 'text_y')) {
                $cols[] = 'text_y';
            }
            if (Schema::hasColumn('Wo_UserStory', 'text_x')) {
                $cols[] = 'text_x';
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
