<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\AdminRole;

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
        'user_id',
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'gender',
        'birthday',
        'country_id',
        'timezone',
        'language',
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
        return $this->attributes['username'] ?? $this->attributes['email'] ?? null;
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
     * Admin roles assigned to this user.
     */
    public function adminRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminRole::class,
            'admin_user_roles',
            'user_id', // FK in pivot pointing to Wo_Users.user_id
            'role_id',
            'user_id', // local key on Wo_Users
            'id'       // local key on admin_roles
        );
    }

    /**
     * Check if this user has a specific admin panel permission.
     * Users with admin == '1' are super admins and always return true.
     */
    public function hasAdminPermission(string $key): bool
    {
        // Super admins bypass all permission checks
        if ($this->admin == '1') {
            return true;
        }

        // Load roles with their permissions and check
        return $this->adminRoles
            ->contains(fn (AdminRole $role) => $role->hasPermission($key));
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

    /**
     * Check if user is verified
     */
    public function isVerified(): bool
    {
        return $this->verified === '1';
    }

    /**
     * Check if user is banned
     */
    public function isBanned(): bool
    {
        return $this->active === '2';
    }

    /**
     * Check if user is inactive
     */
    public function isInactive(): bool
    {
        return $this->active === '0';
    }

    /**
     * Get user's age from birthday
     */
    public function getAgeAttribute(): ?int
    {
        if (empty($this->birthday)) {
            return null;
        }

        $birthday = \Carbon\Carbon::parse($this->birthday);
        return $birthday->age;
    }

    /**
     * Get user's full profile URL
     */
    public function getProfileUrlAttribute(): string
    {
        return url('/user/' . $this->username);
    }

    /**
     * Get user's cover image URL
     */
    public function getCoverUrlAttribute(): string
    {
        return \App\Helpers\ImageHelper::getImageUrl($this->cover, 'cover');
    }

    /**
     * Get user's gender text
     */
    public function getGenderTextAttribute(): string
    {
        switch (strtolower($this->gender)) {
            case 'male':
                return 'Male';
            case 'female':
                return 'Female';
            default:
                return 'Not specified';
        }
    }

    /**
     * Get user's privacy settings
     */
    public function getPrivacySettingsAttribute(): array
    {
        return [
            'follow_privacy' => $this->follow_privacy ?? '1',
            'friend_privacy' => $this->friend_privacy ?? '1',
            'post_privacy' => $this->post_privacy ?? '1',
            'message_privacy' => $this->message_privacy ?? '1',
            'confirm_followers' => $this->confirm_followers ?? '0',
            'show_activities_privacy' => $this->show_activities_privacy ?? '1',
            'birth_privacy' => $this->birth_privacy ?? '0',
            'visit_privacy' => $this->visit_privacy ?? '1',
            'showlastseen' => $this->showlastseen ?? '1',
        ];
    }

    /**
     * Get user's notification settings
     */
    public function getNotificationSettingsAttribute(): array
    {
        return [
            'emailNotification' => $this->emailNotification ?? '1',
            'e_liked' => $this->e_liked ?? '1',
            'e_commented' => $this->e_commented ?? '1',
            'e_shared' => $this->e_shared ?? '1',
            'e_followed' => $this->e_followed ?? '1',
            'e_accepted' => $this->e_accepted ?? '1',
            'e_mentioned' => $this->e_mentioned ?? '1',
            'e_joined_group' => $this->e_joined_group ?? '1',
            'e_liked_page' => $this->e_liked_page ?? '1',
            'e_visited' => $this->e_visited ?? '1',
            'e_liked_post' => $this->e_liked_post ?? '1',
            'e_profile_wall_post' => $this->e_profile_wall_post ?? '1',
            'e_sentme_msg' => $this->e_sentme_msg ?? '1',
        ];
    }

    /**
     * Get user's social media links
     */
    public function getSocialLinksAttribute(): array
    {
        return [
            'website' => $this->website ?? '',
            'facebook' => $this->facebook ?? '',
            'google' => $this->google ?? '',
            'twitter' => $this->twitter ?? '',
            'linkedin' => $this->linkedin ?? '',
            'vk' => $this->vk ?? '',
            'instagram' => $this->instagram ?? '',
            'youtube' => $this->youtube ?? '',
            'pinterest' => $this->pinterest ?? '',
            'tumblr' => $this->tumblr ?? '',
            'flickr' => $this->flickr ?? '',
            'dribbble' => $this->dribbble ?? '',
            'behance' => $this->behance ?? '',
            'github' => $this->github ?? '',
            'stackoverflow' => $this->stackoverflow ?? '',
            'soundcloud' => $this->soundcloud ?? '',
            'medium' => $this->medium ?? '',
        ];
    }

    /**
     * Get user's account statistics
     */
    public function getAccountStatsAttribute(): array
    {
        return [
            'posts_count' => \App\Models\Post::where('user_id', $this->user_id)->where('active', 1)->count(),
            'followers_count' => 0, // Would need to implement followers system
            'following_count' => 0, // Would need to implement following system
            'friends_count' => 0, // Would need to implement friends system
            'groups_count' => 0, // Would need to implement groups system
            'pages_count' => 0, // Would need to implement pages system
            'saved_posts_count' => DB::table('Wo_SavedPosts')->where('user_id', $this->user_id)->count(),
        ];
    }

    /**
     * Get user's complete profile data for API
     */
    public function getProfileDataAttribute(): array
    {
        return [
            'user_id' => $this->user_id,
            'username' => $this->username,
            'email' => $this->email,
            'name' => $this->name,
            'first_name' => $this->first_name ?? '',
            'last_name' => $this->last_name ?? '',
            'about' => $this->about ?? '',
            'avatar_url' => $this->avatar_url,
            'cover_url' => $this->cover_url,
            'gender' => $this->gender,
            'gender_text' => $this->gender_text,
            'birthday' => $this->birthday ?? '',
            'age' => $this->age,
            'country_id' => $this->country_id ?? 0,
            'timezone' => $this->timezone ?? 'UTC',
            'language' => $this->language ?? 'english',
            'verified' => $this->isVerified(),
            'active' => $this->active,
            'status_text' => $this->status_text,
            'type_text' => $this->type_text,
            'is_online' => $this->is_online,
            'last_seen' => $this->last_seen_date,
            'joined_date' => $this->joined_date,
            'profile_url' => $this->profile_url,
            'social_links' => $this->social_links,
            'privacy_settings' => $this->privacy_settings,
            'notification_settings' => $this->notification_settings,
            'account_stats' => $this->account_stats,
        ];
    }

    /**
     * Get user's followers count
     */
    public function getFollowersCountAttribute(): int
    {
        return DB::table('Wo_Followers')
            ->where('following_id', $this->user_id)
            ->where('active', '1')
            ->count();
    }

    /**
     * Get user's following count
     */
    public function getFollowingCountAttribute(): int
    {
        return DB::table('Wo_Followers')
            ->where('follower_id', $this->user_id)
            ->where('active', '1')
            ->count();
    }

    /**
     * Check if user is following another user
     * 
     * @param string $userId
     * @return bool
     */
    public function isFollowing(string $userId): bool
    {
        return DB::table('Wo_Followers')
            ->where('follower_id', $this->user_id)
            ->where('following_id', $userId)
            ->where('active', '1')
            ->exists();
    }

    /**
     * Check if user is followed by another user
     * 
     * @param string $userId
     * @return bool
     */
    public function isFollowedBy(string $userId): bool
    {
        return DB::table('Wo_Followers')
            ->where('follower_id', $userId)
            ->where('following_id', $this->user_id)
            ->where('active', '1')
            ->exists();
    }

    /**
     * Check if follow request is pending
     * 
     * @param string $userId
     * @return bool
     */
    public function hasPendingFollowRequest(string $userId): bool
    {
        return DB::table('Wo_Followers')
            ->where('follower_id', $userId)
            ->where('following_id', $this->user_id)
            ->where('active', '0')
            ->exists();
    }

    /**
     * Check if user can follow another user based on privacy settings
     * 
     * @param string $userId
     * @return bool
     */
    public function canFollow(string $userId): bool
    {
        $targetUser = User::where('user_id', $userId)->first();
        if (!$targetUser) {
            return false;
        }

        // Check if target user is active
        if ($targetUser->active === '0' || $targetUser->active === '2') {
            return false;
        }

        // Check follow privacy
        switch ($targetUser->follow_privacy) {
            case '0': // Everyone can follow
                return true;
            case '1': // Only friends can follow
                return $this->areFriends($targetUser->user_id);
            case '2': // No one can follow
                return false;
            default:
                return true;
        }
    }

    /**
     * Check if two users are friends (mutual follow)
     * 
     * @param string $userId
     * @return bool
     */
    public function areFriends(string $userId): bool
    {
        $user1FollowsUser2 = $this->isFollowing($userId);
        $user2FollowsUser1 = $this->isFollowedBy($userId);
        
        return $user1FollowsUser2 && $user2FollowsUser1;
    }

    /**
     * Get user's followers with pagination
     * 
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getFollowers(int $perPage = 20)
    {
        return DB::table('Wo_Followers as f')
            ->join('Wo_Users as u', 'f.follower_id', '=', 'u.user_id')
            ->where('f.following_id', $this->user_id)
            ->where('f.active', '1')
            ->where('u.active', '1')
            ->select('u.user_id', 'u.username', 'u.first_name', 'u.last_name', 'u.avatar', 'u.verified', 'f.time as followed_at')
            ->orderBy('f.time', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get users that this user is following with pagination
     * 
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getFollowing(int $perPage = 20)
    {
        return DB::table('Wo_Followers as f')
            ->join('Wo_Users as u', 'f.following_id', '=', 'u.user_id')
            ->where('f.follower_id', $this->user_id)
            ->where('f.active', '1')
            ->where('u.active', '1')
            ->select('u.user_id', 'u.username', 'u.first_name', 'u.last_name', 'u.avatar', 'u.verified', 'f.time as followed_at')
            ->orderBy('f.time', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get pending follow requests
     * 
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getFollowRequests(int $perPage = 20)
    {
        return DB::table('Wo_Followers as f')
            ->join('Wo_Users as u', 'f.follower_id', '=', 'u.user_id')
            ->where('f.following_id', $this->user_id)
            ->where('f.active', '0')
            ->where('u.active', '1')
            ->select('u.user_id', 'u.username', 'u.first_name', 'u.last_name', 'u.avatar', 'u.verified', 'f.time as requested_at')
            ->orderBy('f.time', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get mutual followers with another user
     * 
     * @param string $userId
     * @return array
     */
    public function getMutualFollowers(string $userId): array
    {
        return DB::table('Wo_Followers as f1')
            ->join('Wo_Followers as f2', 'f1.follower_id', '=', 'f2.follower_id')
            ->join('Wo_Users as u', 'f1.follower_id', '=', 'u.user_id')
            ->where('f1.following_id', $this->user_id)
            ->where('f2.following_id', $userId)
            ->where('f1.active', '1')
            ->where('f2.active', '1')
            ->where('u.active', '1')
            ->select('u.user_id', 'u.username', 'u.first_name', 'u.last_name', 'u.avatar', 'u.verified')
            ->orderBy('u.username')
            ->get()
            ->toArray();
    }

    /**
     * Get suggested users to follow
     * 
     * @param int $limit
     * @return array
     */
    public function getSuggestedUsers(int $limit = 10): array
    {
        // Get users that are followed by people I follow (friend of friends)
        $suggested = DB::table('Wo_Followers as f1')
            ->join('Wo_Followers as f2', 'f1.following_id', '=', 'f2.follower_id')
            ->join('Wo_Users as u', 'f2.following_id', '=', 'u.user_id')
            ->where('f1.follower_id', $this->user_id)
            ->where('f1.active', '1')
            ->where('f2.active', '1')
            ->where('u.active', '1')
            ->where('u.user_id', '!=', $this->user_id)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('Wo_Followers as f3')
                    ->whereRaw('f3.follower_id = ?', [$this->user_id])
                    ->whereRaw('f3.following_id = u.user_id');
            })
            ->select('u.user_id', 'u.username', 'u.first_name', 'u.last_name', 'u.avatar', 'u.verified')
            ->groupBy('u.user_id', 'u.username', 'u.first_name', 'u.last_name', 'u.avatar', 'u.verified')
            ->orderByRaw('COUNT(f2.following_id) DESC')
            ->limit($limit)
            ->get()
            ->toArray();

        // If not enough suggestions, add random active users
        if (count($suggested) < $limit) {
            $randomUsers = DB::table('Wo_Users')
                ->where('active', '1')
                ->where('user_id', '!=', $this->user_id)
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('Wo_Followers as f')
                        ->whereRaw('f.follower_id = ?', [$this->user_id])
                        ->whereRaw('f.following_id = Wo_Users.user_id');
                })
                ->select('user_id', 'username', 'first_name', 'last_name', 'avatar', 'verified')
                ->inRandomOrder()
                ->limit($limit - count($suggested))
                ->get()
                ->toArray();

            $suggested = array_merge($suggested, $randomUsers);
        }

        return $suggested;
    }

    /**
     * Get follow statistics
     * 
     * @return array
     */
    public function getFollowStatsAttribute(): array
    {
        return [
            'followers_count' => $this->followers_count,
            'following_count' => $this->following_count,
            'mutual_follows' => 0, // Would need to calculate this
            'follow_ratio' => $this->following_count > 0 ? round($this->followers_count / $this->following_count, 2) : 0,
        ];
    }
}