<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Funding extends Model
{
    protected $table = 'Wo_Funding';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'title',
        'description',
        'amount',
        'user_id',
        'funding_type',
        'target_amount',
        'current_amount',
        'deadline',
        'time',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'category_id' => 'string',
        'user_id' => 'string',
        'status' => 'string',
        'funding_type' => 'string',
        'time' => 'string',
    ];

    // Note: Wo_Users table might not exist
    // public function user(): BelongsTo
    // {
    //     return $this->belongsTo(User::class, 'user_id', 'user_id');
    // }

    // Note: Wo_FundingCategories table might not exist
    // public function category(): BelongsTo
    // {
    //     return $this->belongsTo(FundingCategory::class, 'category_id', 'id');
    // }

    // Mutators
    public function setAmountAttribute($value)
    {
        $this->attributes['amount'] = (float) $value;
    }

    public function setTargetAmountAttribute($value)
    {
        $this->attributes['target_amount'] = (float) $value;
    }

    public function setCurrentAmountAttribute($value)
    {
        $this->attributes['current_amount'] = (float) $value;
    }

    public function setCategoryIdAttribute($value)
    {
        $this->attributes['category_id'] = (string) $value;
    }

    public function setUserIdAttribute($value)
    {
        $this->attributes['user_id'] = (string) $value;
    }

    public function setStatusAttribute($value)
    {
        $this->attributes['status'] = (string) $value;
    }

    public function setFundingTypeAttribute($value)
    {
        $this->attributes['funding_type'] = (string) $value;
    }

    public function setTimeAttribute($value)
    {
        if (is_numeric($value)) {
            $this->attributes['time'] = (string) $value;
        } else {
            $this->attributes['time'] = (string) time();
        }
    }

    // Accessors
    public function getStatusAttribute($value)
    {
        $statuses = [
            '1' => 'active',
            '2' => 'completed',
            '3' => 'cancelled',
            '4' => 'expired',
            '5' => 'pending',
        ];
        return $statuses[$value] ?? 'active';
    }

    public function getFundingTypeAttribute($value)
    {
        $types = [
            '1' => 'donation',
            '2' => 'investment',
            '3' => 'loan',
            '4' => 'crowdfunding',
            '5' => 'grant',
        ];
        return $types[$value] ?? 'donation';
    }

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

    // Calculate funding progress percentage
    public function getProgressPercentageAttribute()
    {
        if ($this->target_amount <= 0) {
            return 0;
        }
        return round(($this->current_amount / $this->target_amount) * 100, 2);
    }

    // Check if funding is fully funded
    public function getIsFullyFundedAttribute()
    {
        return $this->current_amount >= $this->target_amount;
    }

    // Check if funding is expired
    public function getIsExpiredAttribute()
    {
        if (!$this->deadline) {
            return false;
        }
        return strtotime($this->deadline) < time();
    }
}