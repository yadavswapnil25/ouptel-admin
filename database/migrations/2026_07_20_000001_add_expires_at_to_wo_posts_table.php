<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Posts')) {
            return;
        }

        Schema::table('Wo_Posts', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Posts', 'expires_at')) {
                $table->unsignedBigInteger('expires_at')->default(0)->after('time');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_Posts')) {
            return;
        }

        Schema::table('Wo_Posts', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Posts', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });
    }
};
