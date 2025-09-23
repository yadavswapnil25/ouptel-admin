<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class GamesController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $query = Game::query()->where('active', 1)->orderByDesc('id');

        $term = $request->query('term', $request->query('q'));
        if (!empty($term)) {
            $like = '%' . str_replace('%', '\\%', $term) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('game_name', 'like', $like)
                  ->orWhere('game_link', 'like', $like);
            });
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Game $game) {
            return [
                'id' => $game->id,
                'name' => $game->game_name,
                'avatar_url' => $game->avatar_url,
                'link' => $game->game_url,
                'players' => $game->players_count,
                'active_players' => $game->active_players_count,
                'created_at' => $game->created_date,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
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
            'game_name' => ['required', 'string', 'max:120'],
            'game_link' => ['required', 'string', 'max:255'],
            'game_avatar' => ['nullable', 'string'],
        ]);

        $game = new Game();
        $game->game_name = $validated['game_name'];
        $game->game_link = $validated['game_link'];
        $game->game_avatar = $validated['game_avatar'] ?? '';
        $game->active = 1;
        $game->time = time();
        $game->save();

        return response()->json([
            'ok' => true,
            'message' => 'Game added successfully',
            'data' => [
                'id' => $game->id,
                'name' => $game->game_name,
                'avatar_url' => $game->avatar_url,
                'link' => $game->game_url,
            ],
        ], 201);
    }
}


