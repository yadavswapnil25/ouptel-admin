<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class PokeController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        // Fetch pokes received by the user
        $pokes = DB::table('Wo_Pokes')
            ->join('Wo_Users', 'Wo_Pokes.send_user_id', '=', 'Wo_Users.user_id')
            ->where('Wo_Pokes.received_user_id', $userId)
            ->select(
                'Wo_Pokes.id as poke_id',
                'Wo_Pokes.send_user_id',
                'Wo_Pokes.received_user_id',
                'Wo_Users.username',
                'Wo_Users.avatar',
                'Wo_Users.first_name',
                'Wo_Users.last_name'
            )
            ->orderByDesc('Wo_Pokes.id')
            ->paginate($perPage);

        $data = $pokes->getCollection()->map(function ($poke) {
            return [
                'poke_id' => $poke->poke_id,
                'user' => [
                    'user_id' => $poke->send_user_id,
                    'username' => $poke->username,
                    'avatar_url' => \App\Helpers\ImageHelper::getImageUrl($poke->avatar, 'user'),
                    'full_name' => trim($poke->first_name . ' ' . $poke->last_name),
                ],
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $pokes->currentPage(),
                'per_page' => $pokes->perPage(),
                'total' => $pokes->total(),
                'last_page' => $pokes->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        $targetUserId = (int) $validated['user_id'];

        // Can't poke yourself
        if ($userId === $targetUserId) {
            return response()->json(['ok' => false, 'message' => 'You cannot poke yourself'], 400);
        }

        // Check if already poked
        $existingPoke = DB::table('Wo_Pokes')
            ->where('received_user_id', $targetUserId)
            ->where('send_user_id', $userId)
            ->first();

        if ($existingPoke) {
            return response()->json(['ok' => false, 'message' => 'This user is already poked'], 400);
        }

        // Check if target user exists
        $targetUser = User::where('user_id', $targetUserId)->first();
        if (!$targetUser) {
            return response()->json(['ok' => false, 'message' => 'User not found'], 404);
        }

        // Create poke
        $pokeId = DB::table('Wo_Pokes')->insertGetId([
            'received_user_id' => $targetUserId,
            'send_user_id' => $userId,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'User successfully poked',
            'data' => [
                'poke_id' => $pokeId,
                'user' => [
                    'user_id' => $targetUser->user_id,
                    'username' => $targetUser->username,
                    'avatar_url' => $targetUser->avatar_url,
                ],
            ],
        ], 201);
    }

    public function destroy(Request $request, int $pokeId): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        // Check if poke exists and user is the sender
        $poke = DB::table('Wo_Pokes')
            ->where('id', $pokeId)
            ->where('send_user_id', $userId)
            ->first();

        if (!$poke) {
            return response()->json(['ok' => false, 'message' => 'Poke not found or you are not the sender'], 404);
        }

        // Delete poke
        DB::table('Wo_Pokes')->where('id', $pokeId)->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Poke successfully deleted',
        ]);
    }
}
