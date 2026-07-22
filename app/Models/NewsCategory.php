<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NewsCategory extends Model
{
    use HasFactory;

    protected $table = 'news_categories';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'display_order',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Articles in this category (many-to-many).
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(
            NewsArticle::class,
            'news_article_category',
            'news_category_id',
            'news_article_id'
        )->withTimestamps();
    }

    /**
     * Published articles in this category.
     */
    public function publishedArticles(): BelongsToMany
    {
        return $this->articles()->published();
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order', 'asc');
    }
}
