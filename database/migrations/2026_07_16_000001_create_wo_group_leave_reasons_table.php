<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('Wo_Group_Leave_Reasons')) {
            return;
        }

        Schema::create('Wo_Group_Leave_Reasons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('group_id')->index();
            $table->string('user_id', 32)->index();
            $table->string('reason', 500);
            $table->string('time', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Wo_Group_Leave_Reasons');
    }
};
