<?php

namespace App\Models;

use App\Helpers\ImageHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMedia extends Model
{
    use HasFactory;

    protected $table = 'Wo_Products_Media';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'image',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function getImageUrlAttribute(): string
    {
        if (!empty($this->image)) {
            return ImageHelper::getImageUrl($this->image, 'product');
        }
        return ImageHelper::getPlaceholder('product');
    }
}



