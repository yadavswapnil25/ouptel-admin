<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Job_Apply')) {
            return;
        }

        Schema::table('Wo_Job_Apply', function (Blueprint $table) {
            if (!Schema::hasColumn('Wo_Job_Apply', 'qualification')) {
                $table->string('qualification', 255)->nullable()->default('')->after('email');
            }
            if (!Schema::hasColumn('Wo_Job_Apply', 'resume')) {
                $table->string('resume', 500)->nullable()->default('')->after('qualification');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('Wo_Job_Apply')) {
            return;
        }

        Schema::table('Wo_Job_Apply', function (Blueprint $table) {
            if (Schema::hasColumn('Wo_Job_Apply', 'resume')) {
                $table->dropColumn('resume');
            }
            if (Schema::hasColumn('Wo_Job_Apply', 'qualification')) {
                $table->dropColumn('qualification');
            }
        });
    }
};
