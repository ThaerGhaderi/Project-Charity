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
        Schema::dropIfExists('otps');
        
        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->string('email');  // بريد المستخدم الإلكتروني
            $table->string('otp');     // رمز التحقق (5 أرقام)
            $table->string('type')->default('verification'); // نوع الـ OTP: verification, reset_password
            $table->timestamp('expires_at'); // تاريخ انتهاء الصلاحية
            $table->boolean('is_used')->default(false); // هل تم استخدامه؟
            $table->timestamps();
            
            // فهارس لتحسين أداء البحث
            $table->index(['email', 'type', 'is_used']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};