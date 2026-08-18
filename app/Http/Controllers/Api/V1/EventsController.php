<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Event;
use App\Models\User;
use App\Services\FriendActivityNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

    /**
     * Resolve the authenticated user id from the Bearer token (Wo_AppsSessions).
     */
    private function resolveUserId(Request $request): ?int
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');

        return $userId ? (int) $userId : null;
    }

    private function mapEvent(Event $event, ?int $userId = null): array
    {
        // Determine if cover field contains an image path or text
        $isImagePath = $event->cover && (
            str_contains($event->cover, 'events/images/') || 
            str_contains($event->cover, 'events/covers/')
        );

        $isGoing = false;
        $isInterested = false;
        if ($userId) {
            if (DB::getSchemaBuilder()->hasTable('Wo_Egoing')) {
                $isGoing = DB::table('Wo_Egoing')
                    ->where('event_id', $event->id)
                    ->where('user_id', $userId)
                    ->exists();
            }
            if (DB::getSchemaBuilder()->hasTable('Wo_Einterested')) {
                $isInterested = DB::table('Wo_Einterested')
                    ->where('event_id', $event->id)
                    ->where('user_id', $userId)
                    ->exists();
            }
        }

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
            'is_going' => $isGoing,
            'is_interested' => $isInterested,
            'is_owner' => $userId !== null && (int) $event->poster_id === (int) $userId,
            'counts' => [
                'going' => $event->going_count,
                'interested' => $event->interested_count,
                'invited' => $event->invited_count,
                'comments' => $event->comments_count,
                'reactions' => $event->reactions_count,
            ],
            'is_public' => (bool) ($event->getAttributes()['is_public'] ?? true),
            'allow_join' => (bool) ($event->getAttributes()['allow_join'] ?? true),
            'published' => (bool) ($event->getAttributes()['published'] ?? true),
            'agreement_accepted' => (bool) ($event->getAttributes()['agreement_accepted'] ?? false),
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

        $currentUserId = $this->resolveUserId($request);
        $data = $paginator->getCollection()->map(fn (Event $e) => $this->mapEvent($e, $currentUserId));

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

        $agreementAccepted = $request->input('agreement_accepted', $request->input('agreed_to_terms', false));
        $agreementAcceptedNormalized = filter_var($agreementAccepted, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($agreementAcceptedNormalized !== true) {
            return response()->json([
                'ok' => false,
                'message' => 'Please agree to Ouptel\'s Event Terms & Community Guidelines',
            ], 400);
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
            'is_public' => ['sometimes', 'boolean'],
            'allow_join' => ['sometimes', 'boolean'],
            'published' => ['sometimes', 'boolean'],
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

        if (Schema::hasColumn('Wo_Events', 'is_public')) {
            $event->setAttribute('is_public', $request->boolean('is_public', true));
        }
        if (Schema::hasColumn('Wo_Events', 'allow_join')) {
            $event->setAttribute('allow_join', $request->boolean('allow_join', true));
        }
        if (Schema::hasColumn('Wo_Events', 'published')) {
            $event->setAttribute('published', $request->boolean('published', true));
        }
        if (Schema::hasColumn('Wo_Events', 'agreement_accepted')) {
            $event->setAttribute('agreement_accepted', true);
        }
        if (Schema::hasColumn('Wo_Events', 'agreement_accepted_at')) {
            $event->setAttribute('agreement_accepted_at', now());
        }
        
        $event->save();

        $isPublished = (bool) ($event->getAttributes()['published'] ?? true);
        if ($isPublished) {
            $this->createEventFeedPost((int) $userId, $event);
            FriendActivityNotificationService::notifyFriendsOfNewEvent($event, (string) $userId);
        }

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
                'is_public' => (bool) ($event->getAttributes()['is_public'] ?? true),
                'allow_join' => (bool) ($event->getAttributes()['allow_join'] ?? true),
                'published' => (bool) ($event->getAttributes()['published'] ?? true),
                'agreement_accepted' => (bool) ($event->getAttributes()['agreement_accepted'] ?? true),
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
        $data = $paginator->getCollection()->map(fn ($e) => $this->mapEvent($e, (int) $userId));

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
        $data = $paginator->getCollection()->map(fn ($e) => $this->mapEvent($e, (int) $userId));

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
        $data = $paginator->getCollection()->map(fn ($e) => $this->mapEvent($e, (int) $userId));

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
        $data = $paginator->getCollection()->map(fn ($e) => $this->mapEvent($e, (int) $userId));

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

    public function show(Request $request, int $id): JsonResponse
    {
        $event = Event::find($id);
        if (!$event) {
            return response()->json(['ok' => false, 'message' => 'Event not found'], 404);
        }

        $currentUserId = $this->resolveUserId($request);
        $data = $this->mapEvent($event, $currentUserId);
        $poster = User::find($event->poster_id);
        $data['poster'] = $poster ? $this->mapUserForEventGuest($poster) : null;
        $data['is_owner'] = $currentUserId !== null && (int) $event->poster_id === (int) $currentUserId;

        return response()->json(['data' => $data]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $event = Event::find($id);
        if (!$event) {
            return response()->json(['ok' => false, 'message' => 'Event not found'], 404);
        }

        if ((int) $event->poster_id !== (int) $userId) {
            return response()->json([
                'ok' => false,
                'message' => 'Only the event owner can delete this event',
            ], 403);
        }

        try {
            DB::transaction(function () use ($event) {
                $this->deleteRelatedEventRows((int) $event->id);
                $event->delete();
            });
        } catch (\Exception $e) {
            Log::warning('Failed to delete event: ' . $e->getMessage(), [
                'event_id' => $id,
                'user_id' => $userId,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Unable to delete this event. Please try again.',
            ], 500);
        }

        return response()->json(['ok' => true, 'message' => 'Event deleted']);
    }

    public function guests(Request $request, int $id): JsonResponse
    {
        $event = Event::find($id);
        if (!$event) {
            return response()->json(['ok' => false, 'message' => 'Event not found'], 404);
        }

        $type = $request->query('type', 'going');
        if (!in_array($type, ['going', 'interested'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid guest type. Use going or interested.',
            ], 400);
        }

        $table = $type === 'interested' ? 'Wo_Einterested' : 'Wo_Egoing';
        if (!Schema::hasTable($table)) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                    'last_page' => 1,
                ],
                'type' => $type,
            ]);
        }

        $perPage = (int) ($request->query('per_page', 20));
        $perPage = max(1, min($perPage, 50));

        $currentUserId = $this->resolveUserId($request);
        $isOwner = $currentUserId !== null && (int) $event->poster_id === (int) $currentUserId;

        $paginator = DB::table($table)
            ->join('Wo_Users', "{$table}.user_id", '=', 'Wo_Users.user_id')
            ->where("{$table}.event_id", $id)
            ->select('Wo_Users.*')
            ->orderByDesc("{$table}.id")
            ->paginate($perPage);

        $data = collect($paginator->items())->map(
            fn ($user) => $this->mapUserForEventGuest($user, $isOwner)
        );

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'type' => $type,
            'is_owner' => $isOwner,
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

        if (!$isGoing) {
            $mobileError = $this->mobileRequiredResponse((int) $userId);
            if ($mobileError) {
                return $mobileError;
            }
        }

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

        if ($goStatus === 'going') {
            $this->notifyEventHost($event, (int) $userId, 'going_event');
        } elseif ($goStatus === 'not-going') {
            $this->notifyEventHost($event, (int) $userId, 'left_event');
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

    public function interestEvent(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'event_id' => ['required', 'integer'],
        ]);

        $eventId = $validated['event_id'];

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

        if (!DB::getSchemaBuilder()->hasTable('Wo_Einterested')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Event interested table does not exist',
                ],
            ], 400);
        }

        $isInterested = DB::table('Wo_Einterested')
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->exists();

        if (!$isInterested) {
            $mobileError = $this->mobileRequiredResponse((int) $userId);
            if ($mobileError) {
                return $mobileError;
            }
        }

        if ($isInterested) {
            DB::table('Wo_Einterested')
                ->where('event_id', $eventId)
                ->where('user_id', $userId)
                ->delete();

            $interestStatus = 'not-interested';
        } else {
            DB::table('Wo_Einterested')->insert([
                'event_id' => $eventId,
                'user_id' => $userId,
            ]);

            // Going takes precedence over interested — remove from going if present.
            if (DB::getSchemaBuilder()->hasTable('Wo_Egoing')) {
                DB::table('Wo_Egoing')
                    ->where('event_id', $eventId)
                    ->where('user_id', $userId)
                    ->delete();
            }

            $interestStatus = 'interested';
        }

        if ($interestStatus === 'interested') {
            $this->notifyEventHost($event, (int) $userId, 'interested_event');
        } elseif ($interestStatus === 'not-interested') {
            $this->notifyEventHost($event, (int) $userId, 'uninterested_event');
        }

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'api_version' => '1.0',
            'interest_status' => $interestStatus,
            'data' => [
                'interested' => $interestStatus === 'interested',
            ],
        ]);
    }

    private function mapUserForEventGuest(object $user, bool $includeContact = false): array
    {
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        if ($name === '') {
            $name = $user->name ?? $user->username ?? 'User';
        }

        $payload = [
            'user_id' => $user->user_id,
            'username' => $user->username ?? '',
            'name' => $name,
            'avatar_url' => !empty($user->avatar) ? asset('storage/' . $user->avatar) : null,
        ];

        if ($includeContact) {
            $email = trim((string) ($user->email ?? ''));
            $phone = '';
            foreach (['phone_number', 'phone', 'mobile'] as $field) {
                $value = trim((string) ($user->{$field} ?? ''));
                if ($value !== '') {
                    $phone = $value;
                    break;
                }
            }
            $payload['email'] = $email !== '' ? $email : null;
            $payload['phone'] = $phone !== '' ? $phone : null;
            $payload['mobile'] = $payload['phone'];
        }

        return $payload;
    }

    private function userHasMobileNumber(int $userId): bool
    {
        $query = DB::table('Wo_Users')->where('user_id', $userId);
        $columns = ['phone_number'];
        if (Schema::hasColumn('Wo_Users', 'phone')) {
            $columns[] = 'phone';
        }
        if (Schema::hasColumn('Wo_Users', 'mobile')) {
            $columns[] = 'mobile';
        }
        $user = $query->first($columns);
        if (!$user) {
            return false;
        }

        foreach ($columns as $field) {
            if (trim((string) ($user->{$field} ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function mobileRequiredResponse(int $userId): ?JsonResponse
    {
        if ($this->userHasMobileNumber($userId)) {
            return null;
        }

        $message = 'Update your mobile number, then you can join any event.';

        return response()->json([
            'ok' => false,
            'api_status' => 400,
            'code' => 'mobile_required',
            'message' => $message,
            'errors' => [
                'error_id' => 'mobile_required',
                'error_text' => $message,
            ],
        ], 400);
    }

    private function notifyEventHost(Event $event, int $actorUserId, string $type): void
    {
        $hostId = (int) ($event->poster_id ?? 0);
        if ($hostId <= 0 || $hostId === $actorUserId) {
            return;
        }
        if (!in_array($type, ['interested_event', 'going_event', 'left_event', 'uninterested_event'], true)) {
            return;
        }
        if (!Schema::hasTable('Wo_Notifications')) {
            return;
        }

        try {
            $payload = [
                'notifier_id' => $actorUserId,
                'recipient_id' => $hostId,
                'type' => $type,
                'url' => 'index.php?link1=show-event&eid=' . $event->id,
                'time' => time(),
                'seen' => 0,
            ];
            if (Schema::hasColumn('Wo_Notifications', 'event_id')) {
                $payload['event_id'] = $event->id;
            }
            DB::table('Wo_Notifications')->insert($payload);
        } catch (\Exception $e) {
            // Notification failure should not block RSVP.
        }
    }

    private function deleteRelatedEventRows(int $eventId): void
    {
        $tables = [
            'Wo_Egoing',
            'Wo_Einterested',
            'Wo_Einvited',
            'Wo_Event_Comments',
            'Wo_Event_Comment_Replies',
            'Wo_Event_Reaction',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'event_id')) {
                DB::table($table)->where('event_id', $eventId)->delete();
            }
        }

        if (Schema::hasTable('Wo_Posts') && Schema::hasColumn('Wo_Posts', 'event_id')) {
            $postIds = DB::table('Wo_Posts')->where('event_id', $eventId)->pluck('id');
            if ($postIds->isNotEmpty()) {
                if (Schema::hasTable('Wo_Comments') && Schema::hasColumn('Wo_Comments', 'post_id')) {
                    DB::table('Wo_Comments')->whereIn('post_id', $postIds)->delete();
                }
                if (Schema::hasTable('Wo_Reactions') && Schema::hasColumn('Wo_Reactions', 'post_id')) {
                    DB::table('Wo_Reactions')->whereIn('post_id', $postIds)->delete();
                }
                DB::table('Wo_Posts')->whereIn('id', $postIds)->delete();
            }
        }

        if (Schema::hasTable('Wo_Notifications') && Schema::hasColumn('Wo_Notifications', 'event_id')) {
            DB::table('Wo_Notifications')->where('event_id', $eventId)->delete();
        }
    }

    /**
     * Create a Wo_Posts row so a published event appears on newsfeed and the author's profile.
     */
    private function createEventFeedPost(int $userId, Event $event): ?int
    {
        if (!Schema::hasTable('Wo_Posts') || $userId <= 0 || (int) $event->id <= 0) {
            return null;
        }

        try {
            $existingId = DB::table('Wo_Posts')
                ->where('event_id', $event->id)
                ->where('user_id', $userId)
                ->where('postType', 'event')
                ->value('id');
            if ($existingId) {
                return (int) $existingId;
            }

            $now = time();
            $isPublic = (bool) ($event->getAttributes()['is_public'] ?? true);
            $postData = [
                'user_id' => $userId,
                'recipient_id' => 0,
                'postText' => (string) ($event->name ?? ''),
                'page_id' => 0,
                'group_id' => 0,
                'event_id' => (int) $event->id,
                'postPrivacy' => $isPublic ? '0' : '1',
                'postType' => 'event',
                'time' => $now,
                'registered' => $now,
                'active' => '1',
                'postShare' => '0',
                'boosted' => '0',
                'comments_status' => '1',
                'send_notify' => '0',
            ];

            if (!empty($event->cover) && Schema::hasColumn('Wo_Posts', 'postPhoto')) {
                $postData['postPhoto'] = $event->cover;
            }
            if (Schema::hasColumn('Wo_Posts', 'postLink')) {
                $postData['postLink'] = '/events/' . $event->id;
            }
            if (Schema::hasColumn('Wo_Posts', 'postLinkTitle')) {
                $postData['postLinkTitle'] = (string) ($event->name ?? '');
            }
            if (Schema::hasColumn('Wo_Posts', 'postLinkContent')) {
                $postData['postLinkContent'] = Str::limit(strip_tags((string) ($event->description ?? '')), 200);
            }
            if (!empty($event->cover) && Schema::hasColumn('Wo_Posts', 'postLinkImage')) {
                $postData['postLinkImage'] = $event->cover;
            }

            $postId = (int) DB::table('Wo_Posts')->insertGetId($postData);
            if ($postId > 0 && Schema::hasColumn('Wo_Posts', 'post_id')) {
                DB::table('Wo_Posts')->where('id', $postId)->update(['post_id' => $postId]);
            }

            return $postId > 0 ? $postId : null;
        } catch (\Exception $e) {
            Log::warning('Failed to publish event to newsfeed: ' . $e->getMessage(), [
                'event_id' => $event->id,
                'user_id' => $userId,
            ]);
            return null;
        }
    }
}


