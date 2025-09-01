<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSubCategory extends Model
{
    use HasFactory;

    protected $table = 'Wo_Sub_Categories';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'category_id',
        'lang_key',
        'type',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::addGlobalScope('product', function ($query) {
            $query->where('type', 'product');
        });
    }

    public function getNameAttribute(): string
    {
        // Map lang_key to actual sub-category names
        $subCategoryNames = [
            'smartphones' => 'Smartphones',
            'laptops' => 'Laptops',
            'tablets' => 'Tablets',
            'headphones' => 'Headphones',
            'mens' => "Men's Clothing",
            'womens' => "Women's Clothing",
            'kids' => "Kids' Clothing",
            'furniture' => 'Furniture',
            'decor' => 'Home Decor',
            'kitchen' => 'Kitchen & Dining',
            'fiction' => 'Fiction',
            'non-fiction' => 'Non-Fiction',
            'textbooks' => 'Textbooks',
            'fitness' => 'Fitness Equipment',
            'outdoor' => 'Outdoor Gear',
            'skincare' => 'Skincare',
            'makeup' => 'Makeup',
            'supplements' => 'Supplements',
            'parts' => 'Auto Parts',
            'accessories' => 'Auto Accessories',
            'board' => 'Board Games',
            'video' => 'Video Games',
            'snacks' => 'Snacks',
            'beverages' => 'Beverages',
            'rings' => 'Rings',
            'necklaces' => 'Necklaces',
            'watches' => 'Watches',
        ];

        return $subCategoryNames[$this->lang_key] ?? ucfirst($this->lang_key);
    }

    public function parent()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id', 'id');
    }
}
