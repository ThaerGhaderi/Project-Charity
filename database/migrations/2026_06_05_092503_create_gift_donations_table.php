<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_id')->constrained()->onDelete('cascade');
            $table->string('recipient_name');
            $table->string('recipient_email');
            $table->text('message')->nullable();
            $table->string('certificate_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_donations');
    }
};