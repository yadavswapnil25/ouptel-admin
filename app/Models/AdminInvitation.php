<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminInvitation extends Model
{
    use HasFactory;

    protected $table = 'Wo_Invitation_Links';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'invited_id',
        'code',
        'time',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function invitedUser()
    {
        return $this->belongsTo(User::class, 'invited_id', 'user_id');
    }

    /**
     * Get the invitation URL
     */
    public function getInvitationUrlAttribute(): string
    {
        return config('app.url') . '/register?invite=' . $this->code;
    }

    /**
     * Get formatted posted time
     */
    public function getPostedTimeAttribute(): string
    {
        return $this->time ? date('Y-m-d H:i:s', $this->time) : 'N/A';
    }

    /**
     * Get time elapsed since posted
     */
    public function getTimeElapsedAttribute(): string
    {
        if (!$this->time) {
            return 'N/A';
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

    /**
     * Get status of invitation
     */
    public function getStatusAttribute(): string
    {
        return $this->invited_id > 0 ? 'Used' : 'Available';
    }

    /**
     * Get who used the invitation
     */
    public function getUsedByAttribute()
    {
        return $this->invitedUser ? $this->invitedUser->username : null;
    }

    /**
     * Generate a new invitation code
     */
    public static function generateCode(): string
    {
        return strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    }

    /**
     * Create a new invitation
     */
    public static function createInvitation($userId = null): self
    {
        return static::create([
            'user_id' => $userId ?? auth()->id() ?? 1, // Default to user 1 if no auth
            'invited_id' => 0,
            'code' => static::generateCode(),
            'time' => time(),
        ]);
    }
}

