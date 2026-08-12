<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    protected $table = 'Wo_ConversationParticipants';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'last_read_message_id',
        'last_read_at',
        'unread_count',
        'last_message_at',
        'muted_at',
        'archived_at',
        'cleared_at',
        'time',
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'user_id' => 'integer',
        'last_read_message_id' => 'integer',
        'last_read_at' => 'integer',
        'unread_count' => 'integer',
        'last_message_at' => 'integer',
        'muted_at' => 'integer',
        'archived_at' => 'integer',
        'cleared_at' => 'integer',
        'time' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Inbox rows for a user, newest thread first, excluding threads they cleared.
     * Ordering matches the (user_id, last_message_at) index.
     */
    public function scopeInboxFor($query, int $userId)
    {
        return $query->where('user_id', $userId)
            ->where('cleared_at', 0)
            ->orderByDesc('last_message_at');
    }

    public function isMuted(): bool
    {
        return $this->muted_at > 0;
    }
}
