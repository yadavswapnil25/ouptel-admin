<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FriendActivityNotificationService
{
    /**
     * Notify the author's friends about a new feed post.
     */
    public static function notifyFriendsOfNewPost(Post $post, string $authorId): void
    {
        try {
            if (!Schema::hasTable('Wo_Notifications') || !self::isGlobalNotifyEnabled('notify_new_post')) {
                return;
            }

            if (!self::shouldNotifyFriendsForPost($post)) {
                return;
            }

            $friendIds = self::getFriendIds($authorId);
            if ($friendIds === []) {
                return;
            }

            $recipientId = (int) ($post->recipient_id ?? 0);
            $authorIdInt = (int) $authorId;
            if ($recipientId > 0 && $recipientId !== $authorIdInt) {
                $friendIds = array_values(array_diff($friendIds, [(string) $recipientId]));
            }

            $friendIds = self::filterRecipientsAllowingAuthorNotifications($authorId, $friendIds);
            if ($friendIds === []) {
                return;
            }

            $postPublicId = $post->post_id ?? $post->id;
            $postType = strtolower((string) ($post->postType ?? ''));
            $type2 = self::mapPostTypeToNotificationType2($postType);

            self::insertFriendNotifications(
                notifierId: $authorIdInt,
                recipientIds: $friendIds,
                type: 'posted',
                url: '/post/' . $postPublicId,
                extra: [
                    'post_id' => $postPublicId,
                    'type2' => $type2,
                    'text' => self::truncateText((string) ($post->postText ?? '')),
                    'page_id' => (int) ($post->page_id ?? 0),
                    'group_id' => (int) ($post->group_id ?? 0),
                    'event_id' => 0,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('notifyFriendsOfNewPost failed: ' . $e->getMessage());
        }
    }

    /**
     * Notify friends + followers about a new story/vibe.
     */
    public static function notifyFriendsOfNewStory(int $storyId, string $authorId): void
    {
        try {
            if (!Schema::hasTable('Wo_Notifications')) {
                return;
            }

            // Stories are always notified unless explicitly disabled.
            if (!self::isGlobalNotifyEnabled('notify_new_story')) {
                return;
            }

            $recipientIds = self::getStoryRecipientIds($authorId);
            if ($recipientIds === []) {
                Log::info('notifyFriendsOfNewStory: no recipients for author ' . $authorId);
                return;
            }

            $recipientIds = self::filterRecipientsAllowingAuthorNotifications($authorId, $recipientIds);
            if ($recipientIds === []) {
                return;
            }

            // Prefer numeric profile id for SPA routes (/profile/:id).
            $url = '/profile/' . $authorId;

            $extra = [
                'post_id' => 0,
                'page_id' => 0,
                'group_id' => 0,
                'event_id' => 0,
                'text' => '',
            ];

            if (Schema::hasColumn('Wo_Notifications', 'story_id')) {
                $extra['story_id'] = $storyId;
            }

            self::insertFriendNotifications(
                notifierId: (int) $authorId,
                recipientIds: $recipientIds,
                type: 'new_story',
                url: $url,
                extra: $extra,
            );
        } catch (\Throwable $e) {
            Log::warning('notifyFriendsOfNewStory failed: ' . $e->getMessage());
        }
    }

    /**
     * Friends + people who follow the author (for story alerts).
     * Also includes people the author follows when that follow is active,
     * so one-sided follow edges still get story alerts in both directions
     * when either user would see the other's vibes in the feed.
     *
     * @return list<string>
     */
    public static function getStoryRecipientIds(string $userId): array
    {
        $ids = self::getFriendIds($userId);
        $ids = array_values(array_unique(array_merge(
            $ids,
            self::getFollowerIds($userId),
            self::getFollowingIds($userId),
        )));
        $ids = array_values(array_diff($ids, [$userId]));

        $blockedIds = self::getBlockedUserIds($userId);
        if ($blockedIds !== []) {
            $ids = array_values(array_diff($ids, $blockedIds));
        }

        return $ids;
    }

    /**
     * Active accounts this user follows.
     *
     * @return list<string>
     */
    public static function getFollowingIds(string $userId): array
    {
        if (!Schema::hasTable('Wo_Followers')) {
            return [];
        }

        return DB::table('Wo_Followers')
            ->where('follower_id', $userId)
            ->where(function ($q) {
                $q->where('active', '=', '1')
                    ->orWhere('active', '=', 1);
            })
            ->pluck('following_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function getFriendIds(string $userId): array
    {
        $friendIds = [];

        if (Schema::hasTable('Wo_Followers')) {
            $followingIds = DB::table('Wo_Followers')
                ->where('follower_id', $userId)
                ->where(function ($q) {
                    $q->where('active', '=', '1')
                        ->orWhere('active', '=', 1);
                })
                ->pluck('following_id')
                ->map(fn ($id) => (string) $id)
                ->toArray();

            $followerIds = DB::table('Wo_Followers')
                ->where('following_id', $userId)
                ->where(function ($q) {
                    $q->where('active', '=', '1')
                        ->orWhere('active', '=', 1);
                })
                ->pluck('follower_id')
                ->map(fn ($id) => (string) $id)
                ->toArray();

            $friendIds = array_values(array_unique(array_intersect($followingIds, $followerIds)));
        }

        $friendIds = array_values(array_unique(array_merge($friendIds, self::getAcceptedWoFriendsPartnerIds($userId))));
        $friendIds = array_values(array_diff($friendIds, [$userId]));

        $blockedIds = self::getBlockedUserIds($userId);
        if ($blockedIds !== []) {
            $friendIds = array_values(array_diff($friendIds, $blockedIds));
        }

        return $friendIds;
    }

    /**
     * Active followers of the author.
     *
     * @return list<string>
     */
    public static function getFollowerIds(string $userId): array
    {
        if (!Schema::hasTable('Wo_Followers')) {
            return [];
        }

        return DB::table('Wo_Followers')
            ->where('following_id', $userId)
            ->where(function ($q) {
                $q->where('active', '=', '1')
                    ->orWhere('active', '=', 1);
            })
            ->pluck('follower_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private static function shouldNotifyFriendsForPost(Post $post): bool
    {
        if ((string) ($post->send_notify ?? '1') === '0') {
            return false;
        }

        $privacy = (string) ($post->postPrivacy ?? '0');
        if (in_array($privacy, ['2', '3'], true)) {
            return false;
        }

        if ((int) ($post->page_id ?? 0) > 0) {
            return false;
        }

        if ((int) ($post->group_id ?? 0) > 0 && in_array($privacy, ['4'], true)) {
            return false;
        }

        return (string) ($post->active ?? '1') !== '0';
    }

    /**
     * Drop recipients who muted the author — but never mute accepted friends.
     * (Wo_Followers.notify often defaults to 0 on insert, which incorrectly
     * blocked one side of a friendship from receiving story/post alerts.)
     *
     * @param  list<string>  $recipientIds
     * @return list<string>
     */
    private static function filterRecipientsAllowingAuthorNotifications(string $authorId, array $recipientIds): array
    {
        if ($recipientIds === [] || !Schema::hasTable('Wo_Followers')) {
            return $recipientIds;
        }

        if (!Schema::hasColumn('Wo_Followers', 'notify')) {
            return $recipientIds;
        }

        try {
            $mutedFollowerIds = DB::table('Wo_Followers')
                ->where('following_id', $authorId)
                ->whereIn('follower_id', $recipientIds)
                ->where(function ($q) {
                    $q->where('notify', 0)->orWhere('notify', '0');
                })
                ->pluck('follower_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            if ($mutedFollowerIds === []) {
                return $recipientIds;
            }

            // Accepted friends always receive friend-activity notifications.
            $friendIds = self::getFriendIds($authorId);
            $friendSet = array_fill_keys($friendIds, true);
            $mutedNonFriends = array_values(array_filter(
                $mutedFollowerIds,
                static fn (string $id): bool => !isset($friendSet[$id])
            ));

            if ($mutedNonFriends === []) {
                return $recipientIds;
            }

            return array_values(array_diff($recipientIds, $mutedNonFriends));
        } catch (\Throwable $e) {
            return $recipientIds;
        }
    }

    /**
     * @param  list<string>  $recipientIds
     * @param  array<string, mixed>  $extra
     */
    private static function insertFriendNotifications(
        int $notifierId,
        array $recipientIds,
        string $type,
        string $url,
        array $extra = [],
    ): void {
        if ($recipientIds === []) {
            return;
        }

        $now = time();
        $inserted = 0;

        foreach ($recipientIds as $recipientId) {
            if ($recipientId === '' || (int) $recipientId === $notifierId) {
                continue;
            }

            $row = [
                'notifier_id' => $notifierId,
                'recipient_id' => (int) $recipientId,
                'type' => $type,
                'url' => $extra['url'] ?? $url,
                'time' => $now,
                'seen' => 0,
            ];

            foreach (['post_id', 'type2', 'text', 'page_id', 'group_id', 'event_id', 'story_id'] as $column) {
                if (array_key_exists($column, $extra) && self::notificationColumnExists($column)) {
                    $row[$column] = $extra[$column];
                }
            }

            // Ensure common NOT NULL numeric columns have defaults when present.
            foreach (['post_id', 'page_id', 'group_id', 'event_id'] as $requiredNumeric) {
                if (self::notificationColumnExists($requiredNumeric) && !array_key_exists($requiredNumeric, $row)) {
                    $row[$requiredNumeric] = 0;
                }
            }

            try {
                DB::table('Wo_Notifications')->insert($row);
                $inserted++;
            } catch (\Throwable $e) {
                Log::warning('Failed to insert friend activity notification: ' . $e->getMessage(), [
                    'type' => $type,
                    'notifier_id' => $notifierId,
                    'recipient_id' => $recipientId,
                ]);
            }
        }

        if ($inserted === 0 && $recipientIds !== []) {
            Log::warning('Friend activity notifications inserted 0 rows', [
                'type' => $type,
                'notifier_id' => $notifierId,
                'recipient_count' => count($recipientIds),
            ]);
        }
    }

    private static function isGlobalNotifyEnabled(string $settingName): bool
    {
        foreach (['Wo_Config', 'settings'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            try {
                $value = DB::table($table)->where('name', $settingName)->value('value');
                if ($value !== null) {
                    return !in_array((string) $value, ['0', 'off', 'false'], true);
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        // Missing setting = enabled by default.
        return true;
    }

    /**
     * @return list<string>
     */
    private static function getBlockedUserIds(string $userId): array
    {
        if (!Schema::hasTable('Wo_Blocks')) {
            return [];
        }

        return DB::table('Wo_Blocks')
            ->where('blocker', $userId)
            ->pluck('blocked')
            ->merge(DB::table('Wo_Blocks')->where('blocked', $userId)->pluck('blocker'))
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function getAcceptedWoFriendsPartnerIds(string $userId): array
    {
        if (!Schema::hasTable('Wo_Friends')) {
            return [];
        }

        try {
            if (Schema::hasColumn('Wo_Friends', 'user_id')
                && Schema::hasColumn('Wo_Friends', 'friend_id')
                && Schema::hasColumn('Wo_Friends', 'status')) {
                return DB::table('Wo_Friends')
                    ->where('user_id', $userId)
                    ->whereIn('status', ['2', 2])
                    ->pluck('friend_id')
                    ->merge(
                        DB::table('Wo_Friends')
                            ->where('friend_id', $userId)
                            ->whereIn('status', ['2', 2])
                            ->pluck('user_id')
                    )
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->values()
                    ->all();
            }

            if (Schema::hasColumn('Wo_Friends', 'from_id')
                && Schema::hasColumn('Wo_Friends', 'to_id')
                && Schema::hasColumn('Wo_Friends', 'status')) {
                return DB::table('Wo_Friends')
                    ->where('from_id', $userId)
                    ->whereIn('status', ['2', 2])
                    ->pluck('to_id')
                    ->merge(
                        DB::table('Wo_Friends')
                            ->where('to_id', $userId)
                            ->whereIn('status', ['2', 2])
                            ->pluck('from_id')
                    )
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->values()
                    ->all();
            }
        } catch (\Throwable $e) {
            return [];
        }

        return [];
    }

    private static function mapPostTypeToNotificationType2(string $postType): string
    {
        return match ($postType) {
            'photo', 'album' => 'post_image',
            'video', 'gif' => 'post_video',
            'file' => 'post_file',
            'audio' => 'post_soundFile',
            default => 'post',
        };
    }

    private static function truncateText(string $text): string
    {
        $text = trim(strip_tags($text));
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) > 120) {
            return mb_substr($text, 0, 117) . '...';
        }

        return $text;
    }

    private static function notificationColumnExists(string $column): bool
    {
        static $columns = null;

        if ($columns === null) {
            $columns = Schema::hasTable('Wo_Notifications')
                ? Schema::getColumnListing('Wo_Notifications')
                : [];
        }

        return in_array($column, $columns, true);
    }
}
