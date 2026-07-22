<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected $appends = [
        'category_id',
        'category',
    ];

    /**
     * Categories this article belongs to (many-to-many).
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            NewsCategory::class,
            'news_article_category',
            'news_article_id',
            'news_category_id'
        )->withTimestamps();
    }

    /**
     * Backward-compatible primary category (first attached by display order).
     */
    public function getCategoryAttribute(): ?NewsCategory
    {
        if ($this->relationLoaded('categories')) {
            return $this->categories->sortBy('display_order')->values()->first();
        }

        return $this->categories()->ordered()->first();
    }

    /**
     * Backward-compatible primary category id.
     */
    public function getCategoryIdAttribute(): ?int
    {
        $category = $this->category;

        return $category?->id;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(NewsArticleComment::class, 'news_article_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true)->published();
    }

    public function scopeBreaking($query)
    {
        return $query->where('breaking', true)->published();
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->whereHas('categories', function ($q) use ($categoryId) {
            $q->where('news_categories.id', $categoryId);
        });
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('published_at', 'desc');
    }

    public function scopeTrending($query)
    {
        return $query->orderBy('views', 'desc');
    }

    public function scopeRecentTwentyFourHours($query)
    {
        return $query->where('published_at', '>=', now()->subHours(24));
    }
}
