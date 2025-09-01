<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageCategory extends Model
{
    use HasFactory;

    protected $table = 'Wo_Pages_Categories';
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
            'business' => 'Business',
            'entertainment' => 'Entertainment',
            'sports' => 'Sports',
            'technology' => 'Technology',
            'education' => 'Education',
            'health' => 'Health',
            'travel' => 'Travel',
            'food' => 'Food',
            'lifestyle' => 'Lifestyle',
            'news' => 'News',
        ];

        return $categoryNames[$this->lang_key] ?? ucfirst($this->lang_key);
    }
}


