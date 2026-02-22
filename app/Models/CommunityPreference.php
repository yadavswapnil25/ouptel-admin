<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CommunityPreference extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sort_order'];

    protected static function booted(): void
    {
        static::creating(function (CommunityPreference $model) {
            if (empty($model->slug) && !empty($model->name)) {
                $model->slug = Str::slug($model->name);
            }
        });
        static::updating(function (CommunityPreference $model) {
            if ($model->isDirty('name') && empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_community_preferences',
            'preference_id',
            'user_id',
            'id',
            'user_id'
        )->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'community_preference_id', 'id');
    }
}
