<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunter_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('Favorite_period',['صباحاً','ظهراً','مساءً']);
            $table->integer('total_hours')->default(0)->nullable();
            $table->boolean('previous_voluntering')->default(0);
            $table->string('previous_work_place')->nullable();
            $table->integer('experience_years')->default(0)->nullable();
            $table->boolean('car')->default(0);
            $table->enum('status',['مشغول','متاح','غير متاح']);
            $table->text('bio')->nullable();
            $table->enum('Commitment_type',['منتظم','مرة بمرة']);
            $table->enum('Educational_level',['ثانوية عامة','بكالوريوس','ماستر','دكتوراة','معهد']);
            $table->string('facebook')->nullable();
            $table->string('linkedin')->nullable();
            $table->integer('points')->default(0);  
            $table->integer('rank')->nullable();  
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('volunter_profiles');
    }
};
