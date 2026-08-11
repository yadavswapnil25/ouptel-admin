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
    }

    /**
     * Notify the author's friends about a new story.
     */
    public static function notifyFriendsOfNewStory(int $storyId, string $authorId): void
    {
        if (!Schema::hasTable('Wo_Notifications')) {
            return;
        }

        if (!self::isGlobalNotifyEnabled('notify_new_post') && !self::isGlobalNotifyEnabled('notify_new_story')) {
            return;
        }

        $friendIds = self::getFriendIds($authorId);
        if ($friendIds === []) {
            return;
        }

        $friendIds = self::filterRecipientsAllowingAuthorNotifications($authorId, $friendIds);
        if ($friendIds === []) {
            return;
        }

        $author = DB::table('Wo_Users')->where('user_id', $authorId)->first(['username']);
        $username = trim((string) ($author->username ?? ''));
        $url = $username !== ''
            ? 'index.php?link1=timeline&u=' . $username
            : '/profile/' . $authorId;

        $extra = [
            'url' => $url,
            'type2' => 'story',
        ];

        if (Schema::hasColumn('Wo_Notifications', 'story_id')) {
            $extra['story_id'] = $storyId;
        }

        self::insertFriendNotifications(
            notifierId: (int) $authorId,
            recipientIds: $friendIds,
            type: 'new_story',
            url: $url,
            extra: $extra,
        );
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
     * @param  list<string>  $recipientIds
     * @return list<string>
     */
    private static function filterRecipientsAllowingAuthorNotifications(string $authorId, array $recipientIds): array
    {
        if ($recipientIds === [] || !Schema::hasTable('Wo_Followers')) {
            return $recipientIds;
        }

        $mutedRecipientIds = DB::table('Wo_Followers')
            ->where('following_id', $authorId)
            ->whereIn('follower_id', $recipientIds)
            ->where(function ($q) {
                $q->where('notify', 0)->orWhere('notify', '0');
            })
            ->pluck('follower_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($mutedRecipientIds === []) {
            return $recipientIds;
        }

        return array_values(array_diff($recipientIds, $mutedRecipientIds));
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
        $rows = [];

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

            $rows[] = $row;
        }

        if ($rows === []) {
            return;
        }

        try {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('Wo_Notifications')->insert($chunk);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to create friend activity notifications: ' . $e->getMessage());
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
            } catch (\Exception $e) {
                continue;
            }
        }

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
        } catch (\Exception $e) {
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
