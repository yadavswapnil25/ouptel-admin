<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $table = 'Wo_Groups';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'group_name',
        'group_title',
        'about',
        'category',
        'sub_category',
        'privacy',
        'join_privacy',
        'active',
        'avatar',
        'cover',
        'user_id',
        'registered',
        'time',
    ];

    protected $casts = [
        'active' => 'string',
        'user_id' => 'string',
        'privacy' => 'string',
        'join_privacy' => 'string',
        'time' => 'string',
    ];

    // Mutator to handle ENUM values for active column
    public function setActiveAttribute($value)
    {
        $this->attributes['active'] = (bool) $value ? '1' : '0';
    }

    // Mutator to handle ENUM values for privacy column
    public function setPrivacyAttribute($value)
    {
        // Convert privacy values to database format
        $this->attributes['privacy'] = $value === 'public' ? '1' : '0';
    }

    // Mutator to handle ENUM values for join_privacy column
    public function setJoinPrivacyAttribute($value)
    {
        // Convert join_privacy values to database format
        $this->attributes['join_privacy'] = $value === 'public' ? '1' : '0';
    }

    // Mutator to handle time field as Unix timestamp
    public function setTimeAttribute($value)
    {
        // Ensure time is stored as Unix timestamp string
        if (is_numeric($value)) {
            $this->attributes['time'] = (string) $value;
        } else {
            $this->attributes['time'] = (string) time();
        }
    }

    // Mutators to prevent null values for optional fields
    public function setAvatarAttribute($value)
    {
        $this->attributes['avatar'] = $value ?: $this->attributes['avatar'] ?? '';
    }

    public function setCoverAttribute($value)
    {
        $this->attributes['cover'] = $value ?: $this->attributes['cover'] ?? '';
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function members()
    {
        return $this->hasMany(GroupMember::class, 'group_id', 'id');
    }

    public function getAvatarUrlAttribute(): string
    {
        return \App\Helpers\ImageHelper::getImageUrl($this->avatar, 'group');
    }

    public function getCoverUrlAttribute(): string
    {
        return \App\Helpers\ImageHelper::getCoverUrl($this->cover);
    }

    // Accessor to convert privacy from database format to readable format
    public function getPrivacyAttribute($value)
    {
        return $value === '1' ? 'public' : 'private';
    }

    // Accessor to convert join_privacy from database format to readable format
    public function getJoinPrivacyAttribute($value)
    {
        return $value === '1' ? 'public' : 'private';
    }

    // Accessor to convert time from Unix timestamp to Carbon instance
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
