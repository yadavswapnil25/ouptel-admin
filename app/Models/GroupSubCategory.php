<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupSubCategory extends Model
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
        
        static::addGlobalScope('group', function ($query) {
            $query->where('type', 'group');
        });
    }

    public function getNameAttribute(): string
    {
        // Map lang_key to actual sub-category names
        $subCategoryNames = [
            'startup' => 'Startup',
            'finance' => 'Finance',
            'marketing' => 'Marketing',
            'movies' => 'Movies',
            'music' => 'Music',
            'games' => 'Games',
            'football' => 'Football',
            'basketball' => 'Basketball',
            'tennis' => 'Tennis',
            'mobile' => 'Mobile',
            'software' => 'Software',
            'hardware' => 'Hardware',
            'school' => 'School',
            'university' => 'University',
            'online' => 'Online',
            'fitness' => 'Fitness',
            'medical' => 'Medical',
            'wellness' => 'Wellness',
            'destinations' => 'Destinations',
            'hotels' => 'Hotels',
            'restaurants' => 'Restaurants',
            'recipes' => 'Recipes',
            'fashion' => 'Fashion',
            'beauty' => 'Beauty',
            'local' => 'Local',
            'international' => 'International',
        ];

        return $subCategoryNames[$this->lang_key] ?? ucfirst($this->lang_key);
    }

    public function parent()
    {
        return $this->belongsTo(GroupCategory::class, 'category_id', 'id');
    }
}
