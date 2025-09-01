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
