<?php

use App\Services\UserContentPurger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Soft-hide posts whose authors were already deleted from Wo_Users.
        if (Schema::hasTable('Wo_Posts') && Schema::hasTable('Wo_Users')) {
            UserContentPurger::deactivateOrphanPosts();

            // Also hide posts from inactive/banned accounts still in the feed.
            DB::table('Wo_Posts')
                ->where('active', '1')
                ->whereIn('user_id', function ($q) {
                    $q->select('user_id')
                        ->from('Wo_Users')
                        ->whereIn('active', ['0', '2']);
                })
                ->update(['active' => '0']);
        }
    }

    public function down(): void
    {
        // Irreversible content hide.
    }
};
