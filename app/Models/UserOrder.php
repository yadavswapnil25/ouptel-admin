<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOrder extends Model
{
    use HasFactory;

    protected $table = 'Wo_UserOrders';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'hash_id',
        'user_id',
        'product_owner_id',
        'product_id',
        'address_id',
        'price',
        'commission',
        'final_price',
        'units',
        'tracking_url',
        'tracking_id',
        'status',
        'time',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'commission' => 'decimal:2',
        'final_price' => 'decimal:2',
        'units' => 'integer',
        'time' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function productOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'product_owner_id', 'user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function getOrderDateAttribute(): string
    {
        return date('Y-m-d H:i:s', $this->time);
    }

    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            'placed' => 'Placed',
            'confirmed' => 'Confirmed',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'placed' => 'info',
            'confirmed' => 'primary',
            'processing' => 'warning',
            'shipped' => 'success',
            'delivered' => 'success',
            'cancelled' => 'danger',
            'refunded' => 'gray',
            default => 'gray',
        };
    }

    public function getTotalFormattedAttribute(): string
    {
        return number_format($this->final_price, 2) . ' USD';
    }
}



