<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('volunteer_badges', function (Blueprint $table) {
       $table->id();
       $table->foreignId('volunteer_id')->constrained('volunter_profiles')->onDelete('cascade');
       $table->string('name');
       $table->string('icon')->nullable();
       $table->text('description')->nullable();
       $table->timestamp('earned_at')->nullable();
       $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volunteer_badges');
    }
};
