<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Default all users without a country to India (WoWonder country_id 99).
     */
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Users') || !Schema::hasColumn('Wo_Users', 'country_id')) {
            return;
        }

        DB::table('Wo_Users')
            ->where(function ($query) {
                $query->whereNull('country_id')
                    ->orWhere('country_id', 0)
                    ->orWhere('country_id', '0')
                    ->orWhere('country_id', '');
            })
            ->update(['country_id' => 99]);
    }

    public function down(): void
    {
        // Irreversible data backfill — intentionally left empty.
    }
};
