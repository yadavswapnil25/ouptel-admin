<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Verification_Requests')) {
            return;
        }

        Schema::table('Wo_Verification_Requests', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Verification_Requests', 'relationship_type')) {
                $table->string('relationship_type', 50)->nullable()->after('id_proof_type');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_Verification_Requests')) {
            return;
        }

        Schema::table('Wo_Verification_Requests', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Verification_Requests', 'relationship_type')) {
                $table->dropColumn('relationship_type');
            }
        });
    }
};
