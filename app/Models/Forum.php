<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

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
    // Note: Wo_ForumMembers table might not exist
    public function members(): HasMany
    {
        return $this->hasMany(ForumMember::class, 'forum_id', 'id');
    }

    // Note: privacy and join_privacy accessors removed since columns don't exist

    // Note: time accessors removed since column doesn't exist

    public function getTopicsCountAttribute(): int
    {
        // Check if Wo_Forum_Threads table exists before querying
        if (!Schema::hasTable('Wo_Forum_Threads')) {
            return 0;
        }
        
        try {
            return $this->topics()->where('posted', '>', 0)->count();
        } catch (\Exception $e) {
            // If table doesn't exist or query fails, return 0
            return 0;
        }
    }

    public function getMembersCountAttribute(): int
    {
        // Check if Wo_ForumMembers table exists before querying
        if (!Schema::hasTable('Wo_ForumMembers')) {
            return 0;
        }
        
        try {
            return $this->members()->count();
        } catch (\Exception $e) {
            // If table doesn't exist or query fails, return 0
            return 0;
        }
    }

    public function getIsJoinedAttribute($userId = null): bool
    {
        // Simplified since user_id and members table might not exist
        return false;
    }
}
