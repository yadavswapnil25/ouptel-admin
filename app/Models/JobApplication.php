<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    protected $table = 'Wo_Job_Apply';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'job_id',
        'page_id',
        'user_name',
        'user_email',
        'user_phone',
        'user_resume',
        'user_cover_letter',
        'question_one_answer',
        'question_two_answer',
        'question_three_answer',
        'time',
    ];

    protected $casts = [
        'time' => 'integer',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function job()
    {
        return $this->belongsTo(Job::class, 'job_id', 'id');
    }

    public function page()
    {
        return $this->belongsTo(Page::class, 'page_id', 'page_id');
    }

    // Accessors
    public function getAppliedDateAttribute()
    {
        return $this->time ? date('M d, Y', $this->time) : 'Unknown';
    }
}



