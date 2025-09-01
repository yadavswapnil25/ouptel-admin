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
}
