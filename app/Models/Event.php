<?php

namespace App\Models;

use App\Helpers\ImageHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Event extends Model
{
    use HasFactory;

    protected $table = 'Wo_Events';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'location',
        'description',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'poster_id',
        'cover',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /**
     * Mutator to prevent null values for cover field
     */
    public function setCoverAttribute($value)
    {
        $this->attributes['cover'] = $value ?: $this->attributes['cover'] ?? '';
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'poster_id', 'user_id');
    }

    public function going(): HasMany
    {
        return $this->hasMany(EventGoing::class, 'event_id', 'id');
    }

    public function interested(): HasMany
    {
        return $this->hasMany(EventInterested::class, 'event_id', 'id');
    }

    public function invited(): HasMany
    {
        return $this->hasMany(EventInvited::class, 'event_id', 'id');
    }

    // Note: Event comments and reactions tables don't exist in the database
    // public function comments(): HasMany
    // {
    //     return $this->hasMany(EventComment::class, 'event_id', 'id');
    // }

    // public function reactions(): HasMany
    // {
    //     return $this->hasMany(EventReaction::class, 'event_id', 'id');
    // }

    public function getCoverUrlAttribute()
    {
        if (!empty($this->cover)) {
            return ImageHelper::getImageUrl($this->cover, 'event');
        }
        return ImageHelper::getPlaceholder('event');
    }

    public function getStartDateTimeAttribute()
    {
        return Carbon::parse($this->start_date . ' ' . $this->start_time);
    }

    public function getEndDateTimeAttribute()
    {
        return Carbon::parse($this->end_date . ' ' . $this->end_time);
    }

    public function getIsUpcomingAttribute()
    {
        return $this->start_date > now()->toDateString();
    }

    public function getIsPastAttribute()
    {
        return $this->end_date < now()->toDateString();
    }

    public function getIsOngoingAttribute()
    {
        $now = now();
        return $this->start_date <= $now->toDateString() && $this->end_date >= $now->toDateString();
    }

    public function getStatusTextAttribute()
    {
        if ($this->is_upcoming) {
            return 'Upcoming';
        } elseif ($this->is_ongoing) {
            return 'Ongoing';
        } else {
            return 'Past';
        }
    }

    public function getGoingCountAttribute(): int
    {
        return $this->going()->count();
    }

    public function getInterestedCountAttribute(): int
    {
        return $this->interested()->count();
    }

    public function getInvitedCountAttribute(): int
    {
        return $this->invited()->count();
    }

    public function getCommentsCountAttribute(): int
    {
        // Event comments table doesn't exist
        return 0;
    }

    public function getReactionsCountAttribute(): int
    {
        // Event reactions table doesn't exist
        return 0;
    }

    public function getLocationShortAttribute()
    {
        return \Illuminate\Support\Str::limit($this->location, 50);
    }

    public function getDescriptionShortAttribute()
    {
        return \Illuminate\Support\Str::limit(strip_tags($this->description), 100);
    }
}
