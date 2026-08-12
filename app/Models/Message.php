<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legacy Wo_Messages semantics, preserved deliberately:
 *
 *  - seen: 0 when unread, otherwise the unix timestamp it was read at.
 *  - deleted_one / deleted_two: enum('0','1') per-side delete flags, where "one"
 *    is the sender (from_id) and "two" is the recipient (to_id). They hold flags,
 *    not user ids, so they must be compared against '0' / '1' and never against a
 *    user id.
 *  - time: unix timestamp, not a datetime.
 */
class Message extends Model
{
    protected $table = 'Wo_Messages';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public const NOT_DELETED = '0';
    public const DELETED = '1';

    protected $fillable = [
        'conversation_id',
        'client_uuid',
        'from_id',
        'to_id',
        'group_id',
        'page_id',
        'text',
        'time',
        'seen',
        'delivered_at',
        'deleted_one',
        'deleted_two',
        'reply_id',
        'type_two',
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'from_id' => 'integer',
        'to_id' => 'integer',
        'group_id' => 'integer',
        'page_id' => 'integer',
        'time' => 'integer',
        'seen' => 'integer',
        'delivered_at' => 'integer',
        'reply_id' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_id', 'user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_id', 'user_id');
    }

    /**
     * Messages the given user has not deleted from their own side of the thread.
     */
    public function scopeVisibleTo($query, int $userId)
    {
        return $query->where(function ($query) use ($userId) {
            $query->where(function ($query) use ($userId) {
                $query->where('from_id', $userId)
                    ->where('deleted_one', self::NOT_DELETED);
            })->orWhere(function ($query) use ($userId) {
                $query->where('to_id', $userId)
                    ->where('deleted_two', self::NOT_DELETED);
            });
        });
    }

    /**
     * The single correct unread definition: addressed to the user, unread, and not
     * deleted from the recipient's side.
     */
    public function scopeUnreadFor($query, int $userId)
    {
        return $query->where('to_id', $userId)
            ->where('seen', 0)
            ->where('deleted_two', self::NOT_DELETED);
    }

    public function isRead(): bool
    {
        return $this->seen > 0;
    }
}
