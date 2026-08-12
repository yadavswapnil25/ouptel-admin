<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Write-side logic for one-to-one chat.
 *
 * Every mutation lives here rather than in the controller so that the realtime
 * layer added later has a single place to dispatch broadcast events from, and so
 * the legacy Wo_Messages bookkeeping (seen, deleted_one/deleted_two) is applied
 * consistently.
 */
class ChatService
{
    /** Wo_Users.message_privacy: anyone may start a thread. */
    private const PRIVACY_EVERYONE = '0';

    /** Wo_Users.message_privacy: only people the recipient follows. */
    private const PRIVACY_FOLLOWING = '1';

    /** Wo_Users.message_privacy: nobody. */
    private const PRIVACY_NOBODY = '2';

    /**
     * Reason the sender may not message the recipient, or null when allowed.
     */
    public function messagingBlockedReason(int $senderId, int $recipientId): ?string
    {
        if ($senderId === $recipientId) {
            return 'You cannot message yourself.';
        }

        $recipient = DB::table('Wo_Users')
            ->where('user_id', $recipientId)
            ->first(['user_id', 'active', 'message_privacy']);

        if (! $recipient || in_array((string) $recipient->active, ['0', '2'], true)) {
            return 'This account is unavailable.';
        }

        $blocked = DB::table('Wo_Blocks')
            ->where(function ($query) use ($senderId, $recipientId) {
                $query->where('blocker', $senderId)->where('blocked', $recipientId);
            })
            ->orWhere(function ($query) use ($senderId, $recipientId) {
                $query->where('blocker', $recipientId)->where('blocked', $senderId);
            })
            ->exists();

        if ($blocked) {
            return 'You cannot message this user.';
        }

        $privacy = (string) ($recipient->message_privacy ?? self::PRIVACY_EVERYONE);

        if ($privacy === self::PRIVACY_NOBODY) {
            return 'This user does not accept messages.';
        }

        if ($privacy === self::PRIVACY_FOLLOWING) {
            $recipientFollowsSender = DB::table('Wo_Followers')
                ->where('follower_id', $recipientId)
                ->where('following_id', $senderId)
                ->where('active', '1')
                ->exists();

            if (! $recipientFollowsSender) {
                return 'This user only accepts messages from people they follow.';
            }
        }

        return null;
    }

    /**
     * The direct thread between two users, created on first use.
     *
     * The unique pair index is the arbiter when two people open the same thread
     * at once: the loser of the race catches the constraint violation and reads
     * back the row the winner inserted.
     */
    public function findOrCreateDirectConversation(int $userId, int $otherUserId): Conversation
    {
        $existing = Conversation::query()->directBetween($userId, $otherUserId)->first();
        if ($existing) {
            return $existing;
        }

        [$one, $two] = Conversation::normalizePair($userId, $otherUserId);
        $now = time();

        try {
            return DB::transaction(function () use ($one, $two, $now) {
                $conversation = Conversation::create([
                    'type' => Conversation::TYPE_DIRECT,
                    'user_one_id' => $one,
                    'user_two_id' => $two,
                    'time' => $now,
                ]);

                foreach ([$one, $two] as $participantId) {
                    ConversationParticipant::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $participantId,
                        'time' => $now,
                    ]);
                }

                return $conversation;
            });
        } catch (QueryException $e) {
            $conversation = Conversation::query()->directBetween($userId, $otherUserId)->first();
            if ($conversation) {
                return $conversation;
            }

            throw $e;
        }
    }

    public function participant(int $conversationId, int $userId): ?ConversationParticipant
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Persist a text message and update the thread's denormalised counters.
     *
     * A repeated client_uuid returns the message already stored instead of
     * inserting a second copy, so a retried request after a dropped response is
     * harmless.
     */
    public function sendTextMessage(
        Conversation $conversation,
        int $senderId,
        int $recipientId,
        string $text,
        ?string $clientUuid = null,
        int $replyId = 0
    ): Message {
        if ($clientUuid) {
            $existing = Message::query()
                ->where('from_id', $senderId)
                ->where('client_uuid', $clientUuid)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $now = time();

        try {
            return DB::transaction(function () use (
                $conversation, $senderId, $recipientId, $text, $clientUuid, $replyId, $now
            ) {
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'client_uuid' => $clientUuid,
                    'from_id' => $senderId,
                    'to_id' => $recipientId,
                    'text' => $text,
                    'time' => $now,
                    'seen' => 0,
                    'delivered_at' => 0,
                    'reply_id' => $replyId,
                ]);

                Conversation::query()
                    ->where('id', $conversation->id)
                    ->update([
                        'last_message_id' => $message->id,
                        'last_message_at' => $now,
                        'last_message_preview' => $this->preview($text),
                    ]);

                ConversationParticipant::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', $senderId)
                    ->update(['last_message_at' => $now, 'cleared_at' => 0]);

                ConversationParticipant::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', $recipientId)
                    ->update([
                        'last_message_at' => $now,
                        'cleared_at' => 0,
                        'unread_count' => DB::raw('unread_count + 1'),
                    ]);

                return $message;
            });
        } catch (QueryException $e) {
            if ($clientUuid) {
                $existing = Message::query()
                    ->where('from_id', $senderId)
                    ->where('client_uuid', $clientUuid)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    /**
     * Advance the user's read cursor, defaulting to the newest message in the
     * thread. Legacy `seen` is stamped too so existing unread queries elsewhere
     * in the app stay correct.
     *
     * @return int Number of messages newly marked as read.
     */
    public function markRead(Conversation $conversation, int $userId, ?int $uptoMessageId = null): int
    {
        $now = time();

        $uptoMessageId = $uptoMessageId ?: (int) Message::query()
            ->where('conversation_id', $conversation->id)
            ->max('id');

        if (! $uptoMessageId) {
            return 0;
        }

        return DB::transaction(function () use ($conversation, $userId, $uptoMessageId, $now) {
            $affected = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('to_id', $userId)
                ->where('id', '<=', $uptoMessageId)
                ->where('seen', 0)
                ->update(['seen' => $now, 'delivered_at' => DB::raw('IF(delivered_at = 0, ' . $now . ', delivered_at)')]);

            $remaining = Message::query()
                ->where('conversation_id', $conversation->id)
                ->unreadFor($userId)
                ->count();

            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $userId)
                ->update([
                    'last_read_message_id' => $uptoMessageId,
                    'last_read_at' => $now,
                    'unread_count' => $remaining,
                ]);

            return $affected;
        });
    }

    /**
     * Stamp delivery on messages the user is now receiving. Delivery is defined
     * as "reached the recipient's client", which is the moment they read the
     * thread while polling, or receive the socket frame once realtime lands.
     */
    public function markDelivered(int $conversationId, int $userId): void
    {
        Message::query()
            ->where('conversation_id', $conversationId)
            ->where('to_id', $userId)
            ->where('delivered_at', 0)
            ->update(['delivered_at' => time()]);
    }

    /**
     * Hide a single message from one side only, using the legacy per-side flags.
     */
    public function deleteMessageForUser(Message $message, int $userId): bool
    {
        if ((int) $message->from_id === $userId) {
            $message->deleted_one = Message::DELETED;
        } elseif ((int) $message->to_id === $userId) {
            $message->deleted_two = Message::DELETED;
        } else {
            return false;
        }

        return $message->save();
    }

    /**
     * Hide an entire thread from one side only. The other participant keeps their
     * copy, matching how the legacy flags were designed to work.
     */
    public function clearConversationForUser(Conversation $conversation, int $userId): void
    {
        $now = time();

        DB::transaction(function () use ($conversation, $userId, $now) {
            Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('from_id', $userId)
                ->update(['deleted_one' => Message::DELETED]);

            Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('to_id', $userId)
                ->update(['deleted_two' => Message::DELETED, 'seen' => DB::raw('IF(seen = 0, ' . $now . ', seen)')]);

            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $userId)
                ->update(['cleared_at' => $now, 'unread_count' => 0]);
        });
    }

    public function totalUnreadFor(int $userId): int
    {
        return (int) Message::query()->unreadFor($userId)->count();
    }

    public function preview(string $text, int $length = 120): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return mb_substr($text, 0, $length);
    }
}
