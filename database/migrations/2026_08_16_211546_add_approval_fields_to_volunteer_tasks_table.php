<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('volunteer_tasks', function (Blueprint $table) {
             $table->enum('awaiting_approval', ['start', 'end'])->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->decimal('requested_latitude', 10, 8)->nullable();
            $table->decimal('requested_longitude', 11, 8)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('volunteer_tasks', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn([
                'awaiting_approval',
                'requested_at',
                'requested_latitude',
                'requested_longitude',
                'rejection_reason',
                'reviewed_by',
                'reviewed_at',
            ]);
        });
    }
};
