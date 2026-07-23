<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_press_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('press_id')->constrained('news_press_profiles')->cascadeOnDelete();
            $table->integer('user_id')->index();
            $table->foreign('user_id')->references('user_id')->on('Wo_Users')->cascadeOnDelete();
            $table->foreignId('editor_id')->nullable()->constrained('news_editors')->nullOnDelete();
            $table->enum('role', ['owner', 'member'])->default('member')->index();
            $table->enum('status', ['active', 'removed'])->default('active')->index();
            $table->integer('invited_by')->nullable();
            $table->foreign('invited_by')->references('user_id')->on('Wo_Users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['press_id', 'user_id'], 'news_press_members_press_user_unique');
        });

        // Backfill existing press owners as active owner members.
        if (Schema::hasTable('news_press_profiles')) {
            $now = now();
            $rows = DB::table('news_press_profiles')->select('id', 'user_id', 'editor_id', 'created_at')->get();
            foreach ($rows as $press) {
                DB::table('news_press_members')->updateOrInsert(
                    [
                        'press_id' => $press->id,
                        'user_id' => $press->user_id,
                    ],
                    [
                        'editor_id' => $press->editor_id,
                        'role' => 'owner',
                        'status' => 'active',
                        'invited_by' => $press->user_id,
                        'joined_at' => $press->created_at ?? $now,
                        'removed_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('news_press_members');
    }
};
