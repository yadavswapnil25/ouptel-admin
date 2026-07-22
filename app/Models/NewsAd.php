<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class NewsAd extends Model
{
    use HasFactory;

    protected $table = 'news_ads';

    protected $fillable = [
        'title',
        'headline',
        'image',
        'link_url',
        'placement',
        'display_order',
        'status',
        'starts_at',
        'ends_at',
        'clicks',
    ];

    protected $casts = [
        'status' => 'boolean',
        'display_order' => 'integer',
        'clicks' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected $appends = [
        'image_url',
    ];

    public const PLACEMENTS = [
        'sidebar' => 'News Sidebar (300×250)',
        'homepage' => 'News Homepage Banner',
        'article' => 'Article Page',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeCurrentlyRunning(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function scopeForPlacement(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderByDesc('id');
    }
}
