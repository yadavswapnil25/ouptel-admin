<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('Wo_Blog_Images')) {
            return;
        }

        Schema::create('Wo_Blog_Images', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('blog_id');
            $table->string('image', 500);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('created_at')->default(0);

            $table->index('blog_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Wo_Blog_Images');
    }
};
