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
        Schema::create('beneficiary_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('priority_score')->default(0);
            $table->unsignedTinyInteger('family_members_count')->default(1);
            $table->boolean('Breadwinner')->default(0);
            $table->boolean('has_income')->default(0);
            $table->enum('income_range', ['أقل من 100 الف','100-300 الف','300-500 الف','أكثر من 500 الف'])->nullable();
            $table->string('photo_Family_notebook')->nullable();
            $table->string('photo_Supporting')->nullable();
            $table->enum('marital_status',['أعزب','متزوج', 'مطلق', 'أرمل','يتيم']);
            $table->boolean('is_Anonymous')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beneficiary_profiles');
    }
};
