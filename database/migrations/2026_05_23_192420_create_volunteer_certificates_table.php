<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
          $table->id();
    $table->foreignId('volunteer_id')->constrained('volunter_profiles')->onDelete('cascade');
    
    $table->string('title');
    $table->text('description')->nullable();
    $table->timestamp('issued_at')->nullable();
    $table->string('certificate_number')->unique()->nullable();
    $table->string('file_path')->nullable();
    
    $table->enum('level', ['برونزية', 'فضية', 'ذهبية', 'ماسية'])->nullable();
    $table->integer('hours_required')->default(0);
    $table->integer('hours_completed')->default(0);
    $table->boolean('is_active')->default(true);
    
    $table->timestamps();
    
    $table->index(['volunteer_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
