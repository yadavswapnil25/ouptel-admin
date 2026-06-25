<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Drops and recreates the Wo_Verification_Requests table to support Blue & Golden badge verification
     */
    public function up(): void
    {
        // Drop table if it exists to ensure clean migration
        Schema::dropIfExists('Wo_Verification_Requests');
        
        // Create fresh table
        Schema::create('Wo_Verification_Requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('user_name')->nullable()->comment('User full name');
            $table->string('type', 100)->default('User')->comment('Verification type');
            
            // ID Proof fields
            $table->string('id_proof_type', 50)->nullable()->comment('Type of ID proof: aadhar, voter_id, passport, driving_license, pan_card');
            $table->string('id_proof_number', 100)->nullable()->comment('ID Proof number');
            $table->string('id_proof_front_image', 255)->nullable()->comment('Front image of ID proof');
            $table->string('id_proof_back_image', 255)->nullable()->comment('Back image of ID proof');
            
            // Badge type
            $table->enum('badge_type', ['blue', 'golden'])->nullable()->comment('Type of badge requested: blue (regular users) or golden (VIPs/celebrities)');
            
            // Verification status
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->comment('Verification status');
            $table->string('rejection_reason', 255)->nullable()->comment('Reason for rejection if status is rejected');
            
            // Timestamps
            $table->timestamp('submitted_at')->nullable()->comment('When the verification was submitted');
            $table->timestamp('reviewed_at')->nullable()->comment('When the verification was reviewed');
                $table->timestamp('approved_at')->nullable()->comment('When the verification was approved');
                $table->unsignedBigInteger('reviewed_by')->nullable()->comment('Admin user who reviewed the verification');
                $table->tinyInteger('seen')->default(0)->comment('Whether admin has seen this request');
                
                // Video verification fields
                $table->string('verification_video', 255)->nullable()->comment('Path to verification video file');
                $table->integer('video_size')->nullable()->comment('Video file size in bytes');
                $table->integer('video_duration')->nullable()->comment('Video duration in seconds');
                $table->timestamp('video_uploaded_at')->nullable()->comment('When the video was uploaded');
            
            // Indexes for faster queries
            $table->index(['user_id', 'status'], 'idx_user_status');
            $table->index(['badge_type', 'status'], 'idx_badge_status');
            $table->index('status', 'idx_status');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Wo_Verification_Requests');
    }
};

