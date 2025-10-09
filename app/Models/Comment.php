<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    use HasFactory;

    protected $table = 'Wo_Comments';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'page_id',
        'post_id',
        'text',
        'c_file',
        'record',
        'time',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'page_id' => 'integer',
        'post_id' => 'integer',
        'time' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id', 'post_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id', 'page_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id', 'id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    // Note: parent_id field doesn't exist in Wo_Comments table
    // Comment replies are not supported in this version
    // public function parentComment(): BelongsTo
    // {
    //     return $this->belongsTo(Comment::class, 'parent_id', 'id');
    // }

    // public function replies(): HasMany
    // {
    //     return $this->hasMany(Comment::class, 'parent_id', 'id')
    //         ->where('active', 1)
    //         ->orderBy('time', 'asc');
    // }

    public function reactions(): HasMany
    {
        return $this->hasMany(PostReaction::class, 'comment_id', 'id');
    }

    public function getCommentTypeAttribute(): string
    {
        if ($this->post_id) return 'Post';
        if ($this->page_id) return 'Page';
        if ($this->group_id) return 'Group';
        if ($this->event_id) return 'Event';
        return 'Unknown';
    }

    // Note: active and boosted fields don't exist in Wo_Comments table
    // public function getIsActiveAttribute(): bool
    // {
    //     return $this->active == 1;
    // }

    // public function getIsBoostedAttribute(): bool
    // {
    //     return $this->boosted == 1;
    // }

    public function getHasFileAttribute(): bool
    {
        return !empty($this->c_file) || !empty($this->record);
    }

    public function getFileUrlAttribute(): ?string
    {
        if ($this->c_file) {
            return asset('storage/' . $this->c_file);
        }
        if ($this->record) {
            return asset('storage/' . $this->record);
        }
        return null;
    }

    // Note: Reaction count fields don't exist in Wo_Comments table
    // Calculate from Wo_PostReactions table instead
    public function getTotalReactionsAttribute(): int
    {
        return $this->reactions()
            ->where('post_id', 0) // Only comment reactions
            ->count();
    }

    public function getReactionCountsAttribute(): array
    {
        $reactions = $this->reactions()
            ->selectRaw('reaction, COUNT(*) as count')
            ->where('post_id', 0) // Only comment reactions
            ->groupBy('reaction')
            ->get();

        $counts = [
            1 => 0, // Like
            2 => 0, // Love
            3 => 0, // Haha
            4 => 0, // Wow
            5 => 0, // Sad
            6 => 0, // Angry
        ];

        foreach ($reactions as $reaction) {
            $counts[$reaction->reaction] = $reaction->count;
        }

        return $counts;
    }

    public function getHumanTimeAttribute(): string
    {
        $time = time() - $this->time;
        
        if ($time < 60) return 'Just now';
        if ($time < 3600) return floor($time / 60) . 'm';
        if ($time < 86400) return floor($time / 3600) . 'h';
        if ($time < 2592000) return floor($time / 86400) . 'd';
        if ($time < 31536000) return floor($time / 2592000) . 'mo';
        return floor($time / 31536000) . 'y';
    }

    public function getCommentPreviewAttribute(): string
    {
        if (empty($this->text)) {
            return 'No text content';
        }
        return strlen($this->text) > 100 ? substr($this->text, 0, 100) . '...' : $this->text;
    }

    /**
     * Get user's reaction for this comment
     * 
     * @param string $userId
     * @return int|null
     */
    public function getUserReaction(string $userId): ?int
    {
        $reaction = $this->reactions()
            ->where('user_id', $userId)
            ->where('post_id', 0) // Only comment reactions, not post reactions
            ->first();

        return $reaction ? $reaction->reaction : null;
    }

    /**
     * Check if comment has replies
     * Note: parent_id and replies fields don't exist in Wo_Comments table
     * 
     * @return bool
     */
    public function getHasRepliesAttribute(): bool
    {
        return false; // Comment replies not supported
    }

    /**
     * Check if this is a reply to another comment
     * Note: parent_id field doesn't exist in Wo_Comments table
     * 
     * @return bool
     */
    public function getIsReplyAttribute(): bool
    {
        return false; // Comment replies not supported
    }
}
