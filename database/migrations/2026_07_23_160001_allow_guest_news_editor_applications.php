<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_editor_applications', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        // Guest applications: user_id is filled when admin approves and creates/links account.
        DB::statement('ALTER TABLE news_editor_applications MODIFY user_id INT NULL');

        Schema::table('news_editor_applications', function (Blueprint $table) {
            $table->foreign('user_id')->references('user_id')->on('Wo_Users')->nullOnDelete();
            if (!Schema::hasColumn('news_editor_applications', 'credentials_sent_at')) {
                $table->timestamp('credentials_sent_at')->nullable()->after('reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news_editor_applications', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            if (Schema::hasColumn('news_editor_applications', 'credentials_sent_at')) {
                $table->dropColumn('credentials_sent_at');
            }
        });

        DB::statement('ALTER TABLE news_editor_applications MODIFY user_id INT NOT NULL');

        Schema::table('news_editor_applications', function (Blueprint $table) {
            $table->foreign('user_id')->references('user_id')->on('Wo_Users')->cascadeOnDelete();
        });
    }
};
