<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventReaction extends Model
{
    use HasFactory;

    protected $table = 'Wo_Event_Reaction';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'user_id',
        'comment_id',
        'reply_id',
        'reaction',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(EventComment::class, 'comment_id', 'id');
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(EventCommentReply::class, 'reply_id', 'id');
    }
}



