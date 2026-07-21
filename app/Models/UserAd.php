<?php

namespace App\Models;

use App\Helpers\ImageHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class UserAd extends Model
{
    protected $table = 'Wo_UserAds';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'page_id',
        'name',
        'url',
        'headline',
        'description',
        'location',
        'audience',
        'community_preferences',
        'gender',
        'age_group',
        'bidding',
        'appears',
        'ad_media',
        'budget',
        'start',
        'end',
        'posted',
        'status',
        'views',
        'clicks',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'views' => 'integer',
        'clicks' => 'integer',
        'posted' => 'integer',
        'page_id' => 'integer',
    ];

    protected $appends = [
        'media_url',
        'status_label',
        'gst_amount',
        'total_with_gst',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function getMediaUrlAttribute(): ?string
    {
        $path = trim((string) ($this->ad_media ?? ''));
        if ($path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $url = ImageHelper::getImageUrl($path, 'post');
        $placeholder = ImageHelper::getPlaceholder('post');

        return $url !== $placeholder ? $url : null;
    }

    public function getStatusLabelAttribute(): string
    {
        $status = $this->attributes['status'] ?? null;

        if ($status === 1 || $status === '1' || $status === 'active') {
            return 'active';
        }

        if ($status === 0 || $status === '0' || $status === 'paused') {
            return 'paused';
        }

        return is_string($status) && $status !== '' ? $status : 'paused';
    }

    public function getGstAmountAttribute(): float
    {
        return round(((float) ($this->budget ?? 0)) * 0.18, 2);
    }

    public function getTotalWithGstAttribute(): float
    {
        return round(((float) ($this->budget ?? 0)) + $this->gst_amount, 2);
    }

    public function getCommunityPreferenceIdsAttribute(): array
    {
        $value = trim((string) ($this->attributes['community_preferences'] ?? ''));
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $value))));
    }

    public function setStatusAttribute($value): void
    {
        $normalized = in_array($value, ['paused', 0, '0'], true) ? 'paused' : 'active';

        if ($this->statusColumnIsInteger()) {
            $this->attributes['status'] = $normalized === 'active' ? 1 : 0;

            return;
        }

        $this->attributes['status'] = $normalized;
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'active')->orWhere('status', 1)->orWhere('status', '1');
        });
    }

    public function scopePaused($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'paused')->orWhere('status', 0)->orWhere('status', '0');
        });
    }

    private function statusColumnIsInteger(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        if (!Schema::hasTable('Wo_UserAds') || !Schema::hasColumn('Wo_UserAds', 'status')) {
            $cached = false;

            return $cached;
        }

        $type = Schema::getColumnType('Wo_UserAds', 'status');
        $cached = in_array($type, ['integer', 'bigint', 'smallint', 'tinyint', 'int'], true);

        return $cached;
    }
}
