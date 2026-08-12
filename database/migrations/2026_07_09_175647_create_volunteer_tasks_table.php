<?php
// database/migrations/2026_07_09_000001_create_volunteer_tasks_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('volunteer_id')->nullable()->constrained('volunter_profiles')->onDelete('cascade');
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->onDelete('set null');
            // ✅ تكامل مع المستفيد فقط (بدون حملات أو تبرعات)
            $table->foreignId('beneficiary_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('aid_application_id')->nullable()->constrained('aid_applications')->onDelete('set null');
            $table->foreignId('visit_id')->nullable()->constrained('visits')->onDelete('set null');


            $table->foreignId('type_id')->nullable()->constrained('types')->onDelete('set null');
            $table->enum('priority', ['منخفضة', 'متوسطة', 'عالية', 'عاجلة'])->default('متوسطة');
            $table->date('due_date')->nullable();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();

            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->timestamp('expected_end_time')->nullable();

            $table->enum('status', ['جديدة', 'قيد التنفيذ', 'مكتملة', 'ملغية', 'معلقة'])
                ->default('جديدة');

            $table->integer('progress_percentage')->default(0);
            $table->integer('points_earned')->default(0);

            $table->text('supervisor_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['volunteer_id', 'status']);
            $table->index('beneficiary_id');
            $table->index('aid_application_id');
            $table->index('visit_id');
        });

        Schema::create('volunteer_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('volunteer_tasks')->onDelete('cascade');
            $table->foreignId('volunteer_id')->constrained('volunter_profiles')->onDelete('cascade');

            $table->timestamp('check_in_time');
            $table->timestamp('check_out_time')->nullable();

            $table->boolean('location_verified')->default(false);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->enum('status', ['حاضر', 'متأخر', 'غائب', 'منصرف'])->default('حاضر');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['task_id', 'volunteer_id']);
        });

        Schema::create('volunteer_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('volunteer_id')->constrained('volunter_profiles')->onDelete('cascade');
            $table->foreignId('task_id')->constrained('volunteer_tasks')->onDelete('cascade');
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->onDelete('set null');

            $table->decimal('rating', 3, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->text('supervisor_response')->nullable();

            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->index(['volunteer_id', 'task_id']);
        });

        Schema::create('volunteer_certificates', function (Blueprint $table) {
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

    public function down(): void
    {
        Schema::dropIfExists('volunteer_certificates');
        Schema::dropIfExists('volunteer_evaluations');
        Schema::dropIfExists('volunteer_check_ins');
        Schema::dropIfExists('volunteer_tasks');
    }
};
