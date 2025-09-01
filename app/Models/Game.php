<?php

namespace App\Models;

use App\Helpers\ImageHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $table = 'Wo_Games';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'game_name',
        'game_avatar',
        'game_link',
        'active',
        'time',
        'last_play',
    ];

    protected $casts = [
        'time' => 'integer',
        'last_play' => 'integer',
        'active' => 'integer',
    ];

    public function players(): HasMany
    {
        return $this->hasMany(GamePlayer::class, 'game_id', 'id');
    }

    public function getAvatarUrlAttribute(): string
    {
        if (!empty($this->game_avatar)) {
            return ImageHelper::getImageUrl($this->game_avatar, 'game');
        }
        return ImageHelper::getPlaceholder('game');
    }

    public function getActiveTextAttribute(): string
    {
        return $this->active ? 'Active' : 'Inactive';
    }

    public function getCreatedDateAttribute(): string
    {
        return date('Y-m-d H:i:s', $this->time);
    }

    public function getLastPlayedDateAttribute(): ?string
    {
        return $this->last_play ? date('Y-m-d H:i:s', $this->last_play) : null;
    }

    public function getPlayersCountAttribute(): int
    {
        return $this->players()->count();
    }

    public function getActivePlayersCountAttribute(): int
    {
        return $this->players()->where('active', 1)->count();
    }

    public function getGameUrlAttribute(): string
    {
        return $this->game_link ?: '#';
    }
}
