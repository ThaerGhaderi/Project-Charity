<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dorations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donor_profile_id')->constrained('donor_profiles')->onDelete('cascade');
            $table->string('name');
            $table->integer('amount');
            $table->enum('payment_method', ['نقد', 'بطاقة مصرفية', 'محفظة موبايل', 'تحويل بنكي', 'حوالة']);
            $table->enum('cat', ['إطعام', 'مساجد', 'تعليم', 'صحة', 'مياه', 'أيتام']);
            $table->date('date');
            $table->enum('status', ['مكتمل', 'ملغي', 'قيد المراجعة'])->default('قيد المراجعة');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dorations');
    }
};
