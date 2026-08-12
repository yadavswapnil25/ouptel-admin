<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wo_Messages arrives from the legacy schema carrying roughly forty indexes,
     * most of which are byte-for-byte duplicates: order4/5/6 repeat order1/2/3,
     * and six single-column indexes are each defined four times over. Every
     * duplicate is written on insert while adding no read capability, which is
     * the wrong trade on the most insert-heavy table in the application.
     *
     * Only exact duplicates are dropped. Single-column indexes that merely
     * duplicate the leading column of a composite index (from_id, to_id, seen,
     * time, group_id) are deliberately kept, because a narrower index is cheaper
     * for the optimiser to scan and removing them changes query plans.
     */
    private const DUPLICATES = [
        'order4' => ['from_id', 'id'],
        'order5' => ['group_id', 'id'],
        'order6' => ['to_id', 'id'],
        'reply_id_2' => ['reply_id'],
        'reply_id_3' => ['reply_id'],
        'reply_id_4' => ['reply_id'],
        'broadcast_id_2' => ['broadcast_id'],
        'broadcast_id_3' => ['broadcast_id'],
        'broadcast_id_4' => ['broadcast_id'],
        'story_id_2' => ['story_id'],
        'story_id_3' => ['story_id'],
        'story_id_4' => ['story_id'],
        'product_id_2' => ['product_id'],
        'product_id_3' => ['product_id'],
        'product_id_4' => ['product_id'],
        'notification_id_2' => ['notification_id'],
        'notification_id_3' => ['notification_id'],
        'notification_id_4' => ['notification_id'],
        'page_id_2' => ['page_id'],
        'page_id_3' => ['page_id'],
        'page_id_4' => ['page_id'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('Wo_Messages')) {
            return;
        }

        foreach (array_keys(self::DUPLICATES) as $index) {
            if (! $this->indexExists('Wo_Messages', $index)) {
                continue;
            }

            Schema::table('Wo_Messages', function (Blueprint $table) use ($index) {
                $table->dropIndex($index);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('Wo_Messages')) {
            return;
        }

        foreach (self::DUPLICATES as $index => $columns) {
            if ($this->indexExists('Wo_Messages', $index)) {
                continue;
            }

            Schema::table('Wo_Messages', function (Blueprint $table) use ($index, $columns) {
                $table->index($columns, $index);
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};
