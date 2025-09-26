<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumMember extends Model
{
    protected $table = 'Wo_ForumMembers';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'forum_id',
        'user_id',
        'role',
        'time',
    ];

    protected $casts = [
        'user_id' => 'string',
        'forum_id' => 'string',
        'time' => 'string',
    ];

    // Mutators to handle data type conversions
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
