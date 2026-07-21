<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoostCampaign extends Model
{
    protected $table = 'Wo_Boost_Campaigns';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'post_id',
        'goal',
        'audience_gender',
        'audience_countries',
        'duration_days',
        'budget',
        'status',
        'starts_at',
        'ends_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'duration_days' => 'integer',
        'post_id' => 'integer',
        'starts_at' => 'integer',
        'ends_at' => 'integer',
        'created_at' => 'integer',
        'updated_at' => 'integer',
    ];

    protected $appends = [
        'gst_amount',
        'total_with_gst',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }

    public function getGstAmountAttribute(): float
    {
        return round(((float) ($this->budget ?? 0)) * 0.18, 2);
    }

    public function getTotalWithGstAttribute(): float
    {
        return round(((float) ($this->budget ?? 0)) + $this->gst_amount, 2);
    }

    public function getAudienceCountryIdsAttribute(): array
    {
        $value = trim((string) ($this->audience_countries ?? ''));
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $value))));
    }
}
