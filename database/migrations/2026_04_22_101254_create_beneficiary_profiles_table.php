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
            $table->string('address');
            $table->string('region');
            $table->enum('category', ['orphan', 'refugee', 'disabled', 'poor']);
            $table->integer('priority_score')->default(0);
            $table->date('birth_date');
            $table->enum('gender', ['male', 'female']);
           $table->enum('marital_status',['single', 'married', 'divorced', 'widowed']);
           $table->boolean('is_anonymized')->default(false);
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
