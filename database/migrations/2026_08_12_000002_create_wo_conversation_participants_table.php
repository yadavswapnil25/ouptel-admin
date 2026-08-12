<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user state within a conversation: read position, unread count, and
     * per-user visibility flags.
     *
     * Read receipts are stored as a single read cursor (last_read_message_id)
     * instead of a receipt row per message per user, which is what keeps this
     * table small as message volume grows. For a two-person thread the cursor is
     * enough to render sent / delivered / read ticks.
     *
     * last_message_at duplicates Wo_Conversations.last_message_at so the inbox
     * query can be ordered straight off the (user_id, last_message_at) index
     * instead of sorting joined rows. This row is already being written on every
     * message to bump unread_count, so the extra column is close to free.
     *
     * All *_at columns use the legacy unix-int convention where 0 means unset.
     * The per-user hide flag is named cleared_at rather than deleted_at to avoid
     * colliding with Eloquent's SoftDeletes column if it is ever added here.
     */
    public function up(): void
    {
        if (Schema::hasTable('Wo_ConversationParticipants')) {
            return;
        }

        Schema::create('Wo_ConversationParticipants', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('conversation_id');
            $table->integer('user_id');
            $table->integer('last_read_message_id')->default(0);
            $table->integer('last_read_at')->default(0);
            $table->unsignedInteger('unread_count')->default(0);
            $table->integer('last_message_at')->default(0);
            $table->integer('muted_at')->default(0);
            $table->integer('archived_at')->default(0);
            $table->integer('cleared_at')->default(0);
            $table->integer('time')->default(0);

            $table->unique(['conversation_id', 'user_id'], 'wo_conversation_participants_unique');
            $table->index(['user_id', 'last_message_at'], 'wo_conversation_participants_inbox_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Wo_ConversationParticipants');
    }
};
