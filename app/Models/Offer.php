<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    protected $table = 'Wo_Offers';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'description',
        'currency',
        'expire_date',
        'expire_time',
        'time',
    ];

    protected $casts = [
        'time' => 'string',
        'expire_time' => 'datetime',
    ];

    // Note: user_id column might not exist in Wo_Offers table
    // public function user(): BelongsTo
    // {
    //     return $this->belongsTo(User::class, 'user_id', 'user_id');
    // }

    // Note: Wo_OfferApplications table might not exist
    // public function applications(): HasMany
    // {
    //     return $this->hasMany(OfferApplication::class, 'offer_id', 'id');
    // }

    // Note: Wo_OfferCategories table might not exist
    // public function category(): BelongsTo
    // {
    //     return $this->belongsTo(OfferCategory::class, 'category_id', 'id');
    // }

    // Mutators to prevent null values for optional fields
    public function setDescriptionAttribute($value)
    {
        $this->attributes['description'] = $value ?: $this->attributes['description'] ?? '';
    }

    public function setLocationAttribute($value)
    {
        $this->attributes['location'] = $value ?: $this->attributes['location'] ?? '';
    }

    public function setCurrencyAttribute($value)
    {
        $this->attributes['currency'] = $value ?: $this->attributes['currency'] ?? 'USD';
    }

    // Note: status column doesn't exist in Wo_Offers table
    // public function setStatusAttribute($value)
    // {
    //     $this->attributes['status'] = (string) $value;
    // }

    public function setTimeAttribute($value)
    {
        if (is_numeric($value)) {
            $this->attributes['time'] = (string) $value;
        } else {
            $this->attributes['time'] = (string) time();
        }
    }

    public function setExpireTimeAttribute($value)
    {
        if (is_numeric($value)) {
            // Convert Unix timestamp to datetime format
            $this->attributes['expire_time'] = date('Y-m-d H:i:s', (int) $value);
        } else {
            // If it's already a datetime string, use it as is
            $this->attributes['expire_time'] = $value ?: date('Y-m-d H:i:s', strtotime('+30 days'));
        }
    }

    // Accessors
    // Note: status column doesn't exist in Wo_Offers table
    // public function getStatusAttribute($value)
    // {
    //     $statuses = [
    //         '1' => 'active',
    //         '2' => 'paused',
    //         '3' => 'closed',
    //     ];
    //     return $statuses[$value] ?? 'active';
    // }

    public function getTimeAttribute($value)
    {
        if (is_numeric($value)) {
            return \Carbon\Carbon::createFromTimestamp((int) $value);
        }
        return $value;
    }

    // Get time as Unix timestamp (for database operations)
    public function getTimeAsTimestampAttribute()
    {
        $time = $this->attributes['time'] ?? null;
        if (is_numeric($time)) {
            return (int) $time;
        }
        return time();
    }

    public function getExpireTimeAttribute($value)
    {
        // expire_time is stored as datetime, so return it as Carbon instance
        return $value;
    }

    // Get expire_time as Unix timestamp (for API responses)
    public function getExpireTimeAsTimestampAttribute()
    {
        $expireTime = $this->attributes['expire_time'] ?? null;
        if ($expireTime) {
            return strtotime($expireTime);
        }
        return strtotime('+30 days');
    }

    public function getApplicationsCountAttribute(): int
    {
        // Simplified since Wo_OfferApplications table might not exist
        return 0;
    }

    public function getIsAppliedAttribute($userId = null): bool
    {
        // Simplified since user_id and applications table might not exist
        return false;
    }
}
