<?php

namespace App\Models;

use App\Helpers\ImageHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    use HasFactory;

    protected $table = 'Wo_Blog';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user',
        'title',
        'description',
        'content',
        'category',
        'thumbnail',
        'posted',
        'active',
        'view',
        'shared',
        'tags',
    ];

    protected $casts = [
        'posted' => 'integer',
        'active' => 'boolean',
        'view' => 'integer',
        'shared' => 'integer',
    ];

    // Mutator to prevent null values for thumbnail field
    public function setThumbnailAttribute($value)
    {
        $this->attributes['thumbnail'] = $value ?: '';
    }

    // Mutator to convert tags array to string
    public function setTagsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['tags'] = implode(',', array_filter($value));
        } else {
            $this->attributes['tags'] = $value ?: '';
        }
    }

    // Mutator to handle ENUM values for active column
    public function setActiveAttribute($value)
    {
        $this->attributes['active'] = (bool) $value ? '1' : '0';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user', 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category', 'id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class, 'blog_id', 'id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(BlogReaction::class, 'blog_id', 'id');
    }

    public function getThumbnailUrlAttribute()
    {
        if (!empty($this->thumbnail)) {
            return ImageHelper::getImageUrl($this->thumbnail, 'blog');
        }
        return ImageHelper::getPlaceholder('blog');
    }

    public function getUrlAttribute()
    {
        return url("/blog/{$this->id}");
    }

    public function getExcerptAttribute()
    {
        return \Illuminate\Support\Str::limit(strip_tags($this->description), 150);
    }

    public function getPostedDateAttribute()
    {
        return date('Y-m-d H:i:s', $this->posted);
    }

    public function getStatusTextAttribute()
    {
        return $this->active ? 'Published' : 'Draft';
    }

    public function getViewsCountAttribute()
    {
        return $this->view ?? 0;
    }

    public function getSharesCountAttribute()
    {
        return $this->shared ?? 0;
    }

    public function getCommentsCountAttribute()
    {
        return $this->comments()->count();
    }

    public function getReactionsCountAttribute()
    {
        return $this->reactions()->count();
    }
}
