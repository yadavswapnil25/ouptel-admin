<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add video verification columns if missing on existing Wo_Verification_Requests table.
     * Safe / additive — does not drop or recreate the table.
     */
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Verification_Requests')) {
            return;
        }

        Schema::table('Wo_Verification_Requests', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Verification_Requests', 'verification_video')) {
                $table->string('verification_video', 255)
                    ->nullable()
                    ->comment('Path to verification video file');
            }

            if (!Schema::hasColumn('Wo_Verification_Requests', 'video_size')) {
                $table->integer('video_size')
                    ->nullable()
                    ->comment('Video file size in bytes');
            }

            if (!Schema::hasColumn('Wo_Verification_Requests', 'video_duration')) {
                $table->integer('video_duration')
                    ->nullable()
                    ->comment('Video duration in seconds');
            }

            if (!Schema::hasColumn('Wo_Verification_Requests', 'video_uploaded_at')) {
                $table->timestamp('video_uploaded_at')
                    ->nullable()
                    ->comment('When the video was uploaded');
            }

            if (!Schema::hasColumn('Wo_Verification_Requests', 'video_challenge_code')) {
                $table->string('video_challenge_code', 20)
                    ->nullable()
                    ->comment('Random number user must speak during verification video');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_Verification_Requests')) {
            return;
        }

        Schema::table('Wo_Verification_Requests', function (Blueprint $table) {
            $columns = [
                'verification_video',
                'video_size',
                'video_duration',
                'video_uploaded_at',
                'video_challenge_code',
            ];

            $existing = array_values(array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn('Wo_Verification_Requests', $column)
            ));

            if (!empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};
