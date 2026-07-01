<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDeletionRequest extends Model
{
    protected $table = 'Wo_AccountDeletionRequests';

    public const DELETION_REASONS = [
        'privacy_concerns' => 'Privacy concerns',
        'data_security' => 'Data security issues',
        'not_using' => 'Not using the app anymore',
        'switching_platform' => 'Switching to another platform',
        'performance_issues' => 'Performance or technical issues',
        'content_moderation' => 'Content moderation concerns',
        'personal_reasons' => 'Personal reasons',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
    ];

    protected $fillable = [
        'user_id',
        'deletion_reason',
        'deletion_reason_other',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function getReasonLabelAttribute(): string
    {
        if (!$this->deletion_reason) {
            return '—';
        }

        return self::DELETION_REASONS[$this->deletion_reason] ?? $this->deletion_reason;
    }

    public function getDisplayReasonAttribute(): string
    {
        if ($this->deletion_reason === 'other' && filled($this->deletion_reason_other)) {
            return $this->deletion_reason_other;
        }

        return $this->reason_label;
    }
}
