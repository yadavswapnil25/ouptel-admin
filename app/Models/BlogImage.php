<?php

namespace App\Models;

use App\Helpers\ImageHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogImage extends Model
{
    protected $table = 'Wo_Blog_Images';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'blog_id',
        'image',
        'sort_order',
        'created_at',
    ];

    protected $casts = [
        'blog_id' => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'integer',
    ];

    public function blog(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'blog_id', 'id');
    }

    public function getImageUrlAttribute(): string
    {
        if (!empty($this->image)) {
            return ImageHelper::getImageUrl($this->image, 'blog');
        }

        return ImageHelper::getPlaceholder('blog');
    }
}
