<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');  // references Wo_Users.user_id
            $table->unsignedBigInteger('role_id');

            $table->foreign('role_id')->references('id')->on('admin_roles')->cascadeOnDelete();

            $table->unique(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_roles');
    }
};
