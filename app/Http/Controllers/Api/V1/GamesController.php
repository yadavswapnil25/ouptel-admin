<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class GamesController extends BaseController
{
    /**
     * Get all games (mimics old API: games.php?type=get)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request): JsonResponse
    {
        $limit = (int) ($request->input('limit', $request->query('limit', 20)));
        $limit = max(1, min($limit, 50));
        $offset = (int) ($request->input('offset', $request->query('offset', 0)));

        $query = DB::table('Wo_Games')
            ->where('active', 1)
            ->orderByDesc('id');

        if ($offset > 0) {
            $query->offset($offset);
        }

        $games = $query->limit($limit)->get();

        $formattedGames = $games->map(function ($game) {
            return $this->formatGameData($game);
        });

        return response()->json([
            'api_status' => 200,
            'data' => $formattedGames
        ]);
    }

    /**
     * Get my games (mimics old API: games.php?type=get_my)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getMy(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        $limit = (int) ($request->input('limit', $request->query('limit', 20)));
        $limit = max(1, min($limit, 50));
        $offset = (int) ($request->input('offset', $request->query('offset', 0)));

        // Get user's games from Wo_UserGames table
        $userGamesTable = 'Wo_UserGames';
        if (!Schema::hasTable($userGamesTable)) {
            return response()->json([
                'api_status' => 200,
                'data' => []
            ]);
        }

        $query = DB::table($userGamesTable)
            ->join('Wo_Games', $userGamesTable . '.game_id', '=', 'Wo_Games.id')
            ->where($userGamesTable . '.user_id', $tokenUserId)
            ->where('Wo_Games.active', 1)
            ->select('Wo_Games.*')
            ->orderByDesc('Wo_Games.id');

        if ($offset > 0) {
            $query->offset($offset);
        }

        $games = $query->limit($limit)->get();

        $formattedGames = $games->map(function ($game) {
            return $this->formatGameData($game);
        });

        return response()->json([
            'api_status' => 200,
            'data' => $formattedGames
        ]);
    }

    /**
     * Add game to my games (mimics old API: games.php?type=add_to_my)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function addToMy(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'game_id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'game_id can not be empty'
                ]
            ], 400);
        }

        $gameId = (int) $request->input('game_id');

        // Check if game exists
        $game = DB::table('Wo_Games')
            ->where('id', $gameId)
            ->where('active', 1)
            ->first();

        if (!$game) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Game not found'
                ]
            ], 404);
        }

        // Check if user already has this game
        $userGamesTable = 'Wo_UserGames';
        if (!Schema::hasTable($userGamesTable)) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'User games feature not available'
                ]
            ], 400);
        }

        $existing = DB::table($userGamesTable)
            ->where('user_id', $tokenUserId)
            ->where('game_id', $gameId)
            ->first();

        if ($existing) {
            return response()->json([
                'api_status' => 200,
                'message' => 'Game already in your list'
            ]);
        }

        try {
            // Add game to user's games
            $insertData = [
                'user_id' => $tokenUserId,
                'game_id' => $gameId,
            ];

            // Add time column if exists
            if (Schema::hasColumn($userGamesTable, 'time')) {
                $insertData['time'] = time();
            }

            DB::table($userGamesTable)->insert($insertData);

            // Increment game players count if column exists
            if (Schema::hasColumn('Wo_Games', 'players')) {
                DB::table('Wo_Games')
                    ->where('id', $gameId)
                    ->increment('players');
            }

            return response()->json([
                'api_status' => 200,
                'message' => 'Game added to your list'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Failed to add game: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Search games (mimics old API: games.php?type=search)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'query can not be empty'
                ]
            ], 400);
        }

        $searchQuery = $request->input('query');
        $limit = (int) ($request->input('limit', $request->query('limit', 20)));
        $limit = max(1, min($limit, 50));
        $offset = (int) ($request->input('offset', $request->query('offset', 0)));

        $like = '%' . str_replace('%', '\\%', $searchQuery) . '%';

        $query = DB::table('Wo_Games')
            ->where('active', 1)
            ->where(function ($q) use ($like) {
                $q->where('game_name', 'LIKE', $like)
                  ->orWhere('game_link', 'LIKE', $like);
                
                // Only search in game_description if column exists
                if (Schema::hasColumn('Wo_Games', 'game_description')) {
                    $q->orWhere('game_description', 'LIKE', $like);
                }
            })
            ->orderByDesc('id');

        if ($offset > 0) {
            $query->offset($offset);
        }

        $games = $query->limit($limit)->get();

        $formattedGames = $games->map(function ($game) {
            return $this->formatGameData($game);
        });

        return response()->json([
            'api_status' => 200,
            'data' => $formattedGames
        ]);
    }

    /**
     * Get popular games (mimics old API: games.php?type=popular)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function popular(Request $request): JsonResponse
    {
        $limit = (int) ($request->input('limit', $request->query('limit', 20)));
        $limit = max(1, min($limit, 50));
        $offset = (int) ($request->input('offset', $request->query('offset', 0)));

        $query = DB::table('Wo_Games')
            ->where('active', 1);

        // Order by players count if column exists, otherwise by id
        if (Schema::hasColumn('Wo_Games', 'players')) {
            $query->orderByDesc('players');
        } else {
            $query->orderByDesc('id');
        }

        if ($offset > 0) {
            $query->offset($offset);
        }

        $games = $query->limit($limit)->get();

        $formattedGames = $games->map(function ($game) {
            return $this->formatGameData($game);
        });

        return response()->json([
            'api_status' => 200,
            'data' => $formattedGames
        ]);
    }

    /**
     * Format game data for API response
     * 
     * @param object $game
     * @return array
     */
    private function formatGameData($game): array
    {
        $avatar = $game->game_avatar ?? $game->avatar ?? '';
        $gameLink = $game->game_link ?? $game->game_url ?? '';

        // Get players count
        $playersCount = 0;
        if (Schema::hasColumn('Wo_Games', 'players')) {
            $playersCount = (int) ($game->players ?? 0);
        } elseif (Schema::hasTable('Wo_UserGames')) {
            $playersCount = DB::table('Wo_UserGames')
                ->where('game_id', $game->id)
                ->count();
        }

        // Get active players count (users who played recently)
        $activePlayersCount = 0;
        if (Schema::hasTable('Wo_UserGames') && Schema::hasColumn('Wo_UserGames', 'time')) {
            $activePlayersCount = DB::table('Wo_UserGames')
                ->where('game_id', $game->id)
                ->where('time', '>', time() - (7 * 24 * 60 * 60)) // Last 7 days
                ->count();
        }

        return [
            'id' => $game->id,
            'game_name' => $game->game_name ?? '',
            'game_link' => $gameLink,
            'game_avatar' => $avatar,
            'game_avatar_url' => !empty($avatar) ? asset('storage/' . $avatar) : null,
            'game_description' => $game->game_description ?? '',
            'players' => $playersCount,
            'active_players' => $activePlayersCount,
            'time' => $game->time ?? time(),
            'time_text' => $this->getTimeElapsedString($game->time ?? time()),
        ];
    }

    /**
     * Get time elapsed string
     * 
     * @param int $timestamp
     * @return string
     */
    private function getTimeElapsedString(int $timestamp): string
    {
        $time = time() - $timestamp;
        
        if ($time < 60) return 'Just now';
        if ($time < 3600) return floor($time / 60) . ' minutes ago';
        if ($time < 86400) return floor($time / 3600) . ' hours ago';
        if ($time < 2592000) return floor($time / 86400) . ' days ago';
        if ($time < 31536000) return floor($time / 2592000) . ' months ago';
        return floor($time / 31536000) . ' years ago';
    }

    /**
     * Unified games endpoint (mimics old API: games.php with type parameter)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        $type = $request->input('type');

        switch ($type) {
            case 'get':
                return $this->getAll($request);
            
            case 'get_my':
                return $this->getMy($request);
            
            case 'add_to_my':
                return $this->addToMy($request);
            
            case 'search':
                return $this->search($request);
            
            case 'popular':
                return $this->popular($request);
            
            default:
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 4,
                        'error_text' => 'type can not be empty'
                    ]
                ], 400);
        }
    }

    /**
     * Legacy index method (for backward compatibility)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        return $this->getAll($request);
    }

    /**
     * Legacy store method (for backward compatibility)
     * 
     * @param Request $request
     * @return JsonResponse
     */
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


