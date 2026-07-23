<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->unsignedBigInteger('press_id')->nullable()->after('author_id')->index();
            $table->foreign('press_id')
                ->references('id')
                ->on('news_press_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropForeign(['press_id']);
            $table->dropColumn('press_id');
        });
    }
};
