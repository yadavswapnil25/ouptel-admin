<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumReply extends Model
{
    protected $table = 'Wo_ForumReplies';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'topic_id',
        'user_id',
        'content',
        'active',
        'time',
    ];

    protected $casts = [
        'active' => 'string',
        'user_id' => 'string',
        'topic_id' => 'string',
        'time' => 'string',
    ];

    // Mutators to handle data type conversions
    public function setActiveAttribute($value)
    {
        $this->attributes['active'] = (bool) $value ? '1' : '0';
    }

    public function setTimeAttribute($value)
    {
        if (is_numeric($value)) {
            $this->attributes['time'] = (string) $value;
        } else {
            $this->attributes['time'] = (string) time();
        }
    }

    public function setUserIdAttribute($value)
    {
        $this->attributes['user_id'] = (string) $value;
    }

    public function setTopicIdAttribute($value)
    {
        $this->attributes['topic_id'] = (string) $value;
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'topic_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function getTimeAttribute($value)
    {
        if (is_numeric($value)) {
            return \Carbon\Carbon::createFromTimestamp((int) $value);
        }
        return $value;
    }

    public function getTimeAsTimestampAttribute()
    {
        $time = $this->attributes['time'] ?? null;
        if (is_numeric($time)) {
            return (int) $time;
        }
        return time();
    }
}
