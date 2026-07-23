<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NewsEditor extends Model
{
    use HasFactory;

    protected $table = 'news_editors';

    protected $fillable = [
        'user_id',
        'preferred_categories',
        'status',
        'approved_by',
        'application_id',
        'approved_at',
        'revoked_at',
        'revoke_note',
    ];

    protected $casts = [
        'preferred_categories' => 'array',
        'approved_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'user_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(NewsEditorApplication::class, 'application_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(NewsArticle::class, 'author_id', 'user_id');
    }

    public function pressProfile(): HasOne
    {
        return $this->hasOne(NewsPressProfile::class, 'editor_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function revoke(?string $note = null): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        return (bool) $this->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoke_note' => $note,
        ]);
    }

    public static function isActiveEditor(int|string|null $userId): bool
    {
        if (!$userId) {
            return false;
        }

        return static::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }
};
