<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferCategory extends Model
{
    protected $table = 'Wo_OfferCategories';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
    ];

    // Note: active column might not exist in Wo_OfferCategories table

    // Note: Wo_Offers table might not exist
    // public function offers(): HasMany
    // {
    //     return $this->hasMany(Offer::class, 'category_id', 'id');
    // }

    // Mutators to prevent null values for optional fields
    public function setDescriptionAttribute($value)
    {
        $this->attributes['description'] = $value ?: $this->attributes['description'] ?? '';
    }
}
