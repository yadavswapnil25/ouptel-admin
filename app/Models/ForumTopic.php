<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumTopic extends Model
{
    protected $table = 'Wo_ForumTopics';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'forum_id',
        'user_id',
        'subject',
        'content',
        'pinned',
        'locked',
        'active',
        'time',
    ];

    protected $casts = [
        'active' => 'string',
        'user_id' => 'string',
        'forum_id' => 'string',
        'pinned' => 'string',
        'locked' => 'string',
        'time' => 'string',
    ];

    // Mutators to handle data type conversions
    public function setActiveAttribute($value)
    {
        $this->attributes['active'] = (bool) $value ? '1' : '0';
    }

    public function setPinnedAttribute($value)
    {
        $this->attributes['pinned'] = (bool) $value ? '1' : '0';
    }

    public function setLockedAttribute($value)
    {
        $this->attributes['locked'] = (bool) $value ? '1' : '0';
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

    public function setForumIdAttribute($value)
    {
        $this->attributes['forum_id'] = (string) $value;
    }

    public function forum(): BelongsTo
    {
        return $this->belongsTo(Forum::class, 'forum_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ForumReply::class, 'topic_id', 'id');
    }

    public function getRepliesCountAttribute(): int
    {
        return $this->replies()->count();
    }

    public function getLastReplyAttribute()
    {
        return $this->replies()->latest('time')->first();
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
