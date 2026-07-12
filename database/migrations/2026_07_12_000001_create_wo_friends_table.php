<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Friend relationships (independent from Wo_Followers follows).
     * status: 0 = pending request, 2 = accepted friends
     */
    public function up(): void
    {
        if (Schema::hasTable('Wo_Friends')) {
            return;
        }

        Schema::create('Wo_Friends', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('friend_id')->index();
            $table->string('status', 11)->default('0')->index();
            $table->string('time', 50)->nullable();
            $table->unique(['user_id', 'friend_id'], 'wo_friends_user_friend_unique');
        });
    }

    public function down(): void
    {
        // Do not drop production friendship data on rollback.
    }
};
