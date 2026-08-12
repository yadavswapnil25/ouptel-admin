<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $table = 'Wo_Conversations';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public const TYPE_DIRECT = 'direct';
    public const TYPE_GROUP = 'group';

    protected $fillable = [
        'type',
        'user_one_id',
        'user_two_id',
        'last_message_id',
        'last_message_at',
        'last_message_preview',
        'time',
    ];

    protected $casts = [
        'user_one_id' => 'integer',
        'user_two_id' => 'integer',
        'last_message_id' => 'integer',
        'last_message_at' => 'integer',
        'time' => 'integer',
    ];

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    /**
     * Direct threads store the lower user id first so that a pair maps to exactly
     * one row regardless of who opened the thread.
     */
    public static function normalizePair(int $userId, int $otherUserId): array
    {
        return $userId <= $otherUserId
            ? [$userId, $otherUserId]
            : [$otherUserId, $userId];
    }

    public function scopeDirectBetween($query, int $userId, int $otherUserId)
    {
        [$one, $two] = self::normalizePair($userId, $otherUserId);

        return $query->where('type', self::TYPE_DIRECT)
            ->where('user_one_id', $one)
            ->where('user_two_id', $two);
    }

    public function otherParticipantId(int $userId): ?int
    {
        if ($this->type !== self::TYPE_DIRECT) {
            return null;
        }

        return (int) $this->user_one_id === $userId
            ? (int) $this->user_two_id
            : (int) $this->user_one_id;
    }
}
