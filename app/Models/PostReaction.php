<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostReaction extends Model
{
    use HasFactory;

    protected $table = 'Wo_Reactions';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'post_id',
        'comment_id',
        'replay_id',
        'message_id',
        'story_id',
        'reaction',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id', 'post_id');
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class, 'comment_id', 'id');
    }

    public function story()
    {
        return $this->belongsTo(UserStory::class, 'story_id', 'id');
    }

    public function getReactionNameAttribute(): string
    {
        $reactionNames = [
            1 => 'Like',
            2 => 'Love',
            3 => 'Haha',
            4 => 'Wow',
            5 => 'Sad',
            6 => 'Angry',
        ];

        return $reactionNames[$this->reaction] ?? "Reaction {$this->reaction}";
    }

    public function getReactionIconAttribute(): string
    {
        $reactionIcons = [
            1 => '👍',
            2 => '❤️',
            3 => '😂',
            4 => '😮',
            5 => '😢',
            6 => '😠',
        ];

        return $reactionIcons[$this->reaction] ?? '👍';
    }

    public function getContentTypeAttribute(): string
    {
        if ($this->post_id) {
            return 'Post';
        } elseif ($this->comment_id) {
            return 'Comment';
        } elseif ($this->story_id) {
            return 'Story';
        } elseif ($this->message_id) {
            return 'Message';
        }

        return 'Unknown';
    }

    public function getContentIdAttribute(): ?int
    {
        return $this->post_id ?? $this->comment_id ?? $this->story_id ?? $this->message_id;
    }

    /**
     * Set user_id attribute with default value if null
     */
    public function setUserIdAttribute($value): void
    {
        $this->attributes['user_id'] = $value ?: 0;
    }

    /**
     * Set message_id attribute with default value if null
     */
    public function setMessageIdAttribute($value): void
    {
        $this->attributes['message_id'] = $value ?: 0;
    }

    /**
     * Set story_id attribute with default value if null
     */
    public function setStoryIdAttribute($value): void
    {
        $this->attributes['story_id'] = $value ?: 0;
    }

    /**
     * Set post_id attribute with default value if null
     */
    public function setPostIdAttribute($value): void
    {
        $this->attributes['post_id'] = $value ?: 0;
    }

    /**
     * Set comment_id attribute with default value if null
     */
    public function setCommentIdAttribute($value): void
    {
        $this->attributes['comment_id'] = $value ?: 0;
    }
}
