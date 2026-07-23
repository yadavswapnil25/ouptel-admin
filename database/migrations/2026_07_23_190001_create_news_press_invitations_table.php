<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_press_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('press_id')->constrained('news_press_profiles')->cascadeOnDelete();
            $table->string('email', 191)->index();
            $table->string('token', 64)->unique();
            $table->integer('invited_by')->nullable();
            $table->foreign('invited_by')->references('user_id')->on('Wo_Users')->nullOnDelete();
            $table->enum('status', ['pending', 'accepted', 'cancelled', 'expired'])->default('pending')->index();
            $table->foreignId('application_id')->nullable()->constrained('news_editor_applications')->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['press_id', 'email']);
        });

        Schema::table('news_editor_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('news_editor_applications', 'press_invitation_id')) {
                $table->foreignId('press_invitation_id')
                    ->nullable()
                    ->after('credentials_sent_at')
                    ->constrained('news_press_invitations')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('news_editor_applications', function (Blueprint $table) {
            if (Schema::hasColumn('news_editor_applications', 'press_invitation_id')) {
                $table->dropConstrainedForeignId('press_invitation_id');
            }
        });

        Schema::dropIfExists('news_press_invitations');
    }
};
