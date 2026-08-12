<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per chat thread.
     *
     * user_one_id / user_two_id are normalised so user_one_id always holds the
     * lower user id. The unique pair index is what prevents two people who open
     * the same thread at the same moment from creating duplicate conversations.
     * Both columns are NULL for group threads, which MySQL permits repeatedly in
     * a unique index.
     *
     * Columns are signed int rather than bigint to match Wo_Users.user_id and
     * Wo_Messages.id in the legacy schema. The legacy schema declares no foreign
     * keys, so none are added here.
     *
     * last_message_* are denormalised so rendering the inbox does not need a
     * correlated subquery per row.
     */
    public function up(): void
    {
        if (Schema::hasTable('Wo_Conversations')) {
            return;
        }

        Schema::create('Wo_Conversations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type', 16)->default('direct');
            $table->integer('user_one_id')->nullable();
            $table->integer('user_two_id')->nullable();
            $table->integer('last_message_id')->default(0);
            $table->integer('last_message_at')->default(0);
            $table->string('last_message_preview', 255)->default('');
            $table->integer('time')->default(0);

            $table->unique(['user_one_id', 'user_two_id'], 'wo_conversations_pair_unique');
            $table->index('last_message_at', 'wo_conversations_last_message_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Wo_Conversations');
    }
};
