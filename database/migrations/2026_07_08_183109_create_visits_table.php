<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('social_worker_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->string('visit_type');
            $table->string('location');
            $table->text('address')->nullable();
            
            $table->date('visit_date');
            $table->time('visit_time');
            $table->enum('status', ['قيد الانتظار', 'مؤكدة', 'مكتملة', 'ملغية', 'معاد جدولتها'])
                ->default('قيد الانتظار');
            
            $table->text('notes')->nullable();
            $table->text('instructions')->nullable();
            
            $table->text('cancelled_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['beneficiary_id', 'status']);
            $table->index('visit_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};