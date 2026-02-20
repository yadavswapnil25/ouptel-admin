<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();  // e.g. 'manage-users'
            $table->string('label');           // e.g. 'Manage Users'
            $table->string('group')->nullable(); // navigation group, e.g. 'Users'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_permissions');
    }
};
