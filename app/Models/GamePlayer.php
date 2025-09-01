<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamePlayer extends Model
{
    use HasFactory;

    protected $table = 'Wo_Games_Players';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'game_id',
        'last_play',
        'active',
    ];

    protected $casts = [
        'last_play' => 'integer',
        'active' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_id', 'id');
    }

    public function getLastPlayedDateAttribute(): ?string
    {
        return $this->last_play ? date('Y-m-d H:i:s', $this->last_play) : null;
    }

    public function getActiveTextAttribute(): string
    {
        return $this->active ? 'Active' : 'Inactive';
    }

    public function getTimeAgoAttribute(): string
    {
        if (!$this->last_play) {
            return 'Never played';
        }

        $time = time() - $this->last_play;
        
        if ($time < 60) {
            return 'Just now';
        } elseif ($time < 3600) {
            return floor($time / 60) . ' minutes ago';
        } elseif ($time < 86400) {
            return floor($time / 3600) . ' hours ago';
        } elseif ($time < 2592000) {
            return floor($time / 86400) . ' days ago';
        } else {
            return floor($time / 2592000) . ' months ago';
        }
    }
}
