<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model
{
    protected $table = 'Wo_Job';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'title',
        'description',
        'location',
        'status',
        'time',
    ];

    protected $casts = [
        'status' => 'string',
        'time' => 'string',
    ];

    // Note: user_id column might not exist in Wo_Jobs table
    // public function user(): BelongsTo
    // {
    //     return $this->belongsTo(User::class, 'user_id', 'user_id');
    // }

    // Note: Wo_JobApplications table might not exist
    // public function applications(): HasMany
    // {
    //     return $this->hasMany(JobApplication::class, 'job_id', 'id');
    // }

    // Note: Wo_JobCategories table might not exist
    // public function category(): BelongsTo
    // {
    //     return $this->belongsTo(JobCategory::class, 'category_id', 'id');
    // }

    // Mutators to prevent null values for optional fields
    public function setDescriptionAttribute($value)
    {
        $this->attributes['description'] = $value ?: $this->attributes['description'] ?? '';
    }

    // Note: company column doesn't exist in Wo_Job table
    // public function setCompanyAttribute($value)
    // {
    //     $this->attributes['company'] = $value ?: $this->attributes['company'] ?? '';
    // }

    public function setLocationAttribute($value)
    {
        $this->attributes['location'] = $value ?: $this->attributes['location'] ?? '';
    }

    // Note: type column doesn't exist in Wo_Job table
    // public function setTypeAttribute($value)
    // {
    //     $this->attributes['type'] = (string) $value;
    // }

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
    // Note: type column doesn't exist in Wo_Job table
    // public function getTypeAttribute($value)
    // {
    //     $types = [
    //         '1' => 'full-time',
    //         '2' => 'part-time',
    //         '3' => 'contract',
    //         '4' => 'freelance',
    //         '5' => 'internship',
    //     ];
    //     return $types[$value] ?? 'full-time';
    // }

    public function getStatusAttribute($value)
    {
        $statuses = [
            '1' => 'active',
            '2' => 'paused',
            '3' => 'closed',
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

    public function getApplicationsCountAttribute(): int
    {
        // Simplified since Wo_JobApplications table might not exist
        return 0;
    }

    public function getIsAppliedAttribute($userId = null): bool
    {
        // Simplified since user_id and applications table might not exist
        return false;
    }

    /**
     * Get job URL for viewing in frontend
     */
    public function getUrlAttribute(): string
    {
        $baseUrl = config('app.url', 'https://ouptel.com');
        return "{$baseUrl}/jobs/{$this->id}";
    }

    /**
     * Get user_id from either user_id or user column
     * Note: This accessor handles both column names
     */
    public function getUserIdValueAttribute()
    {
        return $this->attributes['user_id'] ?? $this->attributes['user'] ?? null;
    }
}