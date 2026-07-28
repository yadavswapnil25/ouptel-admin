<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Removes or hides a user's public content so they disappear from newsfeed
 * and the rest of the site after admin delete / deactivation.
 */
class UserContentPurger
{
    /**
     * Soft-hide content when account is deactivated/banned (active 0/2).
     */
    public static function deactivate(string|int $userId): void
    {
        $userId = (string) $userId;

        if (Schema::hasTable('Wo_Posts') && Schema::hasColumn('Wo_Posts', 'active')) {
            DB::table('Wo_Posts')->where('user_id', $userId)->update(['active' => '0']);
        }

        if (Schema::hasTable('Wo_UserStory') && Schema::hasColumn('Wo_UserStory', 'active')) {
            DB::table('Wo_UserStory')->where('user_id', $userId)->update(['active' => '0']);
        } elseif (Schema::hasTable('Wo_UserStory')) {
            // Fall back to delete stories if no active flag.
            self::deleteWhere('Wo_UserStory', 'user_id', $userId);
        }

        DB::table('Wo_AppsSessions')->where('user_id', $userId)->delete();
    }

    /**
     * Permanently remove user-owned content and relationships (admin hard delete).
     */
    public static function purge(string|int $userId): void
    {
        $userId = (string) $userId;

        try {
            DB::transaction(function () use ($userId) {
                self::purgePostsAndEngagement($userId);
                self::purgeStories($userId);
                self::purgeSocial($userId);
                self::purgePagesAndGroups($userId);
                self::purgeMisc($userId);
                self::deleteWhere('Wo_AppsSessions', 'user_id', $userId);
            });
        } catch (\Throwable $e) {
            Log::error('UserContentPurger::purge failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Hide orphan posts whose authors no longer exist (already-deleted users).
     */
    public static function deactivateOrphanPosts(): int
    {
        if (!Schema::hasTable('Wo_Posts') || !Schema::hasTable('Wo_Users')) {
            return 0;
        }

        return DB::table('Wo_Posts')
            ->where('active', '1')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('Wo_Users')
                    ->whereColumn('Wo_Users.user_id', 'Wo_Posts.user_id');
            })
            ->update(['active' => '0']);
    }

    private static function purgePostsAndEngagement(string $userId): void
    {
        if (!Schema::hasTable('Wo_Posts')) {
            return;
        }

        $postIds = DB::table('Wo_Posts')
            ->where('user_id', $userId)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->values()
            ->all();

        // Some schemas also use post_id as the public id.
        $postIdCol = Schema::hasColumn('Wo_Posts', 'post_id')
            ? DB::table('Wo_Posts')->where('user_id', $userId)->pluck('post_id')->filter()->map(fn ($id) => (string) $id)->all()
            : [];

        $allPostKeys = array_values(array_unique(array_merge($postIds, $postIdCol)));

        if (!empty($allPostKeys)) {
            self::deleteIn('Wo_Comments', 'post_id', $allPostKeys);
            self::deleteIn('Wo_Comment_Replies', 'post_id', $allPostKeys);
            self::deleteIn('Wo_CommentReplies', 'post_id', $allPostKeys);
            self::deleteIn('Wo_Reactions', 'post_id', $allPostKeys);
            self::deleteIn('Wo_PostReactions', 'post_id', $allPostKeys);
            self::deleteIn('Wo_Likes', 'post_id', $allPostKeys);
            self::deleteIn('Wo_SavedPosts', 'post_id', $allPostKeys);
            self::deleteIn('Wo_HiddenPosts', 'post_id', $allPostKeys);
            self::deleteIn('Wo_Notifications', 'post_id', $allPostKeys);
        }

        // Engagement authored by this user on others' posts.
        self::deleteWhere('Wo_Comments', 'user_id', $userId);
        self::deleteWhere('Wo_Reactions', 'user_id', $userId);
        self::deleteWhere('Wo_PostReactions', 'user_id', $userId);
        self::deleteWhere('Wo_Likes', 'user_id', $userId);
        self::deleteWhere('Wo_SavedPosts', 'user_id', $userId);
        self::deleteWhere('Wo_HiddenPosts', 'user_id', $userId);

        DB::table('Wo_Posts')->where('user_id', $userId)->delete();
    }

    private static function purgeStories(string $userId): void
    {
        if (!Schema::hasTable('Wo_UserStory')) {
            return;
        }

        $storyIds = DB::table('Wo_UserStory')->where('user_id', $userId)->pluck('id')->all();
        if (!empty($storyIds)) {
            self::deleteIn('Wo_UserStoryMedia', 'story_id', $storyIds);
            self::deleteIn('Wo_Story_Seen', 'story_id', $storyIds);
            self::deleteIn('Wo_Reactions', 'story_id', $storyIds);
        }
        self::deleteWhere('Wo_UserStory', 'user_id', $userId);
        self::deleteWhere('Wo_Story_Seen', 'user_id', $userId);
    }

    private static function purgeSocial(string $userId): void
    {
        if (Schema::hasTable('Wo_Followers')) {
            DB::table('Wo_Followers')
                ->where(function ($q) use ($userId) {
                    $q->where('follower_id', $userId)->orWhere('following_id', $userId);
                })
                ->delete();
        }

        if (Schema::hasTable('Wo_Friends')) {
            DB::table('Wo_Friends')
                ->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)->orWhere('friend_id', $userId);
                })
                ->delete();
        }

        foreach (['Wo_FriendRequests', 'Wo_Friend_Requests'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $cols = [];
            foreach (['sender_id', 'receiver_id', 'user_id', 'friend_id'] as $col) {
                if (Schema::hasColumn($table, $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols === []) {
                continue;
            }
            DB::table($table)
                ->where(function ($q) use ($cols, $userId) {
                    foreach ($cols as $index => $col) {
                        if ($index === 0) {
                            $q->where($col, $userId);
                        } else {
                            $q->orWhere($col, $userId);
                        }
                    }
                })
                ->delete();
        }

        if (Schema::hasTable('Wo_Blocks')) {
            DB::table('Wo_Blocks')
                ->where(function ($q) use ($userId) {
                    if (Schema::hasColumn('Wo_Blocks', 'blocker')) {
                        $q->where('blocker', $userId)->orWhere('blocked', $userId);
                    } else {
                        $q->where('blocker_id', $userId)->orWhere('blocked_id', $userId);
                    }
                })
                ->delete();
        }

        if (Schema::hasTable('Wo_Notifications')) {
            DB::table('Wo_Notifications')
                ->where(function ($q) use ($userId) {
                    $q->where('notifier_id', $userId)->orWhere('recipient_id', $userId);
                })
                ->delete();
        }

        if (Schema::hasTable('Wo_Messages')) {
            DB::table('Wo_Messages')
                ->where(function ($q) use ($userId) {
                    $q->where('from_id', $userId)->orWhere('to_id', $userId);
                })
                ->delete();
        }
    }

    private static function purgePagesAndGroups(string $userId): void
    {
        // Page likes / admin membership
        self::deleteWhere('Wo_Pages_Likes', 'user_id', $userId);
        self::deleteWhere('Wo_PageLikes', 'user_id', $userId);
        self::deleteWhere('Wo_Pages_Admin', 'user_id', $userId);

        // Owned pages + their posts
        if (Schema::hasTable('Wo_Pages')) {
            $pageIds = DB::table('Wo_Pages')->where('user_id', $userId)->pluck('page_id')->filter()->all();
            if (empty($pageIds) && Schema::hasColumn('Wo_Pages', 'id')) {
                $pageIds = DB::table('Wo_Pages')->where('user_id', $userId)->pluck('id')->filter()->all();
            }
            if (!empty($pageIds)) {
                self::deleteIn('Wo_Pages_Likes', 'page_id', $pageIds);
                self::deleteIn('Wo_Pages_Admin', 'page_id', $pageIds);
                if (Schema::hasTable('Wo_Posts') && Schema::hasColumn('Wo_Posts', 'page_id')) {
                    DB::table('Wo_Posts')->whereIn('page_id', $pageIds)->delete();
                }
                DB::table('Wo_Pages')->where('user_id', $userId)->delete();
            }
        }

        self::deleteWhere('Wo_Group_Members', 'user_id', $userId);
        self::deleteWhere('Wo_GroupMembers', 'user_id', $userId);
        self::deleteWhere('Wo_GroupAdmins', 'user_id', $userId);

        if (Schema::hasTable('Wo_Groups')) {
            DB::table('Wo_Groups')->where('user_id', $userId)->delete();
        }
    }

    private static function purgeMisc(string $userId): void
    {
        self::deleteWhere('Wo_UserAddress', 'user_id', $userId);
        self::deleteWhere('user_community_preferences', 'user_id', $userId);
        self::deleteWhere('Wo_Verification_Requests', 'user_id', $userId);
        self::deleteWhere('Wo_Reports', 'user_id', $userId);
        self::deleteWhere('Wo_Reports', 'reported', $userId);
        self::deleteWhere('Wo_AccountDeletionRequests', 'user_id', $userId);
        self::deleteWhere('Wo_Blog', 'user', $userId);
        self::deleteWhere('Wo_Blogs', 'user', $userId);
        self::deleteWhere('Wo_Products', 'user_id', $userId);
        self::deleteWhere('Wo_UserAds', 'user_id', $userId);
        self::deleteWhere('Wo_Events', 'poster_id', $userId);
        self::deleteWhere('Wo_Events', 'user_id', $userId);

        // Best-effort media cleanup for avatar/cover paths stored on the user row (if still readable).
        try {
            $user = DB::table('Wo_Users')->where('user_id', $userId)->first(['avatar', 'cover']);
            if ($user) {
                foreach (['avatar', 'cover'] as $field) {
                    $path = $user->{$field} ?? null;
                    if ($path && is_string($path) && !str_starts_with($path, 'http') && Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }
        } catch (\Throwable $e) {
            // non-fatal
        }
    }

    private static function deleteWhere(string $table, string $column, string|int $value): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }
        DB::table($table)->where($column, $value)->delete();
    }

    private static function deleteIn(string $table, string $column, array $values): void
    {
        if (empty($values) || !Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }
        DB::table($table)->whereIn($column, $values)->delete();
    }
}
