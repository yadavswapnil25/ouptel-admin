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
        'gallery_images',
        'tags',
        'seo_meta_title',
        'seo_meta_description',
        'author_id',
        'press_id',
        'author_name',
        'views',
        'shares',
        'featured',
        'breaking',
        'published_at',
        'status',
        'review_feedback',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'views' => 'integer',
        'shares' => 'integer',
        'featured' => 'boolean',
        'breaking' => 'boolean',
        'tags' => 'array',
        'gallery_images' => 'array',
        'published_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
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

    public function press(): BelongsTo
    {
        return $this->belongsTo(NewsPressProfile::class, 'press_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
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

    public function scopePendingReview($query)
    {
        return $query->where('status', 'pending_review');
    }

    public function publishFromReview(?int $adminUserId = null): bool
    {
        if ($this->status !== 'pending_review') {
            return false;
        }

        return (bool) $this->update([
            'status' => 'published',
            'published_at' => $this->published_at ?? now(),
            'review_feedback' => null,
            'reviewed_by' => $adminUserId,
            'reviewed_at' => now(),
        ]);
    }

    public function sendBackFromReview(?string $feedback = null, ?int $adminUserId = null): bool
    {
        if ($this->status !== 'pending_review') {
            return false;
        }

        return (bool) $this->update([
            'status' => 'draft',
            'review_feedback' => $feedback,
            'reviewed_by' => $adminUserId,
            'reviewed_at' => now(),
        ]);
    }

    public function rejectFromReview(?string $feedback = null, ?int $adminUserId = null): bool
    {
        if ($this->status !== 'pending_review') {
            return false;
        }

        return (bool) $this->update([
            'status' => 'rejected',
            'review_feedback' => $feedback,
            'reviewed_by' => $adminUserId,
            'reviewed_at' => now(),
        ]);
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
