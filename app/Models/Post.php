<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Post extends Model
{
    protected $table = 'Wo_Posts';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'user_id',
        'recipient_id',
        'postText',
        'page_id',
        'group_id',
        'event_id',
        'page_event_id',
        'postLink',
        'postLinkTitle',
        'postLinkImage',
        'postLinkContent',
        'postVimeo',
        'postDailymotion',
        'postFacebook',
        'postFile',
        'postFileName',
        'postFileThumb',
        'postYoutube',
        'postVine',
        'postSoundCloud',
        'postPlaytube',
        'postDeepsound',
        'postMap',
        'postShare',
        'postPrivacy',
        'postType',
        'postFeeling',
        'postListening',
        'postTraveling',
        'postWatching',
        'postPlaying',
        'postPhoto',
        'time',
        'registered',
        'album_name',
        'multi_image',
        'multi_image_post',
        'boosted',
        'product_id',
        'poll_id',
        'blog_id',
        'forum_id',
        'thread_id',
        'videoViews',
        'postRecord',
        'postSticker',
        'shared_from',
        'post_url',
        'parent_id',
        'cache',
        'comments_status',
        'blur',
        'color_id',
        'job_id',
        'offer_id',
        'fund_raise_id',
        'fund_id',
        'active',
        'stream_name',
        'live_time',
        'live_ended',
        'agora_resource_id',
        'agora_sid',
        'send_notify',
        'community_preference_id',
    ];

    protected $casts = [
        'time' => 'integer',
        'post_id' => 'integer',
        'user_id' => 'integer',
        'recipient_id' => 'integer',
        'page_id' => 'integer',
        'group_id' => 'integer',
        'event_id' => 'integer',
        'page_event_id' => 'integer',
        'postShare' => 'integer',
        'multi_image_post' => 'integer',
        'boosted' => 'integer',
        'product_id' => 'integer',
        'poll_id' => 'integer',
        'blog_id' => 'integer',
        'forum_id' => 'integer',
        'thread_id' => 'integer',
        'videoViews' => 'integer',
        'shared_from' => 'integer',
        'parent_id' => 'integer',
        'cache' => 'integer',
        'comments_status' => 'integer',
        'blur' => 'integer',
        'color_id' => 'integer',
        'job_id' => 'integer',
        'offer_id' => 'integer',
        'fund_raise_id' => 'integer',
        'fund_id' => 'integer',
        'active' => 'integer',
        'live_time' => 'integer',
        'live_ended' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function communityPreference(): BelongsTo
    {
        return $this->belongsTo(CommunityPreference::class, 'community_preference_id', 'id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id', 'page_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id', 'id');
    }

    public function getPostTypeAttribute($value): string
    {
        if (empty($value)) {
            if (!empty($this->postPhoto)) return 'photo';
            if (!empty($this->postYoutube)) return 'video';
            if (!empty($this->postFile)) return 'file';
            if (!empty($this->postLink)) return 'link';
            if (!empty($this->postMap)) return 'location';
            if (!empty($this->postRecord)) return 'audio';
            if (!empty($this->postSticker)) return 'sticker';
            return 'text';
        }
        return $value;
    }

    public function getPostPrivacyTextAttribute(): string
    {
        return match($this->postPrivacy) {
            '0' => 'Public',
            '1' => 'Friends',
            '2' => 'Only Me',
            '3' => 'Custom',
            '4' => 'Group',
            default => 'Unknown'
        };
    }

    public function getPostTextPreviewAttribute(): string
    {
        if (empty($this->postText)) {
            return 'No text content';
        }
        return strlen($this->postText) > 100 ? substr($this->postText, 0, 100) . '...' : $this->postText;
    }

    public function getPostImageUrlAttribute(): string
    {
        if (!empty($this->postPhoto)) {
            return \App\Helpers\ImageHelper::getImageUrl($this->postPhoto, 'post');
        }
        return \App\Helpers\ImageHelper::getPlaceholder('post');
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->active == 1;
    }

    public function getIsBoostedAttribute(): bool
    {
        return $this->boosted == 1;
    }

    public function getHasMediaAttribute(): bool
    {
        return !empty($this->postPhoto) || 
               !empty($this->postYoutube) || 
               !empty($this->postFile) || 
               !empty($this->postRecord);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(PostReaction::class, 'post_id', 'post_id')
            ->where('comment_id', 0); // Only post reactions, not comment reactions
    }

    public function getReactionCountsAttribute(): array
    {
        $reactions = $this->reactions()
            ->selectRaw('reaction, COUNT(*) as count')
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

    public function getTotalReactionsAttribute(): int
    {
        return array_sum($this->reaction_counts);
    }

    public function getUserReaction(string $userId): ?int
    {
        $reaction = $this->reactions()
            ->where('user_id', $userId)
            ->first();

        return $reaction ? $reaction->reaction : null;
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id', 'post_id')
            ->where('active', 1)
            ->where('parent_id', 0) // Only main comments, not replies
            ->orderBy('time', 'desc');
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id', 'post_id')
            ->where('active', 1)
            ->orderBy('time', 'desc');
    }

    public function getCommentsCountAttribute(): int
    {
        return $this->allComments()->count();
    }

    public function getMainCommentsCountAttribute(): int
    {
        return $this->comments()->count();
    }

    /**
     * Check if post is saved by a specific user
     * 
     * @param string $userId
     * @return bool
     */
    public function isSavedByUser(string $userId): bool
    {
        return DB::table('Wo_SavedPosts')
            ->where('user_id', $userId)
            ->where('post_id', $this->id)
            ->exists();
    }

    /**
     * Get saved post record for a specific user
     * 
     * @param string $userId
     * @return object|null
     */
    public function getSavedRecord(string $userId): ?object
    {
        return DB::table('Wo_SavedPosts')
            ->where('user_id', $userId)
            ->where('post_id', $this->id)
            ->first();
    }

    /**
     * Get when post was saved by user
     * 
     * @param string $userId
     * @return string|null
     */
    public function getSavedAtAttribute(string $userId): ?string
    {
        // Time field doesn't exist in Wo_SavedPosts table
        return null;
    }
}



