<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('Wo_UserAds')) {
            Schema::create('Wo_UserAds', function (Blueprint $table) {
                $table->increments('id');
                $table->string('user_id', 32)->default('0')->index();
                $table->unsignedInteger('page_id')->default(0);
                $table->string('name', 255)->default('');
                $table->string('url', 512)->default('');
                $table->string('headline', 255)->default('');
                $table->text('description')->nullable();
                $table->string('location', 255)->default('');
                $table->text('audience')->nullable();
                $table->string('gender', 32)->default('all');
                $table->string('bidding', 32)->default('views');
                $table->string('appears', 32)->default('post');
                $table->string('ad_media', 512)->default('');
                $table->decimal('budget', 12, 2)->default(0);
                $table->string('start', 32)->default('');
                $table->string('end', 32)->default('');
                $table->unsignedInteger('posted')->default(0);
                $table->string('status', 32)->default('active');
                $table->unsignedInteger('views')->default(0);
                $table->unsignedInteger('clicks')->default(0);
            });
        }

        if (!Schema::hasTable('Wo_Boost_Campaigns')) {
            Schema::create('Wo_Boost_Campaigns', function (Blueprint $table) {
                $table->increments('id');
                $table->string('user_id', 32)->index();
                $table->unsignedInteger('post_id')->index();
                $table->string('goal', 64)->default('reach');
                $table->string('audience_gender', 32)->default('all');
                $table->text('audience_countries')->nullable();
                $table->unsignedSmallInteger('duration_days')->default(7);
                $table->decimal('budget', 12, 2)->default(0);
                $table->string('status', 32)->default('draft');
                $table->unsignedInteger('starts_at')->default(0);
                $table->unsignedInteger('ends_at')->default(0);
                $table->unsignedInteger('created_at')->default(0);
                $table->unsignedInteger('updated_at')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('Wo_Boost_Campaigns');
        Schema::dropIfExists('Wo_UserAds');
    }
};
