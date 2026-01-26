<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Updates the Wo_Verification_Requests table to support Blue & Golden badge verification
     */
    public function up(): void
    {
        Schema::table('Wo_Verification_Requests', function (Blueprint $table) {
            // ID Proof fields
            $table->string('id_proof_type', 50)->nullable()->after('type')->comment('Type of ID proof: aadhar, voter_id, passport, driving_license, pan_card');
            $table->string('id_proof_number', 100)->nullable()->after('id_proof_type')->comment('ID Proof number');
            $table->string('id_proof_front_image', 255)->nullable()->after('id_proof_number')->comment('Front image of ID proof');
            $table->string('id_proof_back_image', 255)->nullable()->after('id_proof_front_image')->comment('Back image of ID proof');
            
            // Badge type
            $table->enum('badge_type', ['blue', 'golden'])->default('blue')->after('id_proof_back_image')->comment('Type of badge requested: blue (regular users) or golden (VIPs/celebrities)');
            
            // Verification status
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('badge_type')->comment('Verification status');
            $table->string('rejection_reason', 255)->nullable()->after('status')->comment('Reason for rejection if status is rejected');
            
            // Timestamps
            $table->timestamp('submitted_at')->nullable()->after('rejection_reason')->comment('When the verification was submitted');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at')->comment('When the verification was reviewed');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at')->comment('Admin user who reviewed the verification');
            
            // Index for faster queries
            $table->index(['user_id', 'status'], 'idx_user_status');
            $table->index(['badge_type', 'status'], 'idx_badge_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Wo_Verification_Requests', function (Blueprint $table) {
            $table->dropIndex('idx_user_status');
            $table->dropIndex('idx_badge_status');
            
            $table->dropColumn([
                'id_proof_type',
                'id_proof_number',
                'id_proof_front_image',
                'id_proof_back_image',
                'badge_type',
                'status',
                'rejection_reason',
                'submitted_at',
                'reviewed_at',
                'reviewed_by',
            ]);
        });
    }
};

