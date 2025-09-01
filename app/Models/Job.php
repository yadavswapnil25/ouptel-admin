<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\ImageHelper;

class Job extends Model
{
    protected $table = 'Wo_Job';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'page_id',
        'title',
        'location',
        'lat',
        'lng',
        'minimum',
        'maximum',
        'salary_date',
        'job_type',
        'category',
        'question_one',
        'question_one_type',
        'question_one_answers',
        'question_two',
        'question_two_type',
        'question_two_answers',
        'question_three',
        'question_three_type',
        'question_three_answers',
        'description',
        'image',
        'image_type',
        'currency',
        'status',
        'time',
    ];

    protected $casts = [
        'minimum' => 'float',
        'maximum' => 'float',
        'status' => 'integer',
        'time' => 'integer',
        'lat' => 'float',
        'lng' => 'float',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function page()
    {
        return $this->belongsTo(Page::class, 'page_id', 'page_id');
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class, 'job_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(JobCategory::class, 'category', 'id');
    }

    // Accessors
    public function getImageUrlAttribute()
    {
        if (!empty($this->image)) {
            return ImageHelper::getImageUrl($this->image, 'job');
        }
        return ImageHelper::getPlaceholder('job');
    }

    public function getSalaryRangeAttribute()
    {
        if ($this->minimum && $this->maximum) {
            return '$' . number_format($this->minimum) . ' - $' . number_format($this->maximum);
        } elseif ($this->minimum) {
            return '$' . number_format($this->minimum) . '+';
        } elseif ($this->maximum) {
            return 'Up to $' . number_format($this->maximum);
        }
        return 'Not specified';
    }

    public function getJobTypeTextAttribute()
    {
        $types = [
            'full_time' => 'Full Time',
            'part_time' => 'Part Time',
            'contract' => 'Contract',
            'freelance' => 'Freelance',
            'internship' => 'Internship',
        ];

        return $types[$this->job_type] ?? ucfirst($this->job_type);
    }

    public function getStatusTextAttribute()
    {
        return $this->status ? 'Active' : 'Inactive';
    }

    public function getPostedDateAttribute()
    {
        return $this->time ? date('M d, Y', $this->time) : 'Unknown';
    }

    public function getApplicationsCountAttribute()
    {
        return $this->applications()->count();
    }

    public function getJobUrlAttribute()
    {
        return url("/jobs/{$this->id}");
    }
}
