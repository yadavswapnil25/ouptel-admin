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
        'avatar',
        'cover',
        'website',
        'phone',
        'address',
        'page_created',
        'active',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'active' => 'boolean',
        'page_created' => 'datetime',
    ];

    /**
     * Mutator to ensure verified field is properly handled
     */
    public function setVerifiedAttribute($value)
    {
        // Convert boolean to string '0' or '1' for ENUM('0', '1') column
        $this->attributes['verified'] = (bool) $value ? '1' : '0';
    }

    /**
     * Mutator to ensure active field is properly handled
     */
    public function setActiveAttribute($value)
    {
        // Convert boolean to string '0' or '1' for ENUM('0', '1') column
        $this->attributes['active'] = (bool) $value ? '1' : '0';
    }

    /**
     * Mutator to prevent null values for website field
     */
    public function setWebsiteAttribute($value)
    {
        $this->attributes['website'] = $value ?: $this->attributes['website'] ?? '';
    }

    /**
     * Mutator to prevent null values for avatar field
     */
    public function setAvatarAttribute($value)
    {
        $this->attributes['avatar'] = $value ?: $this->attributes['avatar'] ?? '';
    }

    /**
     * Mutator to prevent null values for cover field
     */
    public function setCoverAttribute($value)
    {
        $this->attributes['cover'] = $value ?: $this->attributes['cover'] ?? '';
    }

    /**
     * Mutator to prevent null values for phone field
     */
    public function setPhoneAttribute($value)
    {
        $this->attributes['phone'] = $value ?: $this->attributes['phone'] ?? '';
    }

    /**
     * Mutator to prevent null values for address field
     */
    public function setAddressAttribute($value)
    {
        $this->attributes['address'] = $value ?: $this->attributes['address'] ?? '';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function getAvatarAttribute(): string
    {
        // Access the raw attribute to avoid infinite loop
        $avatarValue = $this->attributes['avatar'] ?? null;
        return \App\Helpers\ImageHelper::getImageUrl($avatarValue, 'page');
    }

    public function getUrlAttribute(): string
    {
        return url('/page/' . $this->page_name);
    }

    public function getCategoryNameAttribute(): string
    {
        if (!$this->page_category) {
            return 'Uncategorized';
        }
        
        // Convert category values to proper names
        return match ($this->page_category) {
            'business' => 'Business',
            'entertainment' => 'Entertainment',
            'education' => 'Education',
            'health' => 'Health',
            'technology' => 'Technology',
            'sports' => 'Sports',
            'news' => 'News',
            'other' => 'Other',
            default => ucfirst($this->page_category),
        };
    }
}


