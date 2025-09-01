<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventComment extends Model
{
    use HasFactory;

    protected $table = 'Wo_Event_Comments';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'user_id',
        'text',
        'time',
    ];

    protected $casts = [
        'time' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(EventCommentReply::class, 'comment_id', 'id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(EventReaction::class, 'comment_id', 'id');
    }

    public function getCommentedDateAttribute()
    {
        return date('Y-m-d H:i:s', $this->time);
    }
}



