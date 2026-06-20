<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 5)->default('USD');
            $table->enum('payment_method', ['stripe', 'paypal', 'tap', 'moyasar', 'mada', 'apple_pay', 'google_pay', 'crypto', 'payerurl']);
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_gift')->default(false);
            $table->string('on_behalf_of')->nullable();
            $table->text('gift_message')->nullable();
            $table->string('receipt_url')->nullable();
            $table->timestamp('donated_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};