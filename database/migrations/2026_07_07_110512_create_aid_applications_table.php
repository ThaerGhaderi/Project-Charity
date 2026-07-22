<?php
// database/migrations/2026_07_07_000000_create_aid_applications_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aid_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_profile_id')->constrained('beneficiary_profiles')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type');
            $table->text('description');
            $table->boolean('is_urgent')->default(false);
            $table->enum('status', ['pending', 'reviewing', 'approved', 'rejected', 'completed', 'cancelled'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->decimal('amount_requested', 15, 2)->nullable();
            $table->decimal('amount_approved', 15, 2)->nullable();
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['user_id', 'status']);
            $table->index(['type', 'is_urgent']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aid_applications');
    }
};