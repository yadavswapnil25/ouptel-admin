<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NewsPressInvitation extends Model
{
    use HasFactory;

    protected $table = 'news_press_invitations';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'press_id',
        'email',
        'token',
        'invited_by',
        'status',
        'application_id',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function press(): BelongsTo
    {
        return $this->belongsTo(NewsPressProfile::class, 'press_id');
    }

    public function invitedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by', 'user_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(NewsEditorApplication::class, 'application_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isValidPending(): bool
    {
        return $this->isPending() && !$this->isExpired();
    }

    public static function findValidByToken(string $token): ?self
    {
        $invite = static::query()->where('token', $token)->first();
        if (!$invite) {
            return null;
        }

        if ($invite->isPending() && $invite->isExpired()) {
            $invite->update(['status' => self::STATUS_EXPIRED]);

            return null;
        }

        return $invite->isValidPending() ? $invite : null;
    }

    public static function issue(NewsPressProfile $press, string $email, int|string $invitedBy): self
    {
        $email = strtolower(trim($email));

        $existing = static::query()
            ->where('press_id', $press->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->pending()
            ->first();

        if ($existing) {
            $existing->update([
                'token' => Str::random(48),
                'invited_by' => (int) $invitedBy,
                'expires_at' => now()->addDays(14),
            ]);

            return $existing->fresh(['press']);
        }

        return static::create([
            'press_id' => $press->id,
            'email' => $email,
            'token' => Str::random(48),
            'invited_by' => (int) $invitedBy,
            'status' => self::STATUS_PENDING,
            'expires_at' => now()->addDays(14),
        ])->fresh(['press']);
    }

    public function inviteUrl(): string
    {
        $frontend = rtrim((string) (env('FRONTEND_URL') ?: config('app.url')), '/');

        return $frontend . '/news/become-editor?invite=' . urlencode($this->token);
    }

    public function sendInviteEmail(): bool
    {
        $press = $this->press ?: $this->press()->first();
        if (!$press) {
            return false;
        }

        $appName = config('app.name', 'Ouptel');
        $inviter = $this->invitedByUser;
        $inviterName = $inviter?->display_name ?: $inviter?->username ?: 'A press owner';

        try {
            Mail::send(
                'emails.news-press-invite',
                [
                    'appName' => $appName,
                    'pressName' => $press->name,
                    'inviterName' => $inviterName,
                    'inviteUrl' => $this->inviteUrl(),
                    'expiresAt' => optional($this->expires_at)?->toDayDateTimeString(),
                ],
                function ($message) use ($appName, $press) {
                    $message->to($this->email)
                        ->subject("{$appName} — Join {$press->name} as a news editor");
                }
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send news press invite email', [
                'invitation_id' => $this->id,
                'email' => $this->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function markAccepted(?int $applicationId = null): void
    {
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'application_id' => $applicationId ?? $this->application_id,
        ]);
    }

    public function cancel(): void
    {
        if (!$this->isPending()) {
            return;
        }

        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Attach an approved editor user to this invitation's press.
     */
    public function fulfillForEditor(User $user, NewsEditor $editor, ?int $applicationId = null): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        if (
            NewsPressMember::query()
                ->active()
                ->where('user_id', $user->user_id)
                ->where('press_id', '!=', $this->press_id)
                ->exists()
        ) {
            return false;
        }

        if (
            NewsPressProfile::query()
                ->where('user_id', $user->user_id)
                ->where('id', '!=', $this->press_id)
                ->exists()
        ) {
            return false;
        }

        NewsPressMember::query()->updateOrCreate(
            [
                'press_id' => $this->press_id,
                'user_id' => $user->user_id,
            ],
            [
                'editor_id' => $editor->id,
                'role' => NewsPressMember::ROLE_MEMBER,
                'status' => NewsPressMember::STATUS_ACTIVE,
                'invited_by' => $this->invited_by,
                'joined_at' => now(),
                'removed_at' => null,
            ]
        );

        $this->markAccepted($applicationId);

        return true;
    }
}
