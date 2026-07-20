<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('Wo_Blog_Channels')) {
            Schema::create('Wo_Blog_Channels', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('user_id', 32)->index();
                $table->string('name', 255);
                $table->string('slug', 255)->nullable()->unique();
                $table->text('description')->nullable();
                $table->string('avatar', 500)->nullable();
                $table->string('cover', 500)->nullable();
                $table->string('active', 1)->default('1')->index();
                $table->unsignedBigInteger('time')->default(0)->index();
            });
        }

        if (!Schema::hasTable('Wo_Blog_Channel_Followers')) {
            Schema::create('Wo_Blog_Channel_Followers', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('channel_id')->index();
                $table->string('user_id', 32)->index();
                $table->unsignedBigInteger('time')->default(0);
                $table->unique(['channel_id', 'user_id'], 'blog_channel_follower_unique');
            });
        }

        if (Schema::hasTable('Wo_Blog') && !Schema::hasColumn('Wo_Blog', 'channel_id')) {
            Schema::table('Wo_Blog', function (Blueprint $table) {
                $table->unsignedBigInteger('channel_id')->nullable()->index()->after('category');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('Wo_Blog') && Schema::hasColumn('Wo_Blog', 'channel_id')) {
            Schema::table('Wo_Blog', function (Blueprint $table) {
                $table->dropColumn('channel_id');
            });
        }

        Schema::dropIfExists('Wo_Blog_Channel_Followers');
        Schema::dropIfExists('Wo_Blog_Channels');
    }
};
