<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserStory extends Model
{
    use HasFactory;

    protected $table = 'Wo_UserStory';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'title',
        'description',
        'posted',
        'expire',
        'thumbnail',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function media()
    {
        return $this->hasMany(UserStoryMedia::class, 'story_id', 'id');
    }

    public function getExpiresDateAttribute(): ?string
    {
        return $this->expire;
    }

    public function getPostedDateAttribute(): ?string
    {
        return $this->posted;
    }

    public function getIsExpiredAttribute(): bool
    {
        // Since expire is a varchar, we'll assume it's a date string
        // You may need to adjust this logic based on the actual format
        return $this->expire && strtotime($this->expire) < time();
    }
}
