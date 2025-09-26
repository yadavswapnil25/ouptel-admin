<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommonThing extends Model
{
    protected $table = 'Wo_CommonThings';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'title',
        'description',
        'category_id',
        'user_id',
        'status',
        'time',
    ];

    protected $casts = [
        'category_id' => 'string',
        'user_id' => 'string',
        'status' => 'string',
        'time' => 'string',
    ];

    // Note: Wo_Users table might not exist
    // public function user(): BelongsTo
    // {
    //     return $this->belongsTo(User::class, 'user_id', 'user_id');
    // }

    // Note: Wo_CommonThingCategories table might not exist
    // public function category(): BelongsTo
    // {
    //     return $this->belongsTo(CommonThingCategory::class, 'category_id', 'id');
    // }

    // Mutators
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
            '2' => 'inactive',
            '3' => 'pending',
            '4' => 'rejected',
        ];
        return $statuses[$value] ?? 'active';
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
}
