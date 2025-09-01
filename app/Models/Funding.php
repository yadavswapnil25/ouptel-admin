<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Funding extends Model
{
    protected $table = 'Wo_Funding';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'hashed_id',
        'title',
        'description',
        'amount',
        'user_id',
        'image',
        'time',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'user_id' => 'integer',
        'time' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function donations(): HasMany
    {
        return $this->hasMany(FundingRaise::class, 'funding_id', 'id');
    }

    public function getImageUrlAttribute(): string
    {
        if (!empty($this->image)) {
            return \App\Helpers\ImageHelper::getImageUrl($this->image, 'funding');
        }
        return \App\Helpers\ImageHelper::getPlaceholder('funding');
    }

    public function getTotalRaisedAttribute(): float
    {
        return $this->donations()->sum('amount');
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->amount <= 0) {
            return 0;
        }
        return min(100, ($this->total_raised / $this->amount) * 100);
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->total_raised >= $this->amount;
    }

    public function getDonationCountAttribute(): int
    {
        return $this->donations()->count();
    }

    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }

    public function getFormattedTotalRaisedAttribute(): string
    {
        return '$' . number_format($this->total_raised, 2);
    }

    public function getDescriptionPreviewAttribute(): string
    {
        if (empty($this->description)) {
            return 'No description';
        }
        return strlen($this->description) > 100 ? substr($this->description, 0, 100) . '...' : $this->description;
    }
}



