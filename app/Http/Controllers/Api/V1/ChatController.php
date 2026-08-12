<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * One-to-one chat. Threads live in Wo_Conversations, per-user read state in
 * Wo_ConversationParticipants, and message bodies stay in the legacy
 * Wo_Messages table.
 *
 * History is paginated by message id rather than page number so that new
 * messages arriving mid-scroll cannot shift rows across page boundaries.
 */
class ChatController extends Controller
{
    private const ONLINE_WINDOW_SECONDS = 60;

    private ChatService $chat;

    public function __construct(ChatService $chat)
    {
        $this->chat = $chat;
    }

    /**
     * Inbox: the user's threads, newest activity first.
     */
    public function conversations(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $limit = $this->boundedLimit($request->query('limit'), 20, 50);
        $beforeAt = (int) $request->query('before_at', 0);
        $beforeId = (int) $request->query('before_id', 0);

        $query = ConversationParticipant::query()->inboxFor($userId);

        if ($beforeAt > 0) {
            $query->where(function ($query) use ($beforeAt, $beforeId) {
                $query->where('last_message_at', '<', $beforeAt)
                    ->orWhere(function ($query) use ($beforeAt, $beforeId) {
                        $query->where('last_message_at', $beforeAt)
                            ->where('conversation_id', '<', $beforeId);
                    });
            });
        }

        $participants = $query->orderByDesc('conversation_id')->limit($limit)->get();

        if ($participants->isEmpty()) {
            return response()->json([
                'ok' => true,
                'data' => [],
                'meta' => [
                    'has_more' => false,
                    'next_before_at' => null,
                    'next_before_id' => null,
                    'total_unread' => 0,
                ],
            ]);
        }

        $conversations = Conversation::query()
            ->whereIn('id', $participants->pluck('conversation_id'))
            ->get()
            ->keyBy('id');

        $peerIds = $conversations
            ->map(fn (Conversation $conversation) => $conversation->otherParticipantId($userId))
            ->filter()
            ->values();

        $peers = $this->fetchUsers($peerIds->all());

        $lastMessages = Message::query()
            ->whereIn('id', $conversations->pluck('last_message_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $data = [];
        foreach ($participants as $participant) {
            $conversation = $conversations->get($participant->conversation_id);
            if (! $conversation) {
                continue;
            }

            $peerId = $conversation->otherParticipantId($userId);
            $lastMessage = $lastMessages->get($conversation->last_message_id);

            $data[] = [
                'id' => (int) $conversation->id,
                'type' => $conversation->type,
                'unread_count' => (int) $participant->unread_count,
                'is_muted' => $participant->isMuted(),
                'last_message_at' => (int) $participant->last_message_at,
                'last_message_preview' => (string) $conversation->last_message_preview,
                'user' => $peerId ? ($peers[$peerId] ?? null) : null,
                'last_message' => $lastMessage ? $this->formatMessage($lastMessage, $userId) : null,
            ];
        }

        $last = $participants->last();

        return response()->json([
            'ok' => true,
            'data' => $data,
            'meta' => [
                'has_more' => $participants->count() === $limit,
                'next_before_at' => (int) $last->last_message_at,
                'next_before_id' => (int) $last->conversation_id,
                // Counts every thread, not just this page, so badges stay right
                // for users with more conversations than fit in one request.
                'total_unread' => $this->chat->totalUnreadFor($userId),
            ],
        ]);
    }

    /**
     * Open (or reuse) the thread with another user.
     */
    public function openConversation(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $otherUserId = (int) $request->input('user_id');

        if ($reason = $this->chat->messagingBlockedReason($userId, $otherUserId)) {
            return response()->json(['ok' => false, 'message' => $reason], 403);
        }

        $conversation = $this->chat->findOrCreateDirectConversation($userId, $otherUserId);

        return response()->json([
            'ok' => true,
            'data' => $this->conversationPayload($conversation, $userId),
        ]);
    }

    /**
     * Thread metadata for the chat header.
     */
    public function showConversation(Request $request, int $conversationId): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $conversation = Conversation::find($conversationId);
        if (! $conversation || ! $this->chat->participant($conversationId, $userId)) {
            return $this->notFound();
        }

        return response()->json([
            'ok' => true,
            'data' => $this->conversationPayload($conversation, $userId),
        ]);
    }

    /**
     * Message history, or everything after a known id when syncing.
     *
     * before_id walks backwards through history; after_id fetches what the client
     * missed, which is what a reconnecting client uses instead of trusting a
     * socket to replay.
     */
    public function messages(Request $request, int $conversationId): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $conversation = Conversation::find($conversationId);
        if (! $conversation || ! $this->chat->participant($conversationId, $userId)) {
            return $this->notFound();
        }

        $limit = $this->boundedLimit($request->query('limit'), 30, 100);
        $beforeId = (int) $request->query('before_id', 0);
        $afterId = (int) $request->query('after_id', 0);

        // Stamped before reading so the rows returned already carry the delivery
        // state, rather than reporting it one request late.
        $this->chat->markDelivered($conversationId, $userId);

        $query = Message::query()
            ->where('conversation_id', $conversationId)
            ->visibleTo($userId);

        if ($afterId > 0) {
            $messages = $query->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit($limit)
                ->get();
        } else {
            if ($beforeId > 0) {
                $query->where('id', '<', $beforeId);
            }

            $messages = $query->orderByDesc('id')->limit($limit)->get()->reverse()->values();
        }

        return response()->json([
            'ok' => true,
            'data' => $messages->map(fn (Message $message) => $this->formatMessage($message, $userId))->all(),
            'meta' => [
                'has_more' => $messages->count() === $limit,
                'oldest_id' => $messages->isEmpty() ? null : (int) $messages->first()->id,
                'newest_id' => $messages->isEmpty() ? null : (int) $messages->last()->id,
            ],
        ]);
    }

    /**
     * Send a text message. Passing client_uuid makes the call idempotent.
     */
    public function sendMessage(Request $request, int $conversationId): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $validator = Validator::make($request->all(), [
            'text' => ['required', 'string', 'max:5000'],
            'client_uuid' => ['nullable', 'string', 'max:36'],
            'reply_id' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $text = trim((string) $request->input('text'));
        if ($text === '') {
            return response()->json(['ok' => false, 'message' => 'Message cannot be empty.'], 422);
        }

        $conversation = Conversation::find($conversationId);
        if (! $conversation || ! $this->chat->participant($conversationId, $userId)) {
            return $this->notFound();
        }

        $recipientId = $conversation->otherParticipantId($userId);
        if (! $recipientId) {
            return response()->json(['ok' => false, 'message' => 'Unsupported conversation type.'], 422);
        }

        if ($reason = $this->chat->messagingBlockedReason($userId, $recipientId)) {
            return response()->json(['ok' => false, 'message' => $reason], 403);
        }

        $message = $this->chat->sendTextMessage(
            $conversation,
            $userId,
            $recipientId,
            $text,
            $request->input('client_uuid') ?: null,
            (int) $request->input('reply_id', 0)
        );

        return response()->json([
            'ok' => true,
            'data' => $this->formatMessage($message, $userId),
        ], 201);
    }

    /**
     * Advance the read cursor, defaulting to the newest message in the thread.
     */
    public function markRead(Request $request, int $conversationId): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $conversation = Conversation::find($conversationId);
        if (! $conversation || ! $this->chat->participant($conversationId, $userId)) {
            return $this->notFound();
        }

        $upto = (int) $request->input('upto_message_id', 0);
        $marked = $this->chat->markRead($conversation, $userId, $upto ?: null);

        return response()->json([
            'ok' => true,
            'data' => [
                'marked_read' => $marked,
                'total_unread' => $this->chat->totalUnreadFor($userId),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        return response()->json([
            'ok' => true,
            'data' => ['total_unread' => $this->chat->totalUnreadFor($userId)],
        ]);
    }

    /**
     * Remove a message from the caller's side only.
     */
    public function destroyMessage(Request $request, int $messageId): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $message = Message::find($messageId);
        if (! $message) {
            return $this->notFound();
        }

        if (! $this->chat->deleteMessageForUser($message, $userId)) {
            return response()->json(['ok' => false, 'message' => 'Message not found.'], 404);
        }

        return response()->json(['ok' => true, 'message' => 'Message deleted.']);
    }

    /**
     * Hide a whole thread from the caller's side only.
     */
    public function destroyConversation(Request $request, int $conversationId): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (! $userId) {
            return $this->unauthorized();
        }

        $conversation = Conversation::find($conversationId);
        if (! $conversation || ! $this->chat->participant($conversationId, $userId)) {
            return $this->notFound();
        }

        $this->chat->clearConversationForUser($conversation, $userId);

        return response()->json(['ok' => true, 'message' => 'Conversation deleted.']);
    }

    private function conversationPayload(Conversation $conversation, int $userId): array
    {
        $participant = $this->chat->participant($conversation->id, $userId);
        $peerId = $conversation->otherParticipantId($userId);
        $peers = $peerId ? $this->fetchUsers([$peerId]) : [];

        return [
            'id' => (int) $conversation->id,
            'type' => $conversation->type,
            'unread_count' => $participant ? (int) $participant->unread_count : 0,
            'is_muted' => $participant ? $participant->isMuted() : false,
            'last_message_at' => (int) $conversation->last_message_at,
            'last_message_preview' => (string) $conversation->last_message_preview,
            'user' => $peerId ? ($peers[$peerId] ?? null) : null,
        ];
    }

    private function formatMessage(Message $message, int $viewerId): array
    {
        return [
            'id' => (int) $message->id,
            'conversation_id' => (int) $message->conversation_id,
            'client_uuid' => $message->client_uuid,
            'from_id' => (int) $message->from_id,
            'to_id' => (int) $message->to_id,
            'text' => (string) ($message->text ?? ''),
            'time' => (int) $message->time,
            'reply_id' => (int) $message->reply_id,
            'is_mine' => (int) $message->from_id === $viewerId,
            'is_delivered' => (int) $message->delivered_at > 0,
            'delivered_at' => (int) $message->delivered_at ?: null,
            'is_seen' => (int) $message->seen > 0,
            'seen_at' => (int) $message->seen ?: null,
        ];
    }

    /**
     * @param  array<int>  $userIds
     * @return array<int, array>
     */
    private function fetchUsers(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $users = DB::table('Wo_Users')
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'username', 'first_name', 'last_name', 'avatar', 'verified', 'lastseen', 'showlastseen']);

        $formatted = [];
        foreach ($users as $user) {
            $showLastSeen = (string) ($user->showlastseen ?? '1') !== '0';
            $lastSeen = (int) ($user->lastseen ?? 0);
            $visibleLastSeen = null;
            if ($showLastSeen && $lastSeen > 0) {
                $visibleLastSeen = $lastSeen;
            }

            $formatted[(int) $user->user_id] = [
                'user_id' => (int) $user->user_id,
                'username' => $user->username ?? '',
                'name' => $this->displayName($user),
                'avatar' => $user->avatar ?? '',
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'verified' => (bool) ($user->verified ?? false),
                'is_online' => $showLastSeen && $lastSeen > (time() - self::ONLINE_WINDOW_SECONDS),
                'last_seen' => $visibleLastSeen,
            ];
        }

        return $formatted;
    }

    private function displayName($user): string
    {
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $name !== '' ? $name : ($user->username ?? 'Unknown User');
    }

    private function boundedLimit($value, int $default, int $max): int
    {
        $limit = (int) ($value ?: $default);

        return max(1, min($limit, $max));
    }

    private function resolveUserId(Request $request): ?int
    {
        $attributeUserId = $request->attributes->get('apps_session_user_id');
        if ($attributeUserId) {
            return (int) $attributeUserId;
        }

        $authHeader = $request->header('Authorization');
        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($authHeader, 7));
        if ($token === '') {
            return null;
        }

        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');

        return $userId ? (int) $userId : null;
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => 'Conversation not found.'], 404);
    }
}
