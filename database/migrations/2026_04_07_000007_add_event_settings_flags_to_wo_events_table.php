<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Public / join / publish toggles for event creation (API + CreateEvent form).
     */
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Events')) {
            return;
        }

        Schema::table('Wo_Events', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Events', 'is_public')) {
                $table->boolean('is_public')->default(true);
            }
            if (!Schema::hasColumn('Wo_Events', 'allow_join')) {
                $table->boolean('allow_join')->default(true);
            }
            if (!Schema::hasColumn('Wo_Events', 'published')) {
                $table->boolean('published')->default(true);
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
            if (Schema::hasColumn('Wo_Events', 'published')) {
                $table->dropColumn('published');
            }
            if (Schema::hasColumn('Wo_Events', 'allow_join')) {
                $table->dropColumn('allow_join');
            }
            if (Schema::hasColumn('Wo_Events', 'is_public')) {
                $table->dropColumn('is_public');
            }
        });
    }
};
