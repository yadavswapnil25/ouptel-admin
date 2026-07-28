<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('Wo_AppsSessions') || !Schema::hasTable('Wo_Users')) {
            return;
        }

        // Remove sessions for users that no longer exist (admin deletes).
        DB::statement('
            DELETE s FROM Wo_AppsSessions s
            LEFT JOIN Wo_Users u ON u.user_id = s.user_id
            WHERE u.user_id IS NULL
        ');

        // Remove sessions for inactive / banned accounts.
        DB::table('Wo_AppsSessions')
            ->whereIn('user_id', function ($query) {
                $query->select('user_id')
                    ->from('Wo_Users')
                    ->whereIn('active', ['0', '2']);
            })
            ->delete();
    }

    public function down(): void
    {
        // Irreversible cleanup.
    }
};
