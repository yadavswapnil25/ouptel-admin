<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundingRaise extends Model
{
    protected $table = 'Wo_Funding_Raise';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'funding_id',
        'user_id',
        'amount',
        'time',
    ];

    protected $casts = [
        'funding_id' => 'integer',
        'user_id' => 'integer',
        'amount' => 'decimal:2',
        'time' => 'datetime',
    ];

    public function funding(): BelongsTo
    {
        return $this->belongsTo(Funding::class, 'funding_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }
}



