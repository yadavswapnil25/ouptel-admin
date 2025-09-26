<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommonThingCategory extends Model
{
    protected $table = 'Wo_CommonThingCategories';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'color',
    ];

    protected $casts = [
        'icon' => 'string',
        'color' => 'string',
    ];

    // Note: Wo_CommonThings table might not exist
    // public function commonThings(): HasMany
    // {
    //     return $this->hasMany(CommonThing::class, 'category_id', 'id');
    // }

    // Mutators
    public function setIconAttribute($value)
    {
        $this->attributes['icon'] = $value ?: $this->attributes['icon'] ?? '';
    }

    public function setColorAttribute($value)
    {
        $this->attributes['color'] = $value ?: $this->attributes['color'] ?? '#007bff';
    }

    // Accessors
    public function getIconAttribute($value)
    {
        return $value ?: 'fas fa-list';
    }

    public function getColorAttribute($value)
    {
        return $value ?: '#007bff';
    }
}
