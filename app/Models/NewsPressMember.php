<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsPressMember extends Model
{
    use HasFactory;

    protected $table = 'news_press_members';

    public const ROLE_OWNER = 'owner';
    public const ROLE_MEMBER = 'member';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REMOVED = 'removed';

    protected $fillable = [
        'press_id',
        'user_id',
        'editor_id',
        'role',
        'status',
        'invited_by',
        'joined_at',
        'removed_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function press(): BelongsTo
    {
        return $this->belongsTo(NewsPressProfile::class, 'press_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(NewsEditor::class, 'editor_id');
    }

    public function invitedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by', 'user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER && $this->status === self::STATUS_ACTIVE;
    }

    public function isActiveMember(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
