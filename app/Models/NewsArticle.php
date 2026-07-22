<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsArticle extends Model
{
    use HasFactory;

    protected $table = 'news_articles';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'category_id',
        'featured_image',
        'author_id',
        'author_name',
        'views',
        'shares',
        'featured',
        'breaking',
        'published_at',
        'status',
    ];

    protected $casts = [
        'views' => 'integer',
        'shares' => 'integer',
        'featured' => 'boolean',
        'breaking' => 'boolean',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the category this article belongs to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }

    /**
     * Get the author of this article
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Scope to get published articles
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope to get featured articles
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true)->published();
    }

    /**
     * Scope to get breaking news
     */
    public function scopeBreaking($query)
    {
        return $query->where('breaking', true)->published();
    }

    /**
     * Scope to get articles by category
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope to order by latest
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('published_at', 'desc');
    }

    /**
     * Scope to order by trending (most views)
     */
    public function scopeTrending($query)
    {
        return $query->orderBy('views', 'desc');
    }

    /**
     * Get articles from the last 24 hours
     */
    public function scopeRecentTwentyFourHours($query)
    {
        return $query->where('published_at', '>=', now()->subHours(24));
    }
}
