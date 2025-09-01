<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $table = 'Wo_Reports';
    
    protected $fillable = [
        'post_id',
        'comment_id',
        'profile_id',
        'page_id',
        'group_id',
        'user_id',
        'text',
        'reason',
        'seen',
        'time',
    ];

    public $timestamps = false;

    // Relationships
    public function reporter()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'profile_id', 'user_id');
    }

    public function reportedPost()
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }

    public function reportedComment()
    {
        return $this->belongsTo(Comment::class, 'comment_id', 'id');
    }

    public function reportedPage()
    {
        return $this->belongsTo(Page::class, 'page_id', 'id');
    }

    public function reportedGroup()
    {
        return $this->belongsTo(Group::class, 'group_id', 'id');
    }

    // Accessors
    public function getReportTypeAttribute()
    {
        if ($this->post_id != 0) {
            return 'post';
        } elseif ($this->profile_id != 0) {
            return 'profile';
        } elseif ($this->page_id != 0) {
            return 'page';
        } elseif ($this->group_id != 0) {
            return 'group';
        } elseif ($this->comment_id != 0) {
            return 'comment';
        }
        return 'unknown';
    }

    public function getReportTypeDisplayAttribute()
    {
        return match($this->report_type) {
            'post' => 'Post',
            'profile' => 'User Profile',
            'page' => 'Page',
            'group' => 'Group',
            'comment' => 'Comment',
            default => 'Unknown',
        };
    }

    public function getReportReasonDisplayAttribute()
    {
        return match($this->reason) {
            'r_spam' => 'Spam',
            'r_violence' => 'Violence',
            'r_harassment' => 'Harassment',
            'r_hate' => 'Hate Speech',
            'r_terrorism' => 'Terrorism',
            'r_nudity' => 'Nudity',
            'r_fake' => 'Fake Account',
            'r_other' => 'Other',
            default => ucfirst(str_replace('r_', '', $this->reason)),
        };
    }

    public function getReportedContentAttribute()
    {
        return match($this->report_type) {
            'post' => $this->reportedPost ? $this->reportedPost->postText : 'Post not found',
            'profile' => $this->reportedUser ? $this->reportedUser->username : 'User not found',
            'page' => $this->reportedPage ? $this->reportedPage->page_name : 'Page not found',
            'group' => $this->reportedGroup ? $this->reportedGroup->group_name : 'Group not found',
            'comment' => $this->reportedComment ? $this->reportedComment->text : 'Comment not found',
            default => 'Unknown content',
        };
    }

    public function getReportedContentLinkAttribute()
    {
        return match($this->report_type) {
            'post' => $this->post_id ? "/post/{$this->post_id}" : '#',
            'profile' => $this->profile_id ? "/user/{$this->profile_id}" : '#',
            'page' => $this->page_id ? "/page/{$this->page_id}" : '#',
            'group' => $this->group_id ? "/group/{$this->group_id}" : '#',
            'comment' => $this->comment_id ? "/comment/{$this->comment_id}" : '#',
            default => '#',
        };
    }

    public function getReportedAtAttribute()
    {
        return $this->time ? date('Y-m-d H:i:s', $this->time) : null;
    }

    public function getReportedAtHumanAttribute()
    {
        if (!$this->time) {
            return 'Unknown';
        }

        $time = time() - $this->time;
        
        if ($time < 60) {
            return 'Just now';
        } elseif ($time < 3600) {
            return floor($time / 60) . ' minutes ago';
        } elseif ($time < 86400) {
            return floor($time / 3600) . ' hours ago';
        } elseif ($time < 2592000) {
            return floor($time / 86400) . ' days ago';
        } elseif ($time < 31536000) {
            return floor($time / 2592000) . ' months ago';
        } else {
            return floor($time / 31536000) . ' years ago';
        }
    }

    public function getIsSeenAttribute()
    {
        return $this->seen == 1;
    }

    // Scopes
    public function scopeUnseen($query)
    {
        return $query->where('seen', 0);
    }

    public function scopeSeen($query)
    {
        return $query->where('seen', 1);
    }

    public function scopeByType($query, $type)
    {
        return match($type) {
            'post' => $query->where('post_id', '!=', 0),
            'profile' => $query->where('profile_id', '!=', 0),
            'page' => $query->where('page_id', '!=', 0),
            'group' => $query->where('group_id', '!=', 0),
            'comment' => $query->where('comment_id', '!=', 0),
            default => $query,
        };
    }

    public function scopeByReason($query, $reason)
    {
        return $query->where('reason', $reason);
    }
}

