<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogCategory extends Model
{
    use HasFactory;

    protected $table = 'Wo_Blogs_Categories';
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
            'technology' => 'Technology',
            'lifestyle' => 'Lifestyle',
            'business' => 'Business',
            'health' => 'Health',
            'travel' => 'Travel',
            'food' => 'Food',
            'entertainment' => 'Entertainment',
            'sports' => 'Sports',
            'education' => 'Education',
            'news' => 'News',
        ];

        return $categoryNames[$this->lang_key] ?? ucfirst($this->lang_key);
    }
}