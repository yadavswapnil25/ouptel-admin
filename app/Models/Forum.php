<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Forum extends Model
{
    protected $table = 'Wo_Forums';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'sections',
        'posts',
        'last_post',
    ];

    protected $casts = [
        // Note: time column doesn't exist in Wo_Forums table
    ];

    // Note: active column doesn't exist in Wo_Forums table

    // Note: privacy and join_privacy columns don't exist in Wo_Forums table

    // Note: time column doesn't exist in Wo_Forums table

    // Mutators to prevent null values for optional fields
    public function setDescriptionAttribute($value)
    {
        $this->attributes['description'] = $value ?: $this->attributes['description'] ?? '';
    }

    // Note: user_id column doesn't exist in Wo_Forums table
    // public function user(): BelongsTo
    // {
    //     return $this->belongsTo(User::class, 'user_id', 'user_id');
    // }

    // Relationship to forum topics/threads
    public function topics(): HasMany
    {
        return $this->hasMany(ForumTopic::class, 'forum', 'id');
    }

    // Relationship to forum members
    public function members(): HasMany
    {
        return $this->hasMany(ForumMember::class, 'forum_id', 'id');
    }

    // Note: privacy and join_privacy accessors removed since columns don't exist

    // Note: time accessors removed since column doesn't exist

    public function getTopicsCountAttribute(): int
    {
        return $this->topics()->where('posted', '>', 0)->count();
    }

    public function getMembersCountAttribute(): int
    {
        return $this->members()->count();
    }

    public function getIsJoinedAttribute($userId = null): bool
    {
        // Simplified since user_id and members table might not exist
        return false;
    }
}
