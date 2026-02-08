<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumSection extends Model
{
    protected $table = 'Wo_Forum_Sections';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'section_name',
        'description',
    ];

    protected $casts = [
        // No additional casts needed
    ];

    // Mutators to prevent null values
    public function setDescriptionAttribute($value)
    {
        $this->attributes['description'] = $value ?: '';
    }
}

