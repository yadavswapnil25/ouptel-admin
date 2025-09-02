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
        'active' => 'boolean',
        'time' => 'datetime',
    ];

    // Mutator to handle ENUM values for active column
    public function setActiveAttribute($value)
    {
        $this->attributes['active'] = (bool) $value ? '1' : '0';
    }

    // Mutator to handle ENUM values for privacy column
    public function setPrivacyAttribute($value)
    {
        $this->attributes['privacy'] = $value ?: 'public';
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

    public function getAvatarUrlAttribute(): string
    {
        return \App\Helpers\ImageHelper::getImageUrl($this->avatar, 'group');
    }

    public function getCoverUrlAttribute(): string
    {
        return \App\Helpers\ImageHelper::getCoverUrl($this->cover);
    }
}
