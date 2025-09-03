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
    ];

    protected $casts = [
        'time' => 'integer',
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
        // Since last_play column doesn't exist, return null or use time as fallback
        return null;
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

    // Prevent setting last_play field since it doesn't exist in database
    public function setLastPlayAttribute($value): void
    {
        // Do nothing - this field doesn't exist in the database
        // This prevents errors when code tries to set last_play
    }

    // Ensure game_avatar always has a value since it's NOT NULL in database
    public function setGameAvatarAttribute($value): void
    {
        if (empty($value)) {
            // Set a default placeholder image if no avatar is provided
            $this->attributes['game_avatar'] = 'default-game-avatar.jpg';
        } else {
            $this->attributes['game_avatar'] = $value;
        }
    }

    // Ensure game_link always has a value since it's NOT NULL in database
    public function setGameLinkAttribute($value): void
    {
        if (empty($value)) {
            // Set a default value if no link is provided
            $this->attributes['game_link'] = '#';
        } else {
            $this->attributes['game_link'] = $value;
        }
    }

    // Ensure active always has a value since it's NOT NULL in database
    public function setActiveAttribute($value): void
    {
        $this->attributes['active'] = $value ?? 1; // Default to 1 (active) if null
    }
}
