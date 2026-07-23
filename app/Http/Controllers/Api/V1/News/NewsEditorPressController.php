<?php

namespace App\Http\Controllers\Api\V1\News;

use App\Models\NewsCategory;
use App\Models\NewsEditor;
use App\Models\NewsPressInvitation;
use App\Models\NewsPressMember;
use App\Models\NewsPressProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NewsEditorPressController extends Controller
{
    /**
     * Live slug availability check for press setup/settings.
     */
    public function checkSlug(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $raw = (string) $request->query('slug', '');
        $slug = NewsPressProfile::normalizeSlug($raw);
        $owned = NewsPressProfile::query()->where('user_id', $userId)->first();

        $available = NewsPressProfile::isSlugAvailable($slug, $owned?->id);
        $reason = null;

        if ($slug === '') {
            $reason = 'Slug is required.';
        } elseif (NewsPressProfile::isReservedSlug($slug)) {
            $reason = 'This slug is reserved.';
        } elseif (!$available) {
            $reason = 'This slug is already taken.';
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'slug' => $slug,
                'suggested' => $slug,
                'available' => $available,
                'reason' => $reason,
                'previewPath' => $slug !== '' ? '/news/press/' . $slug : null,
                'previewUrlHint' => $slug !== '' ? 'ouptel.in/news/press/' . $slug : null,
            ],
        ]);
    }

    /**
     * Upload logo / banner images for press profile.
     */
    public function uploadImages(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:4'],
            'images.*' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
            'type' => ['nullable', 'string', Rule::in(['logo', 'banner'])],
        ]);

        $folder = $request->input('type') === 'banner' ? 'banners' : 'logos';
        $files = $request->file('images', []);
        if (!is_array($files)) {
            $files = [$files];
        }

        $urls = [];
        foreach ($files as $file) {
            if (!$file) {
                continue;
            }
            $filename = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('news/press/' . $folder . '/' . date('Y/m'), $filename, 'public');
            $urls[] = asset('storage/' . ltrim($path, '/'));
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'urls' => $urls,
                'url' => $urls[0] ?? null,
            ],
        ], 201);
    }

    /**
     * Current editor's press profile (null if not set up yet).
     */
    public function me(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $press = $this->findMyPress($userId);

        return response()->json([
            'status' => 'success',
            'data' => $press
                ? $this->formatEditorPress(
                    $press->load(['categories' => fn ($q) => $q->ordered()]),
                    $userId
                )
                : null,
        ]);
    }

    /**
     * First-time press page setup.
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        if ($this->findMyPress($userId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You already belong to a press page. Leave or ask the owner before creating another.',
            ], 422);
        }

        $editor = NewsEditor::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$editor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Editor access required.',
            ], 403);
        }

        $validated = $this->validateProfilePayload($request, null);
        $slug = NewsPressProfile::normalizeSlug($validated['slug']);

        if (!NewsPressProfile::isSlugAvailable($slug)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This slug is already taken or reserved.',
                'errors' => ['slug' => ['This slug is already taken or reserved.']],
            ], 422);
        }

        $press = NewsPressProfile::create([
            'editor_id' => $editor->id,
            'user_id' => (int) $userId,
            'name' => $validated['name'],
            'slug' => $slug,
            'logo' => $validated['logo'] ?? null,
            'banner_image' => $validated['banner_image'] ?? null,
            'tagline' => $validated['tagline'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'social_links' => $validated['social_links'] ?? [],
            'status' => NewsPressProfile::STATUS_ACTIVE,
        ]);

        $press->ensureOwnerMembership();
        $this->syncCategories($press, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Press page created.',
            'data' => $this->formatEditorPress(
                $press->fresh(['categories' => fn ($q) => $q->ordered()]),
                $userId
            ),
        ], 201);
    }

    /**
     * Update press settings (slug change requires confirmation).
     */
    public function update(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $press = $this->requireOwnedPress($userId);
        if ($press instanceof JsonResponse) {
            return $press;
        }

        $validated = $this->validateProfilePayload($request, $press);
        $newSlug = NewsPressProfile::normalizeSlug($validated['slug']);
        $slugChanging = $newSlug !== $press->slug;

        if ($slugChanging) {
            if (!$request->boolean('confirm_slug_change')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Changing your slug will stop your old link from working. Confirm to continue.',
                    'code' => 'slug_change_confirmation_required',
                    'data' => [
                        'oldSlug' => $press->slug,
                        'newSlug' => $newSlug,
                        'oldPath' => $press->publicPath(),
                        'newPath' => '/news/press/' . $newSlug,
                    ],
                ], 422);
            }

            if (!NewsPressProfile::isSlugAvailable($newSlug, $press->id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This slug is already taken or reserved.',
                    'errors' => ['slug' => ['This slug is already taken or reserved.']],
                ], 422);
            }
        }

        $press->update([
            'name' => $validated['name'],
            'slug' => $newSlug,
            'logo' => $validated['logo'] ?? null,
            'banner_image' => array_key_exists('banner_image', $validated)
                ? ($validated['banner_image'] ?? null)
                : $press->banner_image,
            'tagline' => $validated['tagline'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'social_links' => $validated['social_links'] ?? ($press->social_links ?? []),
        ]);

        if (
            array_key_exists('category_ids', $validated)
            || array_key_exists('categories', $validated)
            || array_key_exists('new_categories', $validated)
        ) {
            $this->syncCategories($press, $validated);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Press settings updated.',
            'data' => $this->formatEditorPress(
                $press->fresh(['categories' => fn ($q) => $q->ordered()]),
                $userId
            ),
        ]);
    }

    /**
     * Attach existing category or create a new one for this press.
     */
    public function addCategory(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $press = $this->requireOwnedPress($userId);
        if ($press instanceof JsonResponse) {
            return $press;
        }

        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:news_categories,id'],
            'name' => ['nullable', 'string', 'max:100', 'required_without:category_id'],
        ]);

        $category = null;

        if (!empty($validated['category_id'])) {
            $category = NewsCategory::query()->find($validated['category_id']);
        } else {
            $name = trim($validated['name']);
            $slug = Str::slug($name) ?: 'category-' . time();
            $category = NewsCategory::query()
                ->where(function ($q) use ($name, $slug) {
                    $q->whereRaw('LOWER(name) = ?', [strtolower($name)])
                        ->orWhere('slug', $slug);
                })
                ->first();

            if (!$category) {
                $maxOrder = (int) NewsCategory::query()->max('display_order');
                $category = NewsCategory::create([
                    'name' => $name,
                    'slug' => $this->uniqueCategorySlug($slug),
                    'description' => null,
                    'icon' => null,
                    'color' => '#2563EB',
                    'display_order' => $maxOrder + 1,
                    'status' => true,
                ]);
            }
        }

        $press->categories()->syncWithoutDetaching([$category->id]);

        return response()->json([
            'status' => 'success',
            'message' => 'Category added to your press.',
            'data' => $this->formatEditorPress(
                $press->fresh(['categories' => fn ($q) => $q->ordered()]),
                $userId
            ),
        ]);
    }

    public function removeCategory(Request $request, int $categoryId): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $press = $this->requireOwnedPress($userId);
        if ($press instanceof JsonResponse) {
            return $press;
        }

        $press->categories()->detach($categoryId);

        return response()->json([
            'status' => 'success',
            'message' => 'Category removed from your press.',
            'data' => $this->formatEditorPress(
                $press->fresh(['categories' => fn ($q) => $q->ordered()]),
                $userId
            ),
        ]);
    }

    /**
     * List active editors on this press (owner only).
     */
    public function members(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $press = $this->requireOwnedPress($userId);
        if ($press instanceof JsonResponse) {
            return $press;
        }

        $press->ensureOwnerMembership();

        $members = $press->members()
            ->active()
            ->with(['user', 'editor'])
            ->orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")
            ->orderBy('joined_at')
            ->get()
            ->map(fn (NewsPressMember $m) => $this->formatMember($m))
            ->values()
            ->all();

        $invitations = $press->invitations()
            ->pending()
            ->orderByDesc('created_at')
            ->get()
            ->map(function (NewsPressInvitation $invite) {
                if ($invite->isExpired()) {
                    $invite->update(['status' => NewsPressInvitation::STATUS_EXPIRED]);

                    return null;
                }

                return $this->formatInvitation($invite);
            })
            ->filter()
            ->values()
            ->all();

        return response()->json([
            'status' => 'success',
            'data' => [
                'press' => $this->formatEditorPress(
                    $press->load(['categories' => fn ($q) => $q->ordered()]),
                    $userId
                ),
                'members' => $members,
                'invitations' => $invitations,
            ],
        ]);
    }

    /**
     * Add an existing approved editor, or email-invite someone not registered yet.
     */
    public function addMember(Request $request): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $press = $this->requireOwnedPress($userId);
        if ($press instanceof JsonResponse) {
            return $press;
        }

        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:191'],
        ]);

        $identifier = trim($validated['identifier']);
        $isEmail = (bool) filter_var($identifier, FILTER_VALIDATE_EMAIL);

        $invitee = User::query()
            ->where(function ($q) use ($identifier) {
                $q->whereRaw('LOWER(email) = ?', [strtolower($identifier)])
                    ->orWhereRaw('LOWER(username) = ?', [strtolower($identifier)]);
            })
            ->first();

        if ($invitee && (int) $invitee->user_id === (int) $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already the owner of this press.',
            ], 422);
        }

        $editor = $invitee
            ? NewsEditor::query()
                ->where('user_id', $invitee->user_id)
                ->where('status', 'active')
                ->first()
            : null;

        if ($invitee && $editor) {
            $conflict = $this->membershipConflictMessage($invitee->user_id, $press->id);
            if ($conflict) {
                return response()->json(['status' => 'error', 'message' => $conflict], 422);
            }

            $member = NewsPressMember::query()->updateOrCreate(
                [
                    'press_id' => $press->id,
                    'user_id' => $invitee->user_id,
                ],
                [
                    'editor_id' => $editor->id,
                    'role' => NewsPressMember::ROLE_MEMBER,
                    'status' => NewsPressMember::STATUS_ACTIVE,
                    'invited_by' => (int) $userId,
                    'joined_at' => now(),
                    'removed_at' => null,
                ]
            );

            $member->load(['user', 'editor']);

            return response()->json([
                'status' => 'success',
                'message' => 'Editor added to your press team.',
                'data' => [
                    'type' => 'member',
                    'member' => $this->formatMember($member),
                ],
            ], 201);
        }

        $email = $isEmail
            ? strtolower($identifier)
            : strtolower(trim((string) ($invitee?->email ?? '')));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Enter a valid email address to invite someone who is not an editor yet.',
            ], 422);
        }

        if ($invitee) {
            $conflict = $this->membershipConflictMessage($invitee->user_id, $press->id);
            if ($conflict) {
                return response()->json(['status' => 'error', 'message' => $conflict], 422);
            }
        }

        $pendingSame = NewsPressInvitation::query()
            ->pending()
            ->where('press_id', $press->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();

        $invitation = NewsPressInvitation::issue($press, $email, $userId);
        $sent = $invitation->sendInviteEmail();

        return response()->json([
            'status' => 'success',
            'message' => $sent
                ? ($pendingSame
                    ? 'Invite email resent.'
                    : 'Invite email sent. They can apply and will join this press after approval.')
                : 'Invite created, but the email could not be sent. Check mail settings and resend.',
            'data' => [
                'type' => 'invitation',
                'invitation' => $this->formatInvitation($invitation),
                'emailSent' => $sent,
            ],
        ], 201);
    }

    /**
     * Cancel a pending email invite (owner only).
     */
    public function cancelInvitation(Request $request, int $invitationId): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $press = $this->requireOwnedPress($userId);
        if ($press instanceof JsonResponse) {
            return $press;
        }

        $invitation = $press->invitations()->whereKey($invitationId)->first();
        if (!$invitation || $invitation->status !== NewsPressInvitation::STATUS_PENDING) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pending invite not found.',
            ], 404);
        }

        $invitation->cancel();

        return response()->json([
            'status' => 'success',
            'message' => 'Invite cancelled.',
        ]);
    }

    /**
     * Public invite details for Become Editor page.
     */
    public function showInvite(string $token): JsonResponse
    {
        $invitation = NewsPressInvitation::findValidByToken($token);
        if (!$invitation) {
            return response()->json([
                'status' => 'error',
                'message' => 'This invite is invalid or has expired.',
            ], 404);
        }

        $press = $invitation->press;
        if (!$press || !$press->isActive()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This press page is not available.',
            ], 404);
        }

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($invitation->email)])
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'pressName' => $press->name,
                'pressSlug' => $press->slug,
                'pressLogo' => $press->logo,
                'expiresAt' => optional($invitation->expires_at)?->toIso8601String(),
                'alreadyEditor' => $existingUser
                    ? NewsEditor::isActiveEditor($existingUser->user_id)
                    : false,
            ],
        ]);
    }

    /**
     * Existing approved editor accepts a press invite (auth required).
     */
    public function acceptInvite(Request $request, string $token): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $invitation = NewsPressInvitation::findValidByToken($token);
        if (!$invitation) {
            return response()->json([
                'status' => 'error',
                'message' => 'This invite is invalid or has expired.',
            ], 404);
        }

        $user = User::query()->where('user_id', $userId)->first();
        if (!$user || strtolower((string) $user->email) !== strtolower($invitation->email)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sign in with the invited email address to accept this invite.',
            ], 403);
        }

        $editor = NewsEditor::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$editor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Editor access required.',
            ], 403);
        }

        $conflict = $this->membershipConflictMessage($userId, $invitation->press_id);
        if ($conflict) {
            return response()->json(['status' => 'error', 'message' => $conflict], 422);
        }

        if (!$invitation->fulfillForEditor($user, $editor)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Could not join this press.',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'You joined the press team.',
            'data' => $this->formatEditorPress(
                $invitation->press->fresh(['categories' => fn ($q) => $q->ordered()]),
                $userId
            ),
        ]);
    }

    /**
     * Remove a team member (owner only). Cannot remove the owner.
     */
    public function removeMember(Request $request, int $memberId): JsonResponse
    {
        $userId = $this->requireEditor($request);
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $press = $this->requireOwnedPress($userId);
        if ($press instanceof JsonResponse) {
            return $press;
        }

        $member = $press->members()->whereKey($memberId)->first();
        if (!$member || $member->status !== NewsPressMember::STATUS_ACTIVE) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team member not found.',
            ], 404);
        }

        if ($member->role === NewsPressMember::ROLE_OWNER || (int) $member->user_id === (int) $press->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot remove the press owner.',
            ], 422);
        }

        $member->update([
            'status' => NewsPressMember::STATUS_REMOVED,
            'removed_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Editor removed from your press team.',
        ]);
    }

    protected function validateProfilePayload(Request $request, ?NewsPressProfile $existing): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(NewsPressProfile::RESERVED_SLUGS),
            ],
            'logo' => ['nullable', 'string', 'max:2048'],
            'banner_image' => ['nullable', 'string', 'max:2048'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'social_links' => ['nullable', 'array'],
            'social_links.facebook' => ['nullable', 'string', 'max:512'],
            'social_links.twitter' => ['nullable', 'string', 'max:512'],
            'social_links.instagram' => ['nullable', 'string', 'max:512'],
            'social_links.youtube' => ['nullable', 'string', 'max:512'],
            'social_links.website' => ['nullable', 'string', 'max:512'],
            'category_ids' => [$existing ? 'sometimes' : 'required', 'array', $existing ? 'nullable' : 'min:1'],
            'category_ids.*' => ['integer', 'exists:news_categories,id'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['string', 'max:100'],
            'new_categories' => ['sometimes', 'array'],
            'new_categories.*' => ['string', 'max:100'],
            'confirm_slug_change' => ['sometimes', 'boolean'],
        ]);
    }

    protected function syncCategories(NewsPressProfile $press, array $validated): void
    {
        $ids = [];

        foreach ($validated['category_ids'] ?? [] as $id) {
            $ids[] = (int) $id;
        }

        $names = array_merge(
            $validated['categories'] ?? [],
            $validated['new_categories'] ?? []
        );

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $slug = Str::slug($name) ?: 'category-' . time();
            $category = NewsCategory::query()
                ->where(function ($q) use ($name, $slug) {
                    $q->whereRaw('LOWER(name) = ?', [strtolower($name)])
                        ->orWhere('slug', $slug);
                })
                ->first();

            if (!$category) {
                $maxOrder = (int) NewsCategory::query()->max('display_order');
                $category = NewsCategory::create([
                    'name' => $name,
                    'slug' => $this->uniqueCategorySlug($slug),
                    'description' => null,
                    'icon' => null,
                    'color' => '#2563EB',
                    'display_order' => $maxOrder + 1,
                    'status' => true,
                ]);
            }

            $ids[] = (int) $category->id;
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (!empty($ids)) {
            $press->categories()->sync($ids);
        }
    }

    protected function uniqueCategorySlug(string $base): string
    {
        $slug = $base;
        $i = 1;

        while (NewsCategory::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    protected function formatEditorPress(NewsPressProfile $press, string|int|null $viewerUserId = null): array
    {
        $categories = $press->relationLoaded('categories')
            ? $press->categories
            : $press->categories()->ordered()->get();

        $isOwner = $viewerUserId !== null && $press->isOwnedBy($viewerUserId);
        $membership = $viewerUserId !== null ? $press->membershipFor($viewerUserId) : null;
        $myRole = $membership?->role;
        if (!$myRole && $isOwner) {
            $myRole = NewsPressMember::ROLE_OWNER;
        }
        if ($membership?->isOwner()) {
            $isOwner = true;
        }

        return [
            'id' => $press->id,
            'editorId' => $press->editor_id,
            'userId' => (string) $press->user_id,
            'name' => $press->name,
            'slug' => $press->slug,
            'logo' => $press->logo,
            'bannerImage' => $press->banner_image,
            'tagline' => $press->tagline,
            'contactEmail' => $press->contact_email,
            'socialLinks' => $press->social_links ?? [],
            'status' => $press->status,
            'publicPath' => $press->publicPath(),
            'publicUrlHint' => 'ouptel.in' . $press->publicPath(),
            'myRole' => $myRole,
            'isOwner' => $isOwner,
            'memberCount' => $press->activeMembers()->count(),
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'color' => $c->color,
            ])->values()->all(),
            'articleCount' => $press->articles()->count(),
            'publishedArticleCount' => $press->articles()->published()->count(),
            'createdAt' => optional($press->created_at)?->toIso8601String(),
            'updatedAt' => optional($press->updated_at)?->toIso8601String(),
        ];
    }

    protected function formatMember(NewsPressMember $member): array
    {
        $user = $member->user;

        return [
            'id' => $member->id,
            'userId' => (string) $member->user_id,
            'editorId' => $member->editor_id,
            'role' => $member->role,
            'status' => $member->status,
            'name' => $user?->display_name ?? $user?->username ?? 'Editor',
            'username' => $user?->username,
            'email' => $user?->email,
            'joinedAt' => optional($member->joined_at)?->toIso8601String(),
        ];
    }

    protected function formatInvitation(NewsPressInvitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'status' => $invitation->status,
            'expiresAt' => optional($invitation->expires_at)?->toIso8601String(),
            'createdAt' => optional($invitation->created_at)?->toIso8601String(),
            'invitePath' => '/news/become-editor?invite=' . urlencode($invitation->token),
        ];
    }

    protected function membershipConflictMessage(int|string $targetUserId, int $pressId): ?string
    {
        $existingMembership = NewsPressMember::query()
            ->active()
            ->where('user_id', $targetUserId)
            ->first();

        if ($existingMembership && (int) $existingMembership->press_id === (int) $pressId) {
            return 'This editor is already on your press team.';
        }

        if ($existingMembership) {
            return 'This editor already belongs to another press page.';
        }

        if (NewsPressProfile::query()->where('user_id', $targetUserId)->where('id', '!=', $pressId)->exists()) {
            return 'This editor already owns a press page and cannot join another.';
        }

        return null;
    }

    protected function findMyPress(string|int $userId): ?NewsPressProfile
    {
        return NewsPressProfile::forUser($userId);
    }

    /**
     * Press owned by this user (settings / team / categories).
     */
    protected function requireOwnedPress(string|int $userId): NewsPressProfile|JsonResponse
    {
        $press = NewsPressProfile::query()->where('user_id', $userId)->first();
        if ($press) {
            return $press;
        }

        if (NewsPressProfile::forUser($userId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only the press owner can manage this.',
                'code' => 'owner_required',
            ], 403);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Press profile not found. Complete press setup first.',
        ], 404);
    }

    protected function requireEditor(Request $request): string|JsonResponse
    {
        $userId = $this->resolveTokenUserId($request);
        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        if (!NewsEditor::isActiveEditor($userId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Editor access required.',
            ], 403);
        }

        return (string) $userId;
    }

    protected function resolveTokenUserId(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);

        return DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
    }
}
