<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Updates/Creates the Wo_Verification_Requests table to support Blue & Golden badge verification
     */
    public function up(): void
    {
        // Create table if it doesn't exist
        if (!Schema::hasTable('Wo_Verification_Requests')) {
            Schema::create('Wo_Verification_Requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                
                // ID Proof fields
                $table->string('id_proof_type', 50)->nullable()->comment('Type of ID proof: aadhar, voter_id, passport, driving_license, pan_card');
                $table->string('id_proof_number', 100)->nullable()->comment('ID Proof number');
                $table->string('id_proof_front_image', 255)->nullable()->comment('Front image of ID proof');
                $table->string('id_proof_back_image', 255)->nullable()->comment('Back image of ID proof');
                
                // Badge type
                $table->enum('badge_type', ['blue', 'golden'])->default('blue')->comment('Type of badge requested: blue (regular users) or golden (VIPs/celebrities)');
                
                // Verification status
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->comment('Verification status');
                $table->string('rejection_reason', 255)->nullable()->comment('Reason for rejection if status is rejected');
                
                // Timestamps
                $table->timestamp('submitted_at')->nullable()->comment('When the verification was submitted');
                $table->timestamp('reviewed_at')->nullable()->comment('When the verification was reviewed');
                $table->timestamp('approved_at')->nullable()->comment('When the verification was approved');
                $table->unsignedBigInteger('reviewed_by')->nullable()->comment('Admin user who reviewed the verification');
                
                // Indexes for faster queries
                $table->index(['user_id', 'status'], 'idx_user_status');
                $table->index(['badge_type', 'status'], 'idx_badge_status');
                $table->index('status', 'idx_status');
                
                $table->timestamps();
            });
        } else {
            // If table exists, update it with any missing columns
            Schema::table('Wo_Verification_Requests', function (Blueprint $table) {
                // Check and add columns if they don't exist
                if (!Schema::hasColumn('Wo_Verification_Requests', 'id_proof_type')) {
                    $table->string('id_proof_type', 50)->nullable()->after('type')->comment('Type of ID proof: aadhar, voter_id, passport, driving_license, pan_card');
                }
                if (!Schema::hasColumn('Wo_Verification_Requests', 'id_proof_number')) {
                    $table->string('id_proof_number', 100)->nullable()->after('id_proof_type')->comment('ID Proof number');
                }
                if (!Schema::hasColumn('Wo_Verification_Requests', 'id_proof_front_image')) {
                    $table->string('id_proof_front_image', 255)->nullable()->after('id_proof_number')->comment('Front image of ID proof');
                }
                if (!Schema::hasColumn('Wo_Verification_Requests', 'id_proof_back_image')) {
                    $table->string('id_proof_back_image', 255)->nullable()->after('id_proof_front_image')->comment('Back image of ID proof');
                }
                if (!Schema::hasColumn('Wo_Verification_Requests', 'badge_type')) {
                    $table->enum('badge_type', ['blue', 'golden'])->default('blue')->after('id_proof_back_image')->comment('Type of badge requested: blue (regular users) or golden (VIPs/celebrities)');
                }
                if (!Schema::hasColumn('Wo_Verification_Requests', 'status')) {
                    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('badge_type')->comment('Verification status');
                }
                if (!Schema::hasColumn('Wo_Verification_Requests', 'rejection_reason')) {
                    $table->string('rejection_reason', 255)->nullable()->after('status')->comment('Reason for rejection if status is rejected');
                }
                if (!Schema::hasColumn('Wo_Verification_Requests', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable()->after('rejection_reason')->comment('When the verification was submitted');
                }
                if (!Schema::hasColumn('Wo_Verification_Requests', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable()->after('submitted_at')->comment('When the verification was reviewed');
                }
                if (!Schema::hasColumn('Wo_Verification_Requests', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('reviewed_at')->comment('When the verification was approved');
                }
                if (!Schema::hasColumn('Wo_Verification_Requests', 'reviewed_by')) {
                    $table->unsignedBigInteger('reviewed_by')->nullable()->after('approved_at')->comment('Admin user who reviewed the verification');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Wo_Verification_Requests');
    }
};

