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

        if (Schema::hasColumn('Wo_Verification_Requests', 'video_challenge_code')) {
            return;
        }

        Schema::table('Wo_Verification_Requests', function (Blueprint $table) {
            $table->string('video_challenge_code', 20)
                ->nullable()
                ->comment('Random number user must speak during verification video');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_Verification_Requests')) {
            return;
        }

        Schema::table('Wo_Verification_Requests', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Verification_Requests', 'video_challenge_code')) {
                $table->dropColumn('video_challenge_code');
            }
        });
    }
};
