<?php
// database/migrations/2026_07_07_000001_create_sponsorships_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('beneficiary_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->onDelete('set null');
            
            $table->enum('type', ['شهرية', 'اسبوعية', 'سنوية', 'مرة واحدة']);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('SYP');
            
            $table->date('start_date');
            $table->date('end_date')->nullable();
            
            $table->enum('status', ['قيد الانتظار', 'نشطة', 'مكتملة', 'ملغية', 'معلقة'])->default('قيد الانتظار');
            
            $table->string('payment_method')->nullable();
            $table->enum('payment_frequency', ['شهرية', 'اسبوعية', 'سنوية', 'مرة واحدة'])->nullable();
            
            $table->boolean('is_anonymous')->default(false);
            $table->text('message')->nullable();
            $table->text('beneficiary_message')->nullable();
            
            $table->date('next_payment_date')->nullable();
            $table->date('last_payment_date')->nullable();
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->integer('remaining_payments')->default(0);
            
            $table->boolean('auto_renew')->default(false);
            
            $table->text('cancelled_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->text('admin_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
            
            // الفهارس
            $table->index(['sponsor_id', 'status']);
            $table->index(['beneficiary_id', 'status']);
            $table->index('status');
            $table->index('type');
            $table->index('next_payment_date');
        });

        Schema::create('sponsorship_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsorship_id')->constrained('sponsorships')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method');
            $table->string('transaction_id')->nullable();
            $table->timestamp('paid_at');
            $table->enum('status', ['قيد الانتظار', 'مكتملة', 'ملغية'])->default('مكتملة');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('sponsorship_id');
            $table->index('paid_at');
        });

        Schema::create('sponsorship_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsorship_id')->constrained('sponsorships')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->enum('type', ['sponsor_to_beneficiary', 'beneficiary_to_sponsor', 'system'])->default('sponsor_to_beneficiary');
            $table->timestamps();
            
            $table->index(['sponsorship_id', 'is_read']);
            $table->index(['sender_id', 'receiver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_messages');
        Schema::dropIfExists('sponsorship_payments');
        Schema::dropIfExists('sponsorships');
    }
};