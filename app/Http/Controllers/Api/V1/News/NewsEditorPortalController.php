<?php

namespace App\Http\Controllers\Api\V1\News;

use App\Models\NewsEditor;
use App\Models\NewsEditorApplication;
use App\Models\NewsPressInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NewsEditorPortalController extends Controller
{
    /**
     * Current user's news-portal role (reader | editor).
     */
    public function me(Request $request): JsonResponse
    {
        $userId = $this->resolveTokenUserId($request);
        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $editor = NewsEditor::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        $application = NewsEditorApplication::query()
            ->where(function ($q) use ($userId, $request) {
                $q->where('user_id', $userId);
                $email = User::query()->where('user_id', $userId)->value('email');
                if ($email) {
                    $q->orWhereRaw('LOWER(email) = ?', [strtolower($email)]);
                }
            })
            ->orderByDesc('id')
            ->first();

        $user = User::find($userId);

        return response()->json([
            'status' => 'success',
            'data' => [
                'user_id' => (string) $userId,
                'role' => $editor ? 'editor' : 'reader',
                'name' => $user?->name
                    ?? (trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')) ?: ($user?->username ?? '')),
                'email' => $user?->email ?? '',
                'preferred_categories' => $editor?->preferred_categories ?? [],
                'application' => $application ? $this->formatApplication($application) : null,
            ],
        ]);
    }

    public function myApplication(Request $request): JsonResponse
    {
        $userId = $this->resolveTokenUserId($request);
        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $email = User::query()->where('user_id', $userId)->value('email');

        $application = NewsEditorApplication::query()
            ->where(function ($q) use ($userId, $email) {
                $q->where('user_id', $userId);
                if ($email) {
                    $q->orWhereRaw('LOWER(email) = ?', [strtolower($email)]);
                }
            })
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => $application ? $this->formatApplication($application) : null,
        ]);
    }

    /**
     * Guest-friendly application submit (auth optional).
     * Logged-in users are linked by user_id; guests submit with email only.
     */
    public function submitApplication(Request $request): JsonResponse
    {
        $userId = $this->resolveTokenUserId($request);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['required', 'string', 'max:50'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'invite_token' => ['nullable', 'string', 'max:64'],
            'preferred_categories' => [
                Rule::requiredIf(fn () => blank($request->input('invite_token'))),
                'nullable',
                'array',
            ],
            'preferred_categories.*' => ['string', 'max:100'],
            'bio' => ['required', 'string', 'min:20', 'max:2000'],
            'portfolio_link' => ['nullable', 'url', 'max:512'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'id_proof_name' => ['nullable', 'string', 'max:255'],
        ]);

        if (blank($request->input('invite_token')) && count($validated['preferred_categories'] ?? []) < 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Select at least one preferred category.',
                'errors' => ['preferred_categories' => ['Select at least one preferred category.']],
            ], 422);
        }

        $email = strtolower(trim($validated['email']));
        $invitation = null;

        if (!empty($validated['invite_token'])) {
            $invitation = NewsPressInvitation::findValidByToken($validated['invite_token']);
            if (!$invitation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This press invite is invalid or has expired.',
                ], 422);
            }

            if (strtolower($invitation->email) !== $email) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Use the invited email address (' . $invitation->email . ') for this application.',
                    'errors' => ['email' => ['Email must match the press invite.']],
                ], 422);
            }
        }

        if ($userId && NewsEditor::isActiveEditor($userId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already an editor.',
            ], 422);
        }

        $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existingUser && NewsEditor::isActiveEditor($existingUser->user_id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'An editor account already exists for this email. Please log in.',
            ], 422);
        }

        $pendingQuery = NewsEditorApplication::query()->where('status', 'pending');
        $pendingQuery->where(function ($q) use ($email, $userId, $existingUser) {
            $q->whereRaw('LOWER(email) = ?', [$email]);
            if ($userId) {
                $q->orWhere('user_id', $userId);
            }
            if ($existingUser) {
                $q->orWhere('user_id', $existingUser->user_id);
            }
        });

        if ($pendingQuery->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'An application with this email is already under review.',
            ], 422);
        }

        $linkedUserId = $userId ? (int) $userId : ($existingUser?->user_id);

        $application = NewsEditorApplication::create([
            'user_id' => $linkedUserId,
            'full_name' => $validated['full_name'],
            'email' => $email,
            'phone' => $validated['phone'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'preferred_categories' => $invitation
                ? ($validated['preferred_categories'] ?? [])
                : ($validated['preferred_categories'] ?? []),
            'bio' => $validated['bio'],
            'portfolio_link' => $validated['portfolio_link'] ?? null,
            'reason' => $validated['reason'],
            'id_proof_name' => $validated['id_proof_name'] ?? null,
            'status' => 'pending',
            'press_invitation_id' => $invitation?->id,
        ]);

        if ($invitation) {
            $invitation->update(['application_id' => $application->id]);
        }

        return response()->json([
            'status' => 'success',
            'message' => $invitation
                ? 'Application submitted. After approval you will join ' . ($invitation->press?->name ?: 'the press') . ' and receive login details by email.'
                : ($userId
                    ? 'Application submitted for review.'
                    : 'Application submitted. After approval you will receive login details by email.'),
            'data' => $this->formatApplication($application),
        ], 201);
    }

    protected function formatApplication(NewsEditorApplication $app): array
    {
        return [
            'id' => $app->id,
            'userId' => $app->user_id !== null ? (string) $app->user_id : null,
            'fullName' => $app->full_name,
            'email' => $app->email,
            'phone' => $app->phone,
            'city' => $app->city,
            'state' => $app->state,
            'categories' => $app->preferred_categories ?? [],
            'bio' => $app->bio,
            'portfolioLink' => $app->portfolio_link,
            'reason' => $app->reason,
            'idProofName' => $app->id_proof_name,
            'status' => $app->status,
            'reviewNote' => $app->review_note,
            'appliedAt' => optional($app->created_at)?->toIso8601String(),
            'reviewedAt' => optional($app->reviewed_at)?->toIso8601String(),
            'credentialsSentAt' => optional($app->credentials_sent_at)?->toIso8601String(),
        ];
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
