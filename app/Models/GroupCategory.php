<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupCategory extends Model
{
    use HasFactory;

    protected $table = 'Wo_Groups_Categories';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
    ];

    public function getNameAttribute(): string
    {
        // Map id to actual category names
        $categoryNames = [
            1 => 'Business',
            2 => 'Entertainment',
            3 => 'Sports',
            4 => 'Technology',
            5 => 'Education',
            6 => 'Health',
            7 => 'Travel',
            8 => 'Food',
            9 => 'Lifestyle',
            10 => 'News',
        ];

        return $categoryNames[$this->id] ?? "Category {$this->id}";
    }
}


