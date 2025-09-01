<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory;

    protected $table = 'Wo_Products_Categories';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'lang_key',
    ];

    public function getNameAttribute(): string
    {
        // Map lang_key to actual category names
        $categoryNames = [
            'electronics' => 'Electronics',
            'clothing' => 'Clothing',
            'home' => 'Home & Garden',
            'books' => 'Books',
            'sports' => 'Sports & Outdoors',
            'beauty' => 'Beauty & Health',
            'automotive' => 'Automotive',
            'toys' => 'Toys & Games',
            'food' => 'Food & Beverages',
            'jewelry' => 'Jewelry',
        ];

        return $categoryNames[$this->lang_key] ?? ucfirst($this->lang_key);
    }
}