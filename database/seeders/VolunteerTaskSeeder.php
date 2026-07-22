<?php
// database/seeders/VolunteerTaskSeeder.php

namespace Database\Seeders;

use App\Models\VolunteerTask;
use App\Models\VolunterProfile;
use App\Models\User;
use App\Models\Campaign;
use App\Models\AidApplication;
use App\Models\Visit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class VolunteerTaskSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        // ✅ جلب البيانات الموجودة
        $volunteers = VolunterProfile::with('user')->get();
        $campaigns = Campaign::where('status', 'active')->get();
        $beneficiaries = User::where('role', 'Beneficiary')->where('profile_completed', true)->get();
        $aidApplications = AidApplication::where('status', 'approved')->get();
        $visits = Visit::where('status', 'confirmed')->get();

        // ✅ التحقق من وجود بيانات
        if ($volunteers->isEmpty()) {
            $this->command->warn('⚠️ لا يوجد متطوعين. يرجى إنشاء متطوعين أولاً.');
            return;
        }

        if ($campaigns->isEmpty() && $aidApplications->isEmpty() && $visits->isEmpty()) {
            $this->command->warn('⚠️ لا يوجد حملات أو طلبات مساعدة أو زيارات لإنشاء مهام منها.');
            return;
        }

        $this->command->info('📋 جاري إنشاء المهام التطوعية...');

        $tasksCreated = 0;

        // ==================== 1. مهام من الحملات ====================
        if ($campaigns->isNotEmpty()) {
            $this->command->info('📢 إنشاء مهام من الحملات...');
            
            foreach ($campaigns as $campaign) {
                // اختيار متطوع عشوائي
                $volunteer = $volunteers->random();
                
                $taskTitles = [
                    "توزيع المساعدات - {$campaign->title}",
                    "تنظيم الفعاليات - {$campaign->title}",
                    "تسجيل المستفيدين - {$campaign->title}",
                    "التوعية والتثقيف - {$campaign->title}",
                    "تقييم الحملة - {$campaign->title}",
                ];

                // إنشاء 2-4 مهام لكل حملة
                $numTasks = rand(2, 4);
                $selectedTitles = $faker->randomElements($taskTitles, $numTasks);

                foreach ($selectedTitles as $title) {
                    $status = $faker->randomElement(['جديدة', 'قيد التنفيذ', 'مكتملة']);
                    $progress = $status === 'مكتملة' ? 100 : ($status === 'قيد التنفيذ' ? rand(30, 80) : 0);
                    
                    // اختيار متطوع عشوائي لكل مهمة
                    $taskVolunteer = $volunteers->random();
                    
                    VolunteerTask::create([
                        'volunteer_id' => $taskVolunteer->id,
                        'supervisor_id' => rand(1, 5),
                        'campaign_id' => $campaign->id,
                        'beneficiary_id' => null,
                        'aid_application_id' => null,
                        'visit_id' => null,
                        'title' => $title,
                        'description' => $faker->paragraph(2),
                        'location' => $campaign->location ?? $faker->city(),
                        'start_time' => $status === 'جديدة' ? null : $faker->dateTimeBetween('-7 days', 'now'),
                        'end_time' => $status === 'مكتملة' ? $faker->dateTimeBetween('-3 days', 'now') : null,
                        'expected_end_time' => $faker->dateTimeBetween('now', '+7 days'),
                        'status' => $status,
                        'progress_percentage' => $progress,
                        'points_earned' => $status === 'مكتملة' ? rand(10, 50) : 0,
                        'supervisor_notes' => $status === 'مكتملة' ? $faker->sentence() : null,
                        'completed_at' => $status === 'مكتملة' ? $faker->dateTimeBetween('-3 days', 'now') : null,
                        'created_at' => $faker->dateTimeBetween('-30 days', 'now'),
                        'updated_at' => now(),
                    ]);
                    
                    $tasksCreated++;
                }
            }
            $this->command->info(" تم إنشاء مهام من الحملات");
        }

        // ==================== 2. مهام من طلبات المساعدة ====================
        if ($aidApplications->isNotEmpty()) {
            $this->command->info('📢 إنشاء مهام من طلبات المساعدة...');
            
            foreach ($aidApplications as $application) {
                $volunteer = $volunteers->random();
                $beneficiary = $application->user;
                
                $typeMap = [
                    'مالية' => 'مساعدة مالية',
                    'تعليمية' => 'مساعدة تعليمية',
                    'صحية' => 'مساعدة صحية',
                    'نفسية' => 'دعم نفسي',
                    'إغاثية' => 'مساعدة إغاثية',
                    'إيواء' => 'مساعدة إيواء',
                    'غذاء' => 'مساعدة غذاء',
                    'مياه' => 'مساعدة مياه',
                    'كسوة' => 'مساعدة كسوة',
                ];
                
                $taskTitle = $typeMap[$application->type] ?? 'مساعدة';
                $userName = $beneficiary?->name ?? 'مستفيد';
                
                $status = $faker->randomElement(['جديدة', 'قيد التنفيذ', 'مكتملة']);
                $progress = $status === 'مكتملة' ? 100 : ($status === 'قيد التنفيذ' ? rand(30, 80) : 0);
                
                VolunteerTask::create([
                    'volunteer_id' => $volunteer->id,
                    'supervisor_id' => rand(1, 5),
                    'campaign_id' => null,
                    'beneficiary_id' => $beneficiary?->id,
                    'aid_application_id' => $application->id,
                    'visit_id' => null,
                    'title' => "{$taskTitle} - {$userName}",
                    'description' => $application->description ?? $faker->paragraph(2),
                    'location' => $beneficiary?->profile?->city?->name ?? $faker->city(),
                    'start_time' => $status === 'جديدة' ? null : $faker->dateTimeBetween('-7 days', 'now'),
                    'end_time' => $status === 'مكتملة' ? $faker->dateTimeBetween('-3 days', 'now') : null,
                    'expected_end_time' => $faker->dateTimeBetween('now', '+5 days'),
                    'status' => $status,
                    'progress_percentage' => $progress,
                    'points_earned' => $status === 'مكتملة' ? rand(10, 30) : 0,
                    'supervisor_notes' => $status === 'مكتملة' ? $faker->sentence() : null,
                    'completed_at' => $status === 'مكتملة' ? $faker->dateTimeBetween('-3 days', 'now') : null,
                    'created_at' => $faker->dateTimeBetween('-20 days', 'now'),
                    'updated_at' => now(),
                ]);
                
                $tasksCreated++;
            }
            $this->command->info(" تم إنشاء مهام من طلبات المساعدة");
        }

        // ==================== 3. مهام من الزيارات ====================
        if ($visits->isNotEmpty()) {
            $this->command->info(' إنشاء مهام من الزيارات...');
            
            foreach ($visits as $visit) {
                $volunteer = $volunteers->random();
                $beneficiary = $visit->beneficiary;
                
                $status = $faker->randomElement(['جديدة', 'قيد التنفيذ', 'مكتملة']);
                $progress = $status === 'مكتملة' ? 100 : ($status === 'قيد التنفيذ' ? rand(30, 80) : 0);
                
                VolunteerTask::create([
                    'volunteer_id' => $volunteer->id,
                    'supervisor_id' => rand(1, 5),
                    'campaign_id' => null,
                    'beneficiary_id' => $beneficiary?->id,
                    'aid_application_id' => null,
                    'visit_id' => $visit->id,
                    'title' => "زيارة ميدانية - {$visit->location}",
                    'description' => "زيارة المستفيد {$beneficiary?->name} في {$visit->location}",
                    'location' => $visit->location,
                    'start_time' => $status === 'جديدة' ? null : $faker->dateTimeBetween('-5 days', 'now'),
                    'end_time' => $status === 'مكتملة' ? $faker->dateTimeBetween('-2 days', 'now') : null,
                    'expected_end_time' => $visit->visit_date ?? $faker->dateTimeBetween('now', '+3 days'),
                    'status' => $status,
                    'progress_percentage' => $progress,
                    'points_earned' => $status === 'مكتملة' ? rand(10, 25) : 0,
                    'supervisor_notes' => $status === 'مكتملة' ? $faker->sentence() : null,
                    'completed_at' => $status === 'مكتملة' ? $faker->dateTimeBetween('-2 days', 'now') : null,
                    'created_at' => $faker->dateTimeBetween('-15 days', 'now'),
                    'updated_at' => now(),
                ]);
                
                $tasksCreated++;
            }
            $this->command->info(" تم إنشاء مهام من الزيارات");
        }

        // ==================== 4. مهام عامة (بدون مصدر) ====================
        $this->command->info(' إنشاء مهام عامة...');
        
        $generalTasks = [
            ['title' => 'تنظيم حملة تبرعات', 'description' => 'تنظيم حملة تبرعات في المركز الرئيسي'],
            ['title' => 'توزيع وجبات الإفطار', 'description' => 'توزيع وجبات الإفطار على المحتاجين'],
            ['title' => 'مساعدة في الدروس الخصوصية', 'description' => 'مساعدة الأيتام في دروسهم'],
            ['title' => 'تسجيل المستفيدين الجدد', 'description' => 'تسجيل بيانات المستفيدين الجدد'],
            ['title' => 'تنظيم فعالية ترفيهية', 'description' => 'تنظيم فعالية ترفيهية للأطفال'],
        ];

        foreach ($generalTasks as $taskData) {
            // 50% chance to create each general task
            if (rand(0, 1)) {
                $volunteer = $volunteers->random();
                $status = $faker->randomElement(['جديدة', 'قيد التنفيذ', 'مكتملة']);
                $progress = $status === 'مكتملة' ? 100 : ($status === 'قيد التنفيذ' ? rand(30, 80) : 0);
                
                VolunteerTask::create([
                    'volunteer_id' => $volunteer->id,
                    'supervisor_id' => rand(1, 5),
                    'campaign_id' => null,
                    'beneficiary_id' => null,
                    'aid_application_id' => null,
                    'visit_id' => null,
                    'title' => $taskData['title'],
                    'description' => $taskData['description'],
                    'location' => $faker->city(),
                    'start_time' => $status === 'جديدة' ? null : $faker->dateTimeBetween('-7 days', 'now'),
                    'end_time' => $status === 'مكتملة' ? $faker->dateTimeBetween('-3 days', 'now') : null,
                    'expected_end_time' => $faker->dateTimeBetween('now', '+7 days'),
                    'status' => $status,
                    'progress_percentage' => $progress,
                    'points_earned' => $status === 'مكتملة' ? rand(5, 20) : 0,
                    'supervisor_notes' => $status === 'مكتملة' ? $faker->sentence() : null,
                    'completed_at' => $status === 'مكتملة' ? $faker->dateTimeBetween('-3 days', 'now') : null,
                    'created_at' => $faker->dateTimeBetween('-30 days', 'now'),
                    'updated_at' => now(),
                ]);
                
                $tasksCreated++;
            }
        }
        $this->command->info(" تم إنشاء مهام عامة");

        // ==================== 5. مهام مع تسجيل حضور ====================
        $this->command->info(' إنشاء تسجيلات حضور للمهام...');
        
        $inProgressTasks = VolunteerTask::where('status', 'قيد التنفيذ')->get();
        $completedTasks = VolunteerTask::where('status', 'مكتملة')->get();
        
        foreach ($inProgressTasks as $task) {
            // إنشاء تسجيل حضور للمهام قيد التنفيذ
            DB::table('volunteer_check_ins')->insert([
                'task_id' => $task->id,
                'volunteer_id' => $task->volunteer_id,
                'check_in_time' => $task->start_time ?? $faker->dateTimeBetween('-2 hours', 'now'),
                'check_out_time' => null,
                'location_verified' => true,
                'latitude' => $faker->latitude(),
                'longitude' => $faker->longitude(),
                'status' => 'حاضر',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        foreach ($completedTasks as $task) {
            // إنشاء تسجيل حضور للمهام المكتملة
            $checkInTime = $task->start_time ?? $faker->dateTimeBetween('-5 hours', '-2 hours');
            $checkOutTime = $task->end_time ?? $faker->dateTimeBetween('-1 hour', 'now');
            
            DB::table('volunteer_check_ins')->insert([
                'task_id' => $task->id,
                'volunteer_id' => $task->volunteer_id,
                'check_in_time' => $checkInTime,
                'check_out_time' => $checkOutTime,
                'location_verified' => true,
                'latitude' => $faker->latitude(),
                'longitude' => $faker->longitude(),
                'status' => 'منصرف',
                'notes' => $faker->sentence(),
                'created_at' => $checkInTime,
                'updated_at' => $checkOutTime,
            ]);
        }
        $this->command->info(" تم إنشاء تسجيلات الحضور");

        // ==================== 6. إحصائيات ====================
        $this->command->newLine();
        $this->command->info(" الإحصائيات:");
        $this->command->line("    إجمالي المهام المنشأة: {$tasksCreated}");
        $this->command->line("    مهام جديدة: " . VolunteerTask::where('status', 'جديدة')->count());
        $this->command->line("    مهام قيد التنفيذ: " . VolunteerTask::where('status', 'قيد التنفيذ')->count());
        $this->command->line("    مهام مكتملة: " . VolunteerTask::where('status', 'مكتملة')->count());
        $this->command->line("    تسجيلات حضور: " . DB::table('volunteer_check_ins')->count());
        $this->command->newLine();
        $this->command->info(" تم إنشاء المهام التطوعية بنجاح!");
    }
}