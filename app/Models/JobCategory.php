<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobCategory extends Model
{
    protected $table = 'Wo_Job_Categories';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
    ];

    // Note: active column might not exist in Wo_JobCategories table

    // Note: Wo_Jobs table might not exist
    // public function jobs(): HasMany
    // {
    //     return $this->hasMany(Job::class, 'category_id', 'id');
    // }

    // Mutators to prevent null values for optional fields
    public function setDescriptionAttribute($value)
    {
        $this->attributes['description'] = $value ?: $this->attributes['description'] ?? '';
    }
}