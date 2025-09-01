<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    use HasFactory;

    protected $table = 'Wo_Config';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'value',
    ];

    // Store-specific settings keys (using existing Wo_Config keys)
    const STORE_ENABLED = 'store_system';
    const COMMISSION_RATE = 'store_commission';
    const REVIEW_SYSTEM = 'store_review_system';
    const PRODUCT_VISIBILITY = 'product_visibility';
    const ORDER_POSTS_BY = 'order_posts_by';
    const MARKET_REQUEST = 'market_request';
    const NEARBY_SHOP_SYSTEM = 'nearby_shop_system';
    
    // Additional store settings (will be created if not exist)
    const CURRENCY = 'store_currency';
    const MIN_ORDER_AMOUNT = 'store_min_order_amount';
    const MAX_ORDER_AMOUNT = 'store_max_order_amount';
    const SHIPPING_ENABLED = 'store_shipping_enabled';
    const SHIPPING_COST = 'store_shipping_cost';
    const FREE_SHIPPING_THRESHOLD = 'store_free_shipping_threshold';
    const PAYMENT_METHODS = 'store_payment_methods';
    const REFUND_POLICY = 'store_refund_policy';
    const TERMS_CONDITIONS = 'store_terms_conditions';

    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('name', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function setValue(string $key, $value): void
    {
        static::updateOrCreate(
            ['name' => $key],
            ['value' => $value]
        );
    }

    public static function getStoreSettings(): array
    {
        $settings = static::where('name', 'like', 'store_%')->get();
        $result = [];
        
        foreach ($settings as $setting) {
            $result[$setting->name] = $setting->value;
        }
        
        return $result;
    }
}
