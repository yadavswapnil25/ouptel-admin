<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobCategory extends Model
{
    protected $table = 'Wo_Job_Categories';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'lang_key',
    ];

    // Relationships
    public function jobs()
    {
        return $this->hasMany(Job::class, 'category', 'id');
    }

    // Accessors
    public function getNameAttribute()
    {
        // Map lang_key to actual category names
        $categoryNames = [
            '1580' => 'Technology',
            '1581' => 'Healthcare',
            '1582' => 'Education',
            '1583' => 'Finance',
            '1584' => 'Marketing',
            '1585' => 'Sales',
            '1586' => 'Customer Service',
            '1587' => 'Human Resources',
            '1588' => 'Operations',
            '1589' => 'Engineering',
            '1590' => 'Design',
            '1591' => 'Writing',
            '1592' => 'Legal',
            '1593' => 'Consulting',
            '1594' => 'Retail',
            '1595' => 'Hospitality',
            '1596' => 'Transportation',
            '1597' => 'Manufacturing',
            '1598' => 'Construction',
            '1599' => 'Agriculture',
            '1600' => 'Media',
            '1601' => 'Entertainment',
            '1602' => 'Sports',
            '1603' => 'Other',
        ];

        return $categoryNames[$this->lang_key] ?? "Category {$this->id}";
    }
}
