<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumTopic extends Model
{
    protected $table = 'Wo_Forum_Threads';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'forum', // Old WoWonder uses 'forum' not 'forum_id'
        'user', // Old WoWonder uses 'user' not 'user_id'
        'headline', // Old WoWonder uses 'headline' not 'subject'
        'post', // Old WoWonder uses 'post' not 'content'
        'pinned',
        'locked',
        'active',
        'posted', // Old WoWonder uses 'posted' not 'time'
    ];

    protected $casts = [
        'active' => 'string',
        'user' => 'string', // Old WoWonder uses 'user' not 'user_id'
        'forum' => 'string', // Old WoWonder uses 'forum' not 'forum_id'
        'pinned' => 'string',
        'locked' => 'string',
        'posted' => 'string', // Old WoWonder uses 'posted' not 'time'
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

    public function setPostedAttribute($value)
    {
        if (is_numeric($value)) {
            $this->attributes['posted'] = (string) $value;
        } else {
            $this->attributes['posted'] = (string) time();
        }
    }

    public function setUserAttribute($value)
    {
        $this->attributes['user'] = (string) $value;
    }

    public function setForumAttribute($value)
    {
        $this->attributes['forum'] = (string) $value;
    }

    public function forum(): BelongsTo
    {
        return $this->belongsTo(Forum::class, 'forum', 'id'); // Old WoWonder uses 'forum' not 'forum_id'
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user', 'user_id'); // Old WoWonder uses 'user' column
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ForumReply::class, 'thread_id', 'id'); // Old WoWonder uses 'thread_id' not 'topic_id'
    }

    public function getRepliesCountAttribute(): int
    {
        return $this->replies()->count();
    }

    public function getLastReplyAttribute()
    {
        return $this->replies()->latest('time')->first();
    }

    public function getPostedAttribute($value)
    {
        if (is_numeric($value)) {
            return \Carbon\Carbon::createFromTimestamp((int) $value);
        }
        return $value;
    }

    public function getPostedAsTimestampAttribute()
    {
        $posted = $this->attributes['posted'] ?? null;
        if (is_numeric($posted)) {
            return (int) $posted;
        }
        return time();
    }
    
    // Alias for backward compatibility
    public function getTimeAttribute()
    {
        return $this->getPostedAttribute($this->attributes['posted'] ?? null);
    }
    
    public function getTimeAsTimestampAttribute()
    {
        return $this->getPostedAsTimestampAttribute();
    }
}
