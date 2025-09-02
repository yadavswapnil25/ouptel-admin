<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'Wo_Users';
    protected $primaryKey = 'user_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'avatar',
        'cover',
        'verified',
        'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Mutators to handle ENUM values for verified and active columns
    public function setVerifiedAttribute($value)
    {
        $this->attributes['verified'] = (bool) $value ? '1' : '0';
    }

    public function setActiveAttribute($value)
    {
        $this->attributes['active'] = (bool) $value ? '1' : '0';
    }

    /**
     * Check if user is admin (assuming admin users have email ending with @admin or specific pattern)
     */
    public function isAdmin(): bool
    {
        return str_ends_with($this->email, '@admin') || $this->email === 'admin@ouptel.com';
    }

    /**
     * Check if user is active (assuming all users are active by default)
     */
    public function isActive(): bool
    {
        return true;
    }

    /**
     * Get user's avatar URL
     */
    public function getAvatarUrlAttribute(): string
    {
        return \App\Helpers\ImageHelper::getImageUrl($this->avatar, 'user');
    }

    /**
     * Get user's profile URL
     */
    public function getUrlAttribute(): string
    {
        return url('/user/' . $this->username);
    }

    /**
     * Get user's display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: $this->username ?: $this->email;
    }

    /**
     * Get the user's name for Filament
     */
    public function getNameAttribute(): ?string
    {
        return $this->attributes['username'] ?? $this->attributes['name'] ?? $this->attributes['email'] ?? null;
    }

    /**
     * Get user's full name
     */
    public function getFullNameAttribute(): string
    {
        $firstName = $this->attributes['first_name'] ?? '';
        $lastName = $this->attributes['last_name'] ?? '';
        
        if ($firstName && $lastName) {
            return $firstName . ' ' . $lastName;
        }
        
        return $firstName ?: $lastName ?: $this->username ?: $this->email;
    }

    /**
     * Get user's gender relationship
     */
    public function gender()
    {
        return $this->belongsTo(Gender::class, 'gender', 'gender_id');
    }

    /**
     * Get user's stories
     */
    public function stories()
    {
        return $this->hasMany(UserStory::class, 'user_id', 'user_id');
    }

    /**
     * Get user's verification requests
     */
    public function verificationRequests()
    {
        return $this->hasMany(VerificationRequest::class, 'user_id', 'user_id');
    }

    /**
     * Check if user is online (last seen within 60 seconds)
     */
    public function getIsOnlineAttribute(): bool
    {
        return $this->lastseen && $this->lastseen > (time() - 60);
    }

    /**
     * Get last seen date
     */
    public function getLastSeenDateAttribute(): ?string
    {
        return $this->lastseen ? date('Y-m-d H:i:s', $this->lastseen) : null;
    }

    /**
     * Get joined date
     */
    public function getJoinedDateAttribute(): ?string
    {
        return $this->joined ? date('Y-m-d H:i:s', $this->joined) : null;
    }

    /**
     * Get user status text
     */
    public function getStatusTextAttribute(): string
    {
        switch ($this->active) {
            case '1':
                return 'Active';
            case '0':
                return 'Inactive';
            case '2':
                return 'Banned';
            default:
                return 'Unknown';
        }
    }

    /**
     * Get user type text
     */
    public function getTypeTextAttribute(): string
    {
        switch ($this->admin) {
            case '1':
                return 'Admin';
            case '2':
                return 'Moderator';
            default:
                return 'User';
        }
    }
}