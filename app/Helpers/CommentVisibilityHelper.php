<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hide comments/replies whose author was deleted or deactivated.
 */
class CommentVisibilityHelper
{
    /**
     * Count visible comments in Wo_Comments (author still exists and is active).
     */
    public static function countForPost(int $postId): int
    {
        if (!Schema::hasTable('Wo_Comments') || !Schema::hasTable('Wo_Users')) {
            return 0;
        }

        try {
            $query = DB::table('Wo_Comments as c')
                ->join('Wo_Users as u', 'u.user_id', '=', 'c.user_id')
                ->where('c.post_id', $postId);

            self::constrainActiveUser($query, 'u');

            return (int) $query->count('c.id');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Apply "user still exists and is active" to a query already joined to users.
     */
    public static function constrainActiveUser($query, string $usersAlias = 'u'): void
    {
        $query->where(function ($q) use ($usersAlias) {
            $q->where("{$usersAlias}.active", '1')
                ->orWhere("{$usersAlias}.active", 1);
        });
    }

    /**
     * Eloquent whereHas callback: comment author exists and is active.
     */
    public static function authorIsVisible(): \Closure
    {
        return function ($q) {
            $q->where(function ($inner) {
                $inner->where('active', '1')->orWhere('active', 1);
            });
        };
    }
}
