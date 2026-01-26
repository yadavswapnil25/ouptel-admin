<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerificationRequest extends Model
{
    use HasFactory;

    protected $table = 'Wo_Verification_Requests';
    protected $primaryKey = 'id';
    public $timestamps = false;

    /**
     * ID Proof types available for verification
     */
    public const ID_PROOF_TYPES = [
        'aadhar' => 'Aadhar Card',
        'voter_id' => 'Voter ID',
        'passport' => 'Passport',
        'driving_license' => 'Driving License',
        'pan_card' => 'PAN Card',
    ];

    /**
     * Badge types available
     */
    public const BADGE_TYPES = [
        'blue' => 'Blue Badge (Regular Users)',
        'golden' => 'Golden Badge (VIPs/Celebrities)',
    ];

    /**
     * Verification statuses
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Rejection reasons
     */
    public const REJECTION_REASONS = [
        'golden_not_for_general' => 'The Golden badge is not for general users.',
        'invalid_document' => 'You have provided invalid document.',
        'unclear_image' => 'Document image is not clear to verify identity.',
        'photo_mismatch' => 'The user could not be verified due to photo mismatch.',
    ];

    protected $fillable = [
        'id',
        'user_id',
        'page_id',
        'message',
        'user_name',
        'passport',
        'photo',
        'type',
        'seen',
        // New fields for badge verification
        'id_proof_type',
        'id_proof_number',
        'id_proof_front_image',
        'id_proof_back_image',
        'badge_type',
        'status',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function page()
    {
        return $this->belongsTo(Page::class, 'page_id', 'page_id');
    }

    public function getTypeNameAttribute(): string
    {
        return $this->type === 'User' ? 'User' : 'Page';
    }

    public function getIsSeenAttribute(): bool
    {
        return $this->seen > 0;
    }

    public function getSeenDateAttribute(): ?string
    {
        return $this->seen ? date('Y-m-d H:i:s', $this->seen) : null;
    }

    public function getNameAttribute(): string
    {
        return $this->user_name;
    }

    public function getTimeAttribute(): ?string
    {
        return $this->seen ? date('Y-m-d H:i:s', $this->seen) : null;
    }

    /**
     * Set passport attribute with default value if null
     */
    public function setPassportAttribute($value): void
    {
        $this->attributes['passport'] = $value ?: '';
    }

    /**
     * Set photo attribute with default value if null
     */
    public function setPhotoAttribute($value): void
    {
        $this->attributes['photo'] = $value ?: '';
    }

    /**
     * Set user_name attribute with default value if null
     */
    public function setUserNameAttribute($value): void
    {
        $this->attributes['user_name'] = $value ?: '';
    }

    /**
     * Set type attribute with default value if null
     */
    public function setTypeAttribute($value): void
    {
        $this->attributes['type'] = $value ?: '';
    }

    /**
     * Set seen attribute with default value if null
     */
    public function setSeenAttribute($value): void
    {
        $this->attributes['seen'] = $value ?: 0;
    }

    /**
     * Get the reviewer (admin) who reviewed this request
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    /**
     * Check if the verification is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the verification is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the verification is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Get the badge type display name
     */
    public function getBadgeTypeNameAttribute(): string
    {
        return self::BADGE_TYPES[$this->badge_type] ?? 'Unknown';
    }

    /**
     * Get the ID proof type display name
     */
    public function getIdProofTypeNameAttribute(): string
    {
        return self::ID_PROOF_TYPES[$this->id_proof_type] ?? 'Unknown';
    }

    /**
     * Get the status display name
     */
    public function getStatusNameAttribute(): string
    {
        return ucfirst($this->status ?? 'Unknown');
    }

    /**
     * Get the rejection reason display text
     */
    public function getRejectionReasonTextAttribute(): ?string
    {
        if (!$this->rejection_reason) {
            return null;
        }
        return self::REJECTION_REASONS[$this->rejection_reason] ?? $this->rejection_reason;
    }

    /**
     * Get the front image URL
     */
    public function getIdProofFrontImageUrlAttribute(): ?string
    {
        if (!$this->id_proof_front_image) {
            return null;
        }
        return asset('storage/' . $this->id_proof_front_image);
    }

    /**
     * Get the back image URL
     */
    public function getIdProofBackImageUrlAttribute(): ?string
    {
        if (!$this->id_proof_back_image) {
            return null;
        }
        return asset('storage/' . $this->id_proof_back_image);
    }

    /**
     * Scope to get pending verifications
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get approved verifications
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope to get rejected verifications
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope to get badge verifications (not page verifications)
     */
    public function scopeBadgeVerifications($query)
    {
        return $query->whereNotNull('badge_type');
    }

    /**
     * Approve the verification request
     * 
     * @param int $adminUserId Admin user who approved
     * @return bool
     */
    public function approve(int $adminUserId): bool
    {
        $this->status = self::STATUS_APPROVED;
        $this->reviewed_at = now();
        $this->reviewed_by = $adminUserId;
        $this->rejection_reason = null;
        
        if ($this->save()) {
            // Update user's verified status
            \Illuminate\Support\Facades\DB::table('Wo_Users')
                ->where('user_id', $this->user_id)
                ->update(['verified' => '1']);
            
            // Send notification to user
            $this->sendVerificationNotification(true);
            
            return true;
        }
        
        return false;
    }

    /**
     * Reject the verification request
     * 
     * @param int $adminUserId Admin user who rejected
     * @param string $reason Rejection reason key
     * @return bool
     */
    public function reject(int $adminUserId, string $reason): bool
    {
        $this->status = self::STATUS_REJECTED;
        $this->reviewed_at = now();
        $this->reviewed_by = $adminUserId;
        $this->rejection_reason = $reason;
        
        if ($this->save()) {
            // Send notification to user
            $this->sendVerificationNotification(false);
            
            return true;
        }
        
        return false;
    }

    /**
     * Send notification to user about verification status
     * 
     * @param bool $approved Whether verification was approved
     * @return void
     */
    protected function sendVerificationNotification(bool $approved): void
    {
        $badgeName = $this->badge_type === 'golden' ? 'golden' : 'blue';
        
        if ($approved) {
            $text = "Your account has been verified. Now you have a {$badgeName} badge.";
        } else {
            $reasonText = self::REJECTION_REASONS[$this->rejection_reason] ?? $this->rejection_reason;
            $text = "Your account verification is unsuccessful because {$reasonText} Kindly resubmit Account Verification.";
        }

        // Insert notification into Wo_Notifications table
        \Illuminate\Support\Facades\DB::table('Wo_Notifications')->insert([
            'notifier_id' => 0, // System notification (0 = admin/system)
            'recipient_id' => $this->user_id,
            'type' => 'verification_result',
            'type2' => $approved ? 'approved' : 'rejected',
            'text' => $text,
            'url' => '/settings/verification',
            'seen' => 0,
            'time' => time(),
        ]);
    }

    /**
     * Get notification message for the verification result
     * 
     * @return string|null
     */
    public function getNotificationMessage(): ?string
    {
        if ($this->status === self::STATUS_PENDING) {
            return null;
        }

        $badgeName = $this->badge_type === 'golden' ? 'golden' : 'blue';
        
        if ($this->status === self::STATUS_APPROVED) {
            return "Your account has been verified. Now you have a {$badgeName} badge.";
        }
        
        $reasonText = self::REJECTION_REASONS[$this->rejection_reason] ?? $this->rejection_reason;
        return "Your account verification is unsuccessful because {$reasonText} Kindly resubmit Account Verification.";
    }
}
