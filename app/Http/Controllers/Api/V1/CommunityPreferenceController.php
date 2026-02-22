<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommunityPreference;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunityPreferenceController extends Controller
{
    /**
     * List all community preferences (public - for registration and profile)
     */
    public function index(): JsonResponse
    {
        $preferences = CommunityPreference::orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        return response()->json([
            'ok' => true,
            'data' => $preferences,
        ]);
    }

    /**
     * Get current user's community preferences (requires auth)
     */
    public function userPreferences(Request $request): JsonResponse
    {
        $tokenUserId = $this->getAuthUserId($request);
        if (!$tokenUserId) {
            return $this->unauthorized();
        }

        $user = User::where('user_id', $tokenUserId)->first();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'User not found'], 404);
        }

        $preferenceIds = DB::table('user_community_preferences')
            ->where('user_id', $user->user_id)
            ->pluck('preference_id')
            ->values()
            ->all();

        $preferences = CommunityPreference::whereIn('id', $preferenceIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        return response()->json([
            'ok' => true,
            'data' => [
                'preference_ids' => $preferenceIds,
                'preferences' => $preferences,
            ],
        ]);
    }

    /**
     * Save current user's community preferences (requires auth)
     */
    public function updateUserPreferences(Request $request): JsonResponse
    {
        $tokenUserId = $this->getAuthUserId($request);
        if (!$tokenUserId) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'preference_ids' => 'nullable|array',
            'preference_ids.*' => 'integer|exists:community_preferences,id',
        ]);

        $user = User::where('user_id', $tokenUserId)->first();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'User not found'], 404);
        }

        $ids = $validated['preference_ids'] ?? [];
        $user->communityPreferences()->sync($ids);

        return response()->json([
            'ok' => true,
            'message' => 'Community preferences updated successfully',
            'data' => [
                'preference_ids' => $ids,
            ],
        ]);
    }

    private function getAuthUserId(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }
        $token = substr($authHeader, 7);
        return DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
    }
}
