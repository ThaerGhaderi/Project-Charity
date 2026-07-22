<?php
// database/migrations/2026_07_09_183811_create_volunteer_check_ins_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ التحقق من وجود الجدول قبل الإنشاء
        if (!Schema::hasTable('volunteer_check_ins')) {
            Schema::create('volunteer_check_ins', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('volunteer_tasks')->onDelete('cascade');
                $table->foreignId('volunteer_id')->constrained('volunter_profiles')->onDelete('cascade');
                
                $table->timestamp('check_in_time');
                $table->timestamp('check_out_time')->nullable();
                
                $table->boolean('location_verified')->default(false);
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                
                $table->enum('status', ['حاضر', 'متأخر', 'غائب', 'منصرف'])->default('حاضر');
                $table->text('notes')->nullable();
                
                $table->timestamps();
                
                $table->index(['task_id', 'volunteer_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_check_ins');
    }
};