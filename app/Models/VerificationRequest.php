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
}
