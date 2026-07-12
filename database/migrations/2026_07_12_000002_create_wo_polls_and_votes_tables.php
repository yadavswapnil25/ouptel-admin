<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Poll options + votes (WoWonder-compatible). Safe if tables already exist.
     */
    public function up(): void
    {
        if (! Schema::hasTable('Wo_Polls')) {
            Schema::create('Wo_Polls', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('post_id')->index();
                $table->string('text', 255);
                $table->string('time', 50)->nullable();
            });
        }

        if (! Schema::hasTable('Wo_Votes')) {
            Schema::create('Wo_Votes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('post_id')->index();
                $table->unsignedBigInteger('option_id')->index();
                $table->unique(['user_id', 'post_id'], 'wo_votes_user_post_unique');
            });
        }
    }

    public function down(): void
    {
        // Do not drop production poll data on rollback.
    }
};
