<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->enum('donor_type',['فردي','منظمة'])->nullable();
            $table->boolean('is_anonymous')->default(0);
            $table->bigInteger('total_donated')->default(0);
            $table->integer('loyalty_points')->default(0);
            $table->enum('loyalty_tier',['برونزية','فضية','ذهبية'])->nullable();
            $table->string('bio',255)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('donor_profiles');
    }
};
