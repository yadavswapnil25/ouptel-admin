<?php

namespace App\Models;

use App\Helpers\ImageHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BlogChannel extends Model
{
    protected $table = 'Wo_Blog_Channels';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'avatar',
        'cover',
        'active',
        'time',
    ];

    protected $casts = [
        'time' => 'integer',
    ];

    public function setActiveAttribute($value): void
    {
        $this->attributes['active'] = (bool) $value ? '1' : '0';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function blogs(): HasMany
    {
        return $this->hasMany(Article::class, 'channel_id', 'id');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (empty($this->avatar)) {
            return ImageHelper::getPlaceholder('blog');
        }
        return ImageHelper::getImageUrl($this->avatar, 'blog');
    }

    public function getCoverUrlAttribute(): ?string
    {
        if (empty($this->cover)) {
            return null;
        }
        return ImageHelper::getImageUrl($this->cover, 'blog');
    }

    public function getUrlAttribute(): string
    {
        $frontendBase = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
        return "{$frontendBase}/blog/channels/{$this->id}";
    }

    public function getFollowersCountAttribute(): int
    {
        if (!Schema::hasTable('Wo_Blog_Channel_Followers')) {
            return 0;
        }
        return (int) DB::table('Wo_Blog_Channel_Followers')
            ->where('channel_id', $this->id)
            ->count();
    }

    public function getBlogsCountAttribute(): int
    {
        return (int) Article::query()
            ->where('channel_id', $this->id)
            ->where('active', '1')
            ->count();
    }

    public static function makeUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'channel';
        }
        $slug = $base;
        $i = 1;
        while (
            static::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    public function toSummaryArray(?string $viewerId = null): array
    {
        $isOwner = $viewerId && (string) $this->user_id === (string) $viewerId;
        $isFollowing = false;
        if ($viewerId && Schema::hasTable('Wo_Blog_Channel_Followers')) {
            $isFollowing = DB::table('Wo_Blog_Channel_Followers')
                ->where('channel_id', $this->id)
                ->where('user_id', $viewerId)
                ->exists();
        }

        $owner = $this->relationLoaded('owner') ? $this->owner : null;
        if (!$owner) {
            $owner = DB::table('Wo_Users')->where('user_id', $this->user_id)->first();
        }

        $ownerName = '';
        $ownerUsername = '';
        $ownerAvatar = null;
        if ($owner) {
            $ownerUsername = $owner->username ?? '';
            $ownerName = trim(($owner->first_name ?? '') . ' ' . ($owner->last_name ?? ''))
                ?: ($owner->name ?? $ownerUsername);
            if (!empty($owner->avatar)) {
                $ownerAvatar = asset('storage/' . ltrim($owner->avatar, '/'));
            }
        }

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?? '',
            'avatar' => $this->avatar,
            'avatar_url' => $this->avatar_url,
            'cover' => $this->cover,
            'cover_url' => $this->cover_url,
            'active' => ($this->active == '1' || $this->active === 1 || $this->active === true),
            'time' => (int) $this->time,
            'url' => $this->url,
            'followers_count' => $this->followers_count,
            'blogs_count' => $this->blogs_count,
            'is_owner' => (bool) $isOwner,
            'is_following' => (bool) $isFollowing,
            'user_id' => $this->user_id,
            'owner' => [
                'user_id' => $this->user_id,
                'username' => $ownerUsername,
                'name' => $ownerName,
                'avatar_url' => $ownerAvatar,
            ],
        ];
    }
}
