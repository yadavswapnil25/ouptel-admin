<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NewsPressProfile extends Model
{
    use HasFactory;

    protected $table = 'news_press_profiles';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    /** Path segments that cannot be used as press slugs. */
    public const RESERVED_SLUGS = [
        'article',
        'articles',
        'category',
        'categories',
        'saved',
        'become-editor',
        'editor',
        'admin',
        'press',
        'featured',
        'breaking',
        'me',
        'ads',
        'api',
        'login',
        'register',
    ];

    protected $fillable = [
        'editor_id',
        'user_id',
        'name',
        'slug',
        'logo',
        'banner_image',
        'tagline',
        'contact_email',
        'social_links',
        'status',
        'suspend_reason',
        'suspended_at',
        'suspended_by',
    ];

    protected $casts = [
        'social_links' => 'array',
        'suspended_at' => 'datetime',
    ];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(NewsEditor::class, 'editor_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function suspendedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by', 'user_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            NewsCategory::class,
            'news_press_category',
            'news_press_profile_id',
            'news_category_id'
        )->withTimestamps();
    }

    public function articles(): HasMany
    {
        return $this->hasMany(NewsArticle::class, 'press_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function suspend(?string $reason = null, int|string|null $adminUserId = null): bool
    {
        if ($this->isSuspended()) {
            return false;
        }

        return (bool) $this->update([
            'status' => self::STATUS_SUSPENDED,
            'suspend_reason' => $reason,
            'suspended_at' => now(),
            'suspended_by' => $adminUserId,
        ]);
    }

    public function reactivate(): bool
    {
        if ($this->isActive()) {
            return false;
        }

        return (bool) $this->update([
            'status' => self::STATUS_ACTIVE,
            'suspend_reason' => null,
            'suspended_at' => null,
            'suspended_by' => null,
        ]);
    }

    public function publicPath(): string
    {
        return '/news/press/' . $this->slug;
    }

    public static function normalizeSlug(?string $slug): string
    {
        return Str::slug((string) $slug);
    }

    public static function suggestSlug(string $name): string
    {
        return static::normalizeSlug($name) ?: 'press';
    }

    public static function isReservedSlug(string $slug): bool
    {
        return in_array(strtolower($slug), self::RESERVED_SLUGS, true);
    }

    public static function isSlugAvailable(string $slug, ?int $ignoreId = null): bool
    {
        $slug = static::normalizeSlug($slug);

        if ($slug === '' || static::isReservedSlug($slug)) {
            return false;
        }

        return !static::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists();
    }
}
