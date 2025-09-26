<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundingCategory extends Model
{
    protected $table = 'Wo_FundingCategories';
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

    // Note: Wo_Fundings table might not exist
    // public function fundings(): HasMany
    // {
    //     return $this->hasMany(Funding::class, 'category_id', 'id');
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
        return $value ?: 'fas fa-money-bill-wave';
    }

    public function getColorAttribute($value)
    {
        return $value ?: '#007bff';
    }
}
