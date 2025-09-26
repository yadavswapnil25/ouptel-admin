<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferApplication extends Model
{
    protected $table = 'Wo_Offer_Apply';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'offer_id',
        'user_id',
        'time',
    ];

    protected $casts = [
        'offer_id' => 'string',
        'user_id' => 'string',
        'time' => 'string',
    ];

    // Note: Wo_Offers table might not exist
    // public function offer(): BelongsTo
    // {
    //     return $this->belongsTo(Offer::class, 'offer_id', 'id');
    // }

    // Note: User relationship might not exist
    // public function user(): BelongsTo
    // {
    //     return $this->belongsTo(User::class, 'user_id', 'user_id');
    // }

    // Mutators
    public function setOfferIdAttribute($value)
    {
        $this->attributes['offer_id'] = (string) $value;
    }

    public function setUserIdAttribute($value)
    {
        $this->attributes['user_id'] = (string) $value;
    }

    public function setTimeAttribute($value)
    {
        if (is_numeric($value)) {
            $this->attributes['time'] = (string) $value;
        } else {
            $this->attributes['time'] = (string) time();
        }
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
