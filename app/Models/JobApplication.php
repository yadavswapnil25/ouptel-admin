<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Job;
use App\Models\User;

class JobApplication extends Model
{
    protected $table = 'Wo_Job_Apply';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'job_id',
        'user_id',
        'time',
    ];

    protected $casts = [
        'job_id' => 'string',
        'user_id' => 'string',
        'time' => 'string',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // Mutators
    public function setJobIdAttribute($value)
    {
        $this->attributes['job_id'] = (string) $value;
    }

    public function setUserIdAttribute($value)
    {
        $this->attributes['user_id'] = (string) $value;
    }

    // Note: status column doesn't exist in Wo_Job_Apply table
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

    // Accessors
    // Note: status column doesn't exist in Wo_Job_Apply table
    // public function getStatusAttribute($value)
    // {
    //     $statuses = [
    //         '1' => 'pending',
    //         '2' => 'reviewed',
    //         '3' => 'accepted',
    //         '4' => 'rejected',
    //     ];
    //     return $statuses[$value] ?? 'pending';
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
}