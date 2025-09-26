<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friend extends Model
{
    protected $table = 'Wo_Friends';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'friend_id',
        'status',
        'time',
    ];

    protected $casts = [
        'user_id' => 'string',
        'friend_id' => 'string',
        'status' => 'string',
        'time' => 'string',
    ];

    // Note: Wo_Users table might not exist
    // public function user(): BelongsTo
    // {
    //     return $this->belongsTo(User::class, 'user_id', 'user_id');
    // }

    // Note: Wo_Users table might not exist
    // public function friend(): BelongsTo
    // {
    //     return $this->belongsTo(User::class, 'friend_id', 'user_id');
    // }

    // Mutators
    public function setUserIdAttribute($value)
    {
        $this->attributes['user_id'] = (string) $value;
    }

    public function setFriendIdAttribute($value)
    {
        $this->attributes['friend_id'] = (string) $value;
    }

    public function setStatusAttribute($value)
    {
        $this->attributes['status'] = (string) $value;
    }

    public function setTimeAttribute($value)
    {
        if (is_numeric($value)) {
            $this->attributes['time'] = (string) $value;
        } else {
            $this->attributes['time'] = (string) time();
        }
    }

    // Accessors
    public function getStatusAttribute($value)
    {
        $statuses = [
            '1' => 'pending',
            '2' => 'accepted',
            '3' => 'declined',
            '4' => 'blocked',
        ];
        return $statuses[$value] ?? 'pending';
    }

    public function getTimeAttribute($value)
    {
        if (is_numeric($value)) {
            return \Carbon\Carbon::createFromTimestamp((int) $value);
        }
        return $value;
    }

    // Get time as Unix timestamp (for database operations)
    public function getTimeAsTimestampAttribute()
    {
        $time = $this->attributes['time'] ?? null;
        if (is_numeric($time)) {
            return (int) $time;
        }
        return time();
    }
}
