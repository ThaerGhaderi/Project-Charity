<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // التحقق من وجود الجدول قبل إنشائه
        if (!Schema::hasTable('campaigns')) {
            Schema::create('campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description');
                $table->decimal('goal_amount', 12, 2);
                $table->decimal('collected_amount', 12, 2)->default(0);
                $table->enum('category', ['إطعام', 'مساجد', 'تعليم', 'صحة', 'مياه', 'أيتام'])->nullable();
                $table->enum('status', ['draft', 'review', 'active', 'closed', 'completed', 'cancelled'])->default('draft');
                $table->boolean('is_emergency')->default(false);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('short_url')->nullable();
                $table->string('qr_code_url')->nullable();
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade')->nullable();;
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};