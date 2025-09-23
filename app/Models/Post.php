<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}



