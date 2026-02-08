<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class EventsController extends BaseController
{
    /**
     * Format time with AM/PM
     * Handles TIME type from MySQL database (stored as H:i format)
     */
    private function formatTimeWithAmPm($time): string
    {
        if (empty($time)) {
            return '';
        }
        
        // Handle Carbon instance
        if ($time instanceof \Carbon\Carbon) {
            return $time->format('h:i A');
        }
        
        // Handle string time format (H:i or H:i:s from MySQL TIME type)
        if (is_string($time)) {
            try {
                // Try H:i format first (most common for TIME type)
                $carbonTime = \Carbon\Carbon::createFromFormat('H:i:s', $time);
                return $carbonTime->format('h:i A');
            } catch (\Exception $e) {
                try {
                    // Try H:i format (without seconds)
                    $carbonTime = \Carbon\Carbon::createFromFormat('H:i', $time);
                    return $carbonTime->format('h:i A');
                } catch (\Exception $e2) {
                    try {
                        // Try parsing as general time
                        $carbonTime = \Carbon\Carbon::parse($time);
                        return $carbonTime->format('h:i A');
                    } catch (\Exception $e3) {
                        return $time; // Return original if all parsing fails
                    }
                }
            }
        }
        
        return (string) $time;
    }

    private function mapEvent(Event $event): array
    {
        // Determine if cover field contains an image path or text
        $isImagePath = $event->cover && (
            str_contains($event->cover, 'events/images/') || 
            str_contains($event->cover, 'events/covers/')
        );
        
        return [
            'id' => $event->id,
            'name' => $event->name,
            'location' => $event->location,
            'description' => $event->description_short,
            'start_date' => $event->start_date?->format('d-m-Y'), // Format: DD-MM-YYYY (e.g., 14-02-2026)
            'start_time' => $event->start_time ? $this->formatTimeWithAmPm($event->start_time) : null, // Format: hh:mm AM/PM
            'end_date' => $event->end_date?->format('d-m-Y'), // Format: DD-MM-YYYY
            'end_time' => $event->end_time ? $this->formatTimeWithAmPm($event->end_time) : null, // Format: hh:mm AM/PM
            'cover_url' => $event->cover_url,
            'image_url' => $isImagePath ? asset('storage/' . $event->cover) : null,
            'cover_image_url' => null, // Since we store only one image in cover field
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
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'], // 2MB max
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'], // 5MB max
        ]);

        // Handle image uploads
        $imagePath = '';
        $coverImagePath = '';
        
        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');
            $imageName = 'event_' . time() . '_' . uniqid() . '.' . $imageFile->getClientOriginalExtension();
            $imagePath = $imageFile->storeAs('events/images', $imageName, 'public');
        }
        
        if ($request->hasFile('cover_image')) {
            $coverFile = $request->file('cover_image');
            $coverName = 'event_cover_' . time() . '_' . uniqid() . '.' . $coverFile->getClientOriginalExtension();
            $coverImagePath = $coverFile->storeAs('events/covers', $coverName, 'public');
        }

        $event = new Event();
        $event->name = $validated['name'];
        $event->location = $validated['location'];
        $event->description = $validated['description'] ?? '';
        $event->start_date = $validated['start_date'];
        $event->start_time = $validated['start_time'];
        $event->end_date = $validated['end_date'];
        $event->end_time = $validated['end_time'];
        $event->poster_id = $userId;
        
        // Store the main image in cover field if uploaded, otherwise use cover text
        if ($imagePath) {
            $event->cover = $imagePath;
        } elseif ($coverImagePath) {
            $event->cover = $coverImagePath;
        } else {
            $event->cover = $validated['cover'] ?? '';
        }
        
        $event->save();

        return response()->json([
            'ok' => true,
            'message' => 'Event created successfully',
            'data' => [
                'id' => $event->id,
                'name' => $event->name,
                'description' => $event->description,
                'location' => $event->location,
                'start_date' => $event->start_date?->format('d-m-Y'), // Format: DD-MM-YYYY
                'start_time' => $event->start_time ? $this->formatTimeWithAmPm($event->start_time) : null, // Format: hh:mm AM/PM
                'end_date' => $event->end_date?->format('d-m-Y'), // Format: DD-MM-YYYY
                'end_time' => $event->end_time ? $this->formatTimeWithAmPm($event->end_time) : null, // Format: hh:mm AM/PM
                'image_url' => $imagePath ? asset('storage/' . $imagePath) : null,
                'cover_image_url' => $coverImagePath ? asset('storage/' . $coverImagePath) : null,
                'cover_url' => $event->cover_url,
                'status' => $event->status_text,
                'is_owner' => true,
                'created_at' => $event->created_at?->format('c'),
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

    public function goEvent(Request $request): JsonResponse
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

        // Validate event_id
        $validated = $request->validate([
            'event_id' => ['required', 'integer'],
        ]);

        $eventId = $validated['event_id'];

        // Check if event exists
        $event = Event::find($eventId);
        if (!$event) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Event not found',
                ],
            ], 400);
        }

        // Check if Wo_Egoing table exists
        if (!DB::getSchemaBuilder()->hasTable('Wo_Egoing')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Event going table does not exist',
                ],
            ], 400);
        }

        // Check if user is already going to the event
        $isGoing = DB::table('Wo_Egoing')
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->exists();

        $goStatus = 'invalid';

        if ($isGoing) {
            // Remove from going list
            DB::table('Wo_Egoing')
                ->where('event_id', $eventId)
                ->where('user_id', $userId)
                ->delete();
            
            // Also remove from interested list (matching old code behavior)
            if (DB::getSchemaBuilder()->hasTable('Wo_Einterested')) {
                DB::table('Wo_Einterested')
                    ->where('event_id', $eventId)
                    ->where('user_id', $userId)
                    ->delete();
            }
            
            $goStatus = 'not-going';
        } else {
            // Add to going list
            DB::table('Wo_Egoing')->insert([
                'event_id' => $eventId,
                'user_id' => $userId,
            ]);

            // Remove from invited list if exists (when user accepts going, remove from invites)
            if (DB::getSchemaBuilder()->hasTable('Wo_Einvited')) {
                DB::table('Wo_Einvited')
                    ->where('event_id', $eventId)
                    ->where('invited_id', $userId)
                    ->delete();
            }

            // Remove from interested list if exists (going takes precedence)
            if (DB::getSchemaBuilder()->hasTable('Wo_Einterested')) {
                DB::table('Wo_Einterested')
                    ->where('event_id', $eventId)
                    ->where('user_id', $userId)
                    ->delete();
            }

            $goStatus = 'going';
        }

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'api_version' => '1.0',
            'go_status' => $goStatus,
            'data' => [
                'going' => $goStatus === 'going',
            ],
        ]);
    }
}


