<?php

namespace App\Models;

use App\Helpers\ImageHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $table = 'Wo_Products';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'page_id',
        'name',
        'description',
        'category',
        'sub_category',
        'price',
        'location',
        'status',
        'type',
        'currency',
        'lng',
        'lat',
        'units',
        'time',
        'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'units' => 'integer',
        'time' => 'integer',
        'status' => 'integer',
        'active' => 'integer',
        'type' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id', 'page_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category', 'id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class, 'product_id', 'id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'product_id', 'id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(UserOrder::class, 'product_id', 'id');
    }

    public function getPriceFormattedAttribute(): string
    {
        return number_format($this->price, 2) . ' ' . $this->currency;
    }

    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            0 => 'Draft',
            1 => 'Active',
            2 => 'Sold Out',
            3 => 'Discontinued',
            default => 'Unknown',
        };
    }

    public function getTypeTextAttribute(): string
    {
        return match ($this->type) {
            0 => 'Physical',
            1 => 'Digital',
            default => 'Unknown',
        };
    }

    public function getActiveTextAttribute(): string
    {
        return $this->active ? 'Active' : 'Inactive';
    }

    public function getPostedDateAttribute(): string
    {
        return date('Y-m-d H:i:s', $this->time);
    }

    public function getMainImageAttribute(): string
    {
        $mainMedia = $this->media()->first();
        if ($mainMedia && !empty($mainMedia->image)) {
            return ImageHelper::getImageUrl($mainMedia->image, 'product');
        }
        return ImageHelper::getPlaceholder('product');
    }

    public function getAverageRatingAttribute(): float
    {
        return $this->reviews()->avg('star') ?? 0;
    }

    public function getReviewsCountAttribute(): int
    {
        return $this->reviews()->count();
    }

    public function getOrdersCountAttribute(): int
    {
        return $this->orders()->count();
    }

    public function getTotalSalesAttribute(): float
    {
        return $this->orders()->sum('final_price');
    }
}



