<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    use HasFactory;

    protected $table = 'Wo_ProductReview';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'product_id',
        'star',
        'review',
        'time',
    ];

    protected $casts = [
        'star' => 'integer',
        'time' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function getReviewedDateAttribute(): string
    {
        return date('Y-m-d H:i:s', $this->time);
    }

    public function getRatingStarsAttribute(): string
    {
        return str_repeat('★', $this->star) . str_repeat('☆', 5 - $this->star);
    }

    // Accessor for backward compatibility
    public function getRatingAttribute(): int
    {
        return $this->star;
    }

    // Mutator for backward compatibility
    public function setRatingAttribute($value): void
    {
        $this->attributes['star'] = $value;
    }
}



