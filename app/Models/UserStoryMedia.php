<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserStoryMedia extends Model
{
    use HasFactory;

    protected $table = 'Wo_UserStoryMedia';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'story_id',
        'type',
        'filename',
        'expire',
    ];

    public function story()
    {
        return $this->belongsTo(UserStory::class, 'story_id', 'id');
    }

    public function getExpiresDateAttribute(): ?string
    {
        return $this->expire ? date('Y-m-d H:i:s', $this->expire) : null;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expire && $this->expire < time();
    }
}


