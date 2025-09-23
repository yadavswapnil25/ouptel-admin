<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class EventsController extends BaseController
{
    private function mapEvent(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'location' => $event->location,
            'description' => $event->description_short,
            'start_date' => $event->start_date?->format('Y-m-d'),
            'start_time' => $event->start_time?->format('H:i'),
            'end_date' => $event->end_date?->format('Y-m-d'),
            'end_time' => $event->end_time?->format('H:i'),
            'cover_url' => $event->cover_url,
            'status' => $event->status_text,
            'counts' => [
                'going' => $event->going_count,
                'interested' => $event->interested_count,
                'invited' => $event->invited_count,
                'comments' => $event->comments_count,
                'reactions' => $event->reactions_count,
            ],
        ];
    }
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $query = Event::query()->orderByDesc('id');

        if ($request->filled('status')) { // upcoming|ongoing|past
            $status = $request->query('status');
            if ($status === 'upcoming') {
                $query->where('start_date', '>', now()->toDateString());
            } elseif ($status === 'ongoing') {
                $query->where('start_date', '<=', now()->toDateString())
                      ->where('end_date', '>=', now()->toDateString());
            } elseif ($status === 'past') {
                $query->where('end_date', '<', now()->toDateString());
            }
        }

        if ($request->filled('term')) {
            $like = '%' . str_replace('%', '\\%', $request->query('term')) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('location', 'like', $like)
                  ->orWhere('description', 'like', $like);
            });
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(fn (Event $e) => $this->mapEvent($e));

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
            'name' => ['required', 'string', 'max:120'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'end_time' => ['required', 'date_format:H:i'],
            'cover' => ['nullable', 'string'],
        ]);

        $event = new Event();
        $event->name = $validated['name'];
        $event->location = $validated['location'];
        $event->description = $validated['description'] ?? '';
        $event->start_date = $validated['start_date'];
        $event->start_time = $validated['start_time'];
        $event->end_date = $validated['end_date'];
        $event->end_time = $validated['end_time'];
        $event->poster_id = $userId;
        $event->cover = $validated['cover'] ?? '';
        $event->save();

        return response()->json([
            'ok' => true,
            'message' => 'Event created successfully',
            'data' => [
                'id' => $event->id,
                'name' => $event->name,
                'start_date' => $event->start_date?->format('Y-m-d'),
                'start_time' => $event->start_time?->format('H:i'),
                'end_date' => $event->end_date?->format('Y-m-d'),
                'end_time' => $event->end_time?->format('H:i'),
                'cover_url' => $event->cover_url,
                'status' => $event->status_text,
            ],
        ], 201);
    }

    public function going(Request $request): JsonResponse
    {
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

        $query = Event::query()
            ->join('Wo_Egoing', 'Wo_Events.id', '=', 'Wo_Egoing.event_id')
            ->where('Wo_Egoing.user_id', $userId)
            ->orderByDesc('Wo_Events.id')
            ->select('Wo_Events.*');

        $paginator = $query->paginate($perPage);
        $data = $paginator->getCollection()->map(fn ($e) => $this->mapEvent($e));

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

    public function invited(Request $request): JsonResponse
    {
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

        $query = Event::query()
            ->join('Wo_Einvited', 'Wo_Events.id', '=', 'Wo_Einvited.event_id')
            ->where('Wo_Einvited.invited_id', $userId)
            ->orderByDesc('Wo_Events.id')
            ->select('Wo_Events.*');

        $paginator = $query->paginate($perPage);
        $data = $paginator->getCollection()->map(fn ($e) => $this->mapEvent($e));

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

    public function interested(Request $request): JsonResponse
    {
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

        $query = Event::query()
            ->join('Wo_Einterested', 'Wo_Events.id', '=', 'Wo_Einterested.event_id')
            ->where('Wo_Einterested.user_id', $userId)
            ->orderByDesc('Wo_Events.id')
            ->select('Wo_Events.*');

        $paginator = $query->paginate($perPage);
        $data = $paginator->getCollection()->map(fn ($e) => $this->mapEvent($e));

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->PerPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
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

        $query = Event::query()->where('poster_id', $userId)->orderByDesc('id');
        $paginator = $query->paginate($perPage);
        $data = $paginator->getCollection()->map(fn ($e) => $this->mapEvent($e));

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
}


