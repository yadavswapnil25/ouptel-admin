<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class NewsEditorApplication extends Model
{
    use HasFactory;

    protected $table = 'news_editor_applications';

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone',
        'city',
        'state',
        'preferred_categories',
        'bio',
        'portfolio_link',
        'reason',
        'id_proof_name',
        'status',
        'reviewed_by',
        'review_note',
        'reviewed_at',
    ];

    protected $casts = [
        'preferred_categories' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function approve(?int $adminUserId = null): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        return DB::transaction(function () use ($adminUserId) {
            $this->update([
                'status' => 'approved',
                'reviewed_by' => $adminUserId,
                'reviewed_at' => now(),
                'review_note' => null,
            ]);

            NewsEditor::updateOrCreate(
                ['user_id' => $this->user_id],
                [
                    'preferred_categories' => $this->preferred_categories ?? [],
                    'status' => 'active',
                    'approved_by' => $adminUserId,
                    'application_id' => $this->id,
                    'approved_at' => now(),
                    'revoked_at' => null,
                    'revoke_note' => null,
                ]
            );

            return true;
        });
    }

    public function reject(?int $adminUserId = null, ?string $note = null): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        return (bool) $this->update([
            'status' => 'rejected',
            'reviewed_by' => $adminUserId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }
};
