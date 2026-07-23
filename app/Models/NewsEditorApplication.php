<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
        'credentials_sent_at',
        'press_invitation_id',
    ];

    protected $casts = [
        'preferred_categories' => 'array',
        'reviewed_at' => 'datetime',
        'credentials_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    public function pressInvitation(): BelongsTo
    {
        return $this->belongsTo(NewsPressInvitation::class, 'press_invitation_id');
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
            [$user, $plainPassword] = $this->resolveOrCreateUserAccount();

            $this->update([
                'user_id' => $user->user_id,
                'status' => 'approved',
                'reviewed_by' => $adminUserId,
                'reviewed_at' => now(),
                'review_note' => null,
            ]);

            NewsEditor::updateOrCreate(
                ['user_id' => $user->user_id],
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

            $editor = NewsEditor::query()
                ->where('user_id', $user->user_id)
                ->where('status', 'active')
                ->first();

            if ($editor) {
                $this->fulfillLinkedPressInvitation($user, $editor);
            }

            $this->sendCredentialsEmail($user, $plainPassword);

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

    protected function fulfillLinkedPressInvitation(User $user, NewsEditor $editor): void
    {
        $invitation = null;

        if ($this->press_invitation_id) {
            $invitation = NewsPressInvitation::query()->find($this->press_invitation_id);
        }

        if (!$invitation) {
            $invitation = NewsPressInvitation::query()
                ->pending()
                ->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $this->email))])
                ->orderByDesc('id')
                ->first();
        }

        if (!$invitation || $invitation->status !== NewsPressInvitation::STATUS_PENDING) {
            return;
        }

        $invitation->fulfillForEditor($user, $editor, $this->id);
    }

    /**
     * @return array{0: User, 1: ?string} user + plain password when newly created
     */
    protected function resolveOrCreateUserAccount(): array
    {
        if ($this->user_id) {
            $existing = User::query()->where('user_id', $this->user_id)->first();
            if ($existing) {
                return [$existing, null];
            }
        }

        $email = strtolower(trim((string) $this->email));
        $byEmail = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($byEmail) {
            return [$byEmail, null];
        }

        $username = $this->uniqueUsernameFromEmail($email);
        $plainPassword = Str::random(12);
        $nameParts = preg_split('/\s+/', trim((string) $this->full_name), 2);
        $userId = $this->generateUserId();

        // Plain password — User model casts password as hashed.
        $user = User::create([
            'user_id' => $userId,
            'username' => $username,
            'email' => $email,
            'password' => $plainPassword,
            'first_name' => $nameParts[0] ?? '',
            'last_name' => $nameParts[1] ?? '',
            'verified' => '1',
            'active' => '1',
            'avatar' => '',
            'cover' => '',
        ]);

        $now = time();
        DB::table('Wo_Users')
            ->where('user_id', $user->user_id)
            ->update([
                'joined' => $now,
                'registered' => date('n') . '/' . date('Y'),
            ]);

        return [$user->fresh(), $plainPassword];
    }

    protected function sendCredentialsEmail(User $user, ?string $plainPassword): void
    {
        $appName = config('app.name', 'Ouptel');
        $frontend = rtrim((string) (env('FRONTEND_URL') ?: config('app.url')), '/');
        $loginUrl = $frontend . '/login';

        try {
            Mail::send(
                'emails.news-editor-credentials',
                [
                    'appName' => $appName,
                    'name' => $this->full_name ?: ($user->username ?? 'Editor'),
                    'username' => $user->username,
                    'email' => $user->email,
                    'password' => $plainPassword,
                    'loginUrl' => $loginUrl,
                ],
                function ($message) use ($user, $appName) {
                    $message->to($user->email, $user->username)
                        ->subject("{$appName} — Your news editor login details");
                }
            );

            $this->update(['credentials_sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Failed to send news editor credentials email', [
                'application_id' => $this->id,
                'user_id' => $user->user_id,
                'error' => $e->getMessage(),
            ]);
            // Approval still succeeds even if mail fails — admin can resend manually later.
        }
    }

    protected function uniqueUsernameFromEmail(string $email): string
    {
        $base = Str::slug(Str::before($email, '@'), '_');
        $base = preg_replace('/[^a-zA-Z0-9_]/', '', $base) ?: 'editor';
        $base = Str::limit($base, 24, '');
        $username = $base;
        $i = 1;

        while (User::query()->where('username', $username)->exists()) {
            $username = Str::limit($base, 20, '') . '_' . $i;
            $i++;
        }

        return $username;
    }

    protected function generateUserId(): int
    {
        do {
            $userId = random_int(100000, 999999);
        } while (User::query()->where('user_id', $userId)->exists());

        return $userId;
    }
}
