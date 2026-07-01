<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Verification_Requests')) {
            return;
        }

        Schema::table('Wo_Verification_Requests', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Verification_Requests', 'page_id')) {
                $table->unsignedBigInteger('page_id')->nullable()->index()->after('user_id');
            }
            if (!Schema::hasColumn('Wo_Verification_Requests', 'message')) {
                $table->string('message', 500)->nullable()->after('type');
            }
            if (!Schema::hasColumn('Wo_Verification_Requests', 'passport')) {
                $table->string('passport', 255)->nullable();
            }
            if (!Schema::hasColumn('Wo_Verification_Requests', 'photo')) {
                $table->string('photo', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_Verification_Requests')) {
            return;
        }

        Schema::table('Wo_Verification_Requests', function (Blueprint $table) {
            foreach (['page_id', 'message', 'passport', 'photo'] as $column) {
                if (Schema::hasColumn('Wo_Verification_Requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
