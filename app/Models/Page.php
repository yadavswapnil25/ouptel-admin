<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    protected $table = 'Wo_Pages';
    protected $primaryKey = 'page_id';
    public $timestamps = false;

    protected $fillable = [
        'page_name',
        'page_title',
        'page_description',
        'page_category',
        'user_id',
        'verified',
        'page_avatar',
        'page_cover',
        'page_website',
        'page_phone',
        'page_address',
        'page_about',
        'page_created',
        'active',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'active' => 'boolean',
        'page_created' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function getAvatarAttribute(): string
    {
        return \App\Helpers\ImageHelper::getImageUrl($this->page_avatar, 'page');
    }

    public function getUrlAttribute(): string
    {
        return url('/page/' . $this->page_name);
    }

    public function getCategoryNameAttribute(): string
    {
        return $this->page_category ?: 'Uncategorized';
    }
}


