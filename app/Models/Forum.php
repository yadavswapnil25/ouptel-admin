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

    // Note: Wo_ForumTopics table doesn't exist
    // public function topics(): HasMany
    // {
    //     return $this->hasMany(ForumTopic::class, 'forum_id', 'id');
    // }

    // Note: Wo_ForumMembers table doesn't exist
    // public function members(): HasMany
    // {
    //     return $this->hasMany(ForumMember::class, 'forum_id', 'id');
    // }

    // Note: privacy and join_privacy accessors removed since columns don't exist

    // Note: time accessors removed since column doesn't exist

    public function getTopicsCountAttribute(): int
    {
        // Simplified since Wo_ForumTopics table doesn't exist
        return 0;
    }

    public function getMembersCountAttribute(): int
    {
        // Simplified since Wo_ForumMembers table doesn't exist
        return 0;
    }

    public function getIsJoinedAttribute($userId = null): bool
    {
        // Simplified since user_id and members table might not exist
        return false;
    }
}
