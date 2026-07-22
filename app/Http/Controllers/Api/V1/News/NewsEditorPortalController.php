<?php

namespace App\Http\Controllers\Api\V1\News;

use App\Models\NewsEditor;
use App\Models\NewsEditorApplication;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

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
            ->where('user_id', $userId)
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

        $application = NewsEditorApplication::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => $application ? $this->formatApplication($application) : null,
        ]);
    }

    public function submitApplication(Request $request): JsonResponse
    {
        $userId = $this->resolveTokenUserId($request);
        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        if (NewsEditor::isActiveEditor($userId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already an editor.',
            ], 422);
        }

        $pending = NewsEditorApplication::query()
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            return response()->json([
                'status' => 'error',
                'message' => 'You already have a pending application under review.',
            ], 422);
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['required', 'string', 'max:50'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'preferred_categories' => ['required', 'array', 'min:1'],
            'preferred_categories.*' => ['string', 'max:100'],
            'bio' => ['required', 'string', 'min:20', 'max:2000'],
            'portfolio_link' => ['nullable', 'url', 'max:512'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'id_proof_name' => ['nullable', 'string', 'max:255'],
        ]);

        $application = NewsEditorApplication::create([
            'user_id' => $userId,
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'preferred_categories' => $validated['preferred_categories'],
            'bio' => $validated['bio'],
            'portfolio_link' => $validated['portfolio_link'] ?? null,
            'reason' => $validated['reason'],
            'id_proof_name' => $validated['id_proof_name'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Application submitted for review.',
            'data' => $this->formatApplication($application),
        ], 201);
    }

    protected function formatApplication(NewsEditorApplication $app): array
    {
        return [
            'id' => $app->id,
            'userId' => (string) $app->user_id,
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
