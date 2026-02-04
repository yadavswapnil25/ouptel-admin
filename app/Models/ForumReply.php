<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumReply extends Model
{
    protected $table = 'Wo_ForumThreadReplies';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'thread_id', // Old WoWonder uses 'thread_id' not 'topic_id'
        'poster_id', // Old WoWonder uses 'poster_id' not 'user_id'
        'post', // Old WoWonder uses 'post' not 'content'
        'active',
        'posted_time', // Old WoWonder uses 'posted_time' not 'time'
    ];

    protected $casts = [
        'active' => 'string',
        'poster_id' => 'string', // Old WoWonder uses 'poster_id' not 'user_id'
        'thread_id' => 'string', // Old WoWonder uses 'thread_id' not 'topic_id'
        'posted_time' => 'string', // Old WoWonder uses 'posted_time' not 'time'
    ];

    // Mutators to handle data type conversions
    public function setActiveAttribute($value)
    {
        $this->attributes['active'] = (bool) $value ? '1' : '0';
    }

    public function setPostedTimeAttribute($value)
    {
        if (is_numeric($value)) {
            $this->attributes['posted_time'] = (string) $value;
        } else {
            $this->attributes['posted_time'] = (string) time();
        }
    }

    public function setPosterIdAttribute($value)
    {
        $this->attributes['poster_id'] = (string) $value;
    }

    public function setThreadIdAttribute($value)
    {
        $this->attributes['thread_id'] = (string) $value;
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'thread_id', 'id'); // Old WoWonder uses 'thread_id'
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'poster_id', 'user_id'); // Old WoWonder uses 'poster_id'
    }

    public function getPostedTimeAttribute($value)
    {
        if (is_numeric($value)) {
            return \Carbon\Carbon::createFromTimestamp((int) $value);
        }
        return $value;
    }

    public function getPostedTimeAsTimestampAttribute()
    {
        $postedTime = $this->attributes['posted_time'] ?? null;
        if (is_numeric($postedTime)) {
            return (int) $postedTime;
        }
        return time();
    }
    
    // Alias for backward compatibility
    public function getTimeAttribute()
    {
        return $this->getPostedTimeAttribute($this->attributes['posted_time'] ?? null);
    }
    
    public function getTimeAsTimestampAttribute()
    {
        return $this->getPostedTimeAsTimestampAttribute();
    }
}
