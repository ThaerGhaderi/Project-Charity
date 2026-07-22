<?php
// app/Http/Controllers/VolunteerTaskController.php

namespace App\Http\Controllers;

use App\Models\VolunteerTask;
use App\Models\VolunteerCheckIn;
use App\Models\VolunteerEvaluation;
use App\Models\VolunteerCertificate;
use App\Models\VolunterProfile;
use App\Models\Notification;
use App\Models\User;
use App\Models\VolunteerBadge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VolunteerTaskController extends Controller
{
    /**
     * عرض جميع مهام المتطوع (الخاصة + المفتوحة للجميع)
     * 
     * @api {get} /api/volunteer/tasks Get All Tasks
     * @apiHeader Authorization Bearer {token}
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunteer;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }

        // ✅ عرض المهام الخاصة بالمستخدم
        $query = VolunteerTask::where('volunteer_id', $volunteer->id)
            ->with(['supervisor', 'beneficiary', 'aidApplication', 'visit', 'campaign']);

        // ✅ تصفية حسب الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // ✅ تصفية حسب النوع
        if ($request->filled('filter')) {
            switch ($request->filter) {
                case 'new':
                    $query->where('status', 'جديدة');
                    break;
                case 'in_progress':
                    $query->where('status', 'قيد التنفيذ');
                    break;
                case 'completed':
                    $query->where('status', 'مكتملة');
                    break;
            }
        }

        // ✅ تصفية حسب المصدر
        if ($request->filled('source')) {
            switch ($request->source) {
                case 'beneficiary':
                    $query->whereNotNull('beneficiary_id');
                    break;
                case 'aid':
                    $query->whereNotNull('aid_application_id');
                    break;
                case 'visit':
                    $query->whereNotNull('visit_id');
                    break;
                case 'campaign':
                    $query->whereNotNull('campaign_id');
                    break;
            }
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $tasks = $query->get();

        // ✅ إضافة الخصائص المحسوبة
        $tasks->transform(function ($task) {
            $task->status_text = $task->status_text;
            $task->elapsed_time = $task->formatted_elapsed_time;
            $task->is_in_progress = $task->is_in_progress;
            $task->is_completed = $task->is_completed;
            $task->is_new = $task->is_new;
            $task->source_type = $task->source_type;
            $task->source_name = $task->source_name;
            $task->beneficiary_name = $task->beneficiary_name;
            
            if ($task->campaign) {
                $task->campaign_title = $task->campaign->title;
                $task->campaign_progress = $task->campaign->progress_percentage;
            }
            
            return $task;
        });

        // ✅ إحصائيات
        $stats = [
            'total' => $tasks->count(),
            'new' => $tasks->where('status', 'جديدة')->count(),
            'in_progress' => $tasks->where('status', 'قيد التنفيذ')->count(),
            'completed' => $tasks->where('status', 'مكتملة')->count(),
            'cancelled' => $tasks->where('status', 'ملغية')->count(),
            'from_beneficiaries' => $tasks->whereNotNull('beneficiary_id')->count(),
            'from_aid_applications' => $tasks->whereNotNull('aid_application_id')->count(),
            'from_visits' => $tasks->whereNotNull('visit_id')->count(),
            'from_campaigns' => $tasks->whereNotNull('campaign_id')->count(),
        ];

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب المهام بنجاح',
            'data' => [
                'tasks' => $tasks,
                'stats' => $stats,
                'volunteer' => [
                    'id' => $volunteer->id,
                    'name' => $user->name,
                    'total_hours' => $volunteer->total_hours,
                ]
            ]
        ], 200);
    }

    /**
     * عرض تفاصيل مهمة معينة
     * 
     * @api {get} /api/volunteer/tasks/{id} Get Task Details
     * @apiHeader Authorization Bearer {token}
     */
    public function show($id, Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunteer;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }

        $task = VolunteerTask::where('volunteer_id', $volunteer->id)
            ->where('id', $id)
            ->with(['supervisor', 'checkIns', 'beneficiary', 'aidApplication', 'visit', 'campaign'])
            ->firstOrFail();

        $task->status_text = $task->status_text;
        $task->elapsed_time = $task->formatted_elapsed_time;
        $task->is_in_progress = $task->is_in_progress;
        $task->is_completed = $task->is_completed;
        $task->is_new = $task->is_new;
        $task->source_type = $task->source_type;
        $task->source_name = $task->source_name;
        $task->beneficiary_name = $task->beneficiary_name;

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب تفاصيل المهمة بنجاح',
            'data' => $task
        ], 200);
    }

    /**
     * بدء المهمة (تسجيل الحضور)
     * 
     * @api {post} /api/volunteer/tasks/{id}/start Start Task
     * @apiHeader Authorization Bearer {token}
     */
    public function startTask($id, Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunteer;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }

        $task = VolunteerTask::where('volunteer_id', $volunteer->id)
            ->where('id', $id)
            ->firstOrFail();

        // ✅ التحقق من أن المهمة جديدة
        if ($task->status !== 'جديدة') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لا يمكن بدء مهمة غير جديدة',
            ], 400);
        }

        // ✅ التحقق من وجود تسجيل حضور سابق
        $existingCheckIn = VolunteerCheckIn::where('task_id', $task->id)
            ->where('volunteer_id', $volunteer->id)
            ->whereNull('check_out_time')
            ->first();

        if ($existingCheckIn) {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لديك تسجيل حضور نشط بالفعل لهذه المهمة',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $checkIn = VolunteerCheckIn::create([
                'task_id' => $task->id,
                'volunteer_id' => $volunteer->id,
                'check_in_time' => now(),
                'location_verified' => true,
                'latitude' => $request->latitude ?? null,
                'longitude' => $request->longitude ?? null,
                'status' => 'حاضر',
            ]);

            $task->update([
                'status' => 'قيد التنفيذ',
                'start_time' => now(),
            ]);

            DB::commit();

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم تسجيل الحضور بنجاح',
                'data' => [
                    'task' => $task,
                    'check_in' => $checkIn,
                    'elapsed_time' => $task->formatted_elapsed_time,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء بدء المهمة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * إنهاء المهمة (تسجيل الانصراف)
     * 
     * @api {post} /api/volunteer/tasks/{id}/end End Task
     * @apiHeader Authorization Bearer {token}
     */
    public function endTask($id, Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunteer;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }

        $task = VolunteerTask::where('volunteer_id', $volunteer->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($task->status !== 'قيد التنفيذ') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'المهمة ليست قيد التنفيذ',
            ], 400);
        }

        $checkIn = VolunteerCheckIn::where('task_id', $task->id)
            ->where('volunteer_id', $volunteer->id)
            ->whereNull('check_out_time')
            ->first();

        if (!$checkIn) {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لا يوجد تسجيل حضور نشط لهذه المهمة',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $checkIn->update([
                'check_out_time' => now(),
                'status' => 'منصرف',
            ]);

            $duration = $checkIn->check_in_time->diffInHours($checkIn->check_out_time);

            $task->update([
                'status' => 'مكتملة',
                'end_time' => now(),
                'completed_at' => now(),
                'progress_percentage' => 100,
            ]);

            $volunteer->update([
                'total_hours' => $volunteer->total_hours + $duration,
            ]);

            VolunteerEvaluation::create([
                'volunteer_id' => $volunteer->id,
                'task_id' => $task->id,
                'supervisor_id' => $task->supervisor_id,
                'rating' => null,
                'feedback' => null,
                'evaluated_at' => null,
            ]);

            // ✅ تحديث حالة طلب المساعدة إذا كانت المهمة مرتبطة به
            if ($task->aidApplication) {
                $task->aidApplication->update(['status' => 'completed']);
            }

            // ✅ تحديث حالة الزيارة إذا كانت المهمة مرتبطة بها
            if ($task->visit) {
                $task->visit->update(['status' => 'مكتملة']);
            }

            // ✅ تحديث الشهادات
            $this->updateCertificates($volunteer);
            $this->updatePoints($volunteer, $duration);

            // ✅ إشعار للمستفيد
            if ($task->beneficiary_id) {
                Notification::sendPushOnly(
                    $task->beneficiary_id,
                    '✅ تم إكمال المهمة',
                    "تم إكمال المهمة '{$task->title}' بواسطة المتطوع {$user->name}",
                    'task_completed',
                    ['task_id' => $task->id]
                );
            }

            DB::commit();

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم تسجيل الانصراف بنجاح',
                'data' => [
                    'task' => $task,
                    'check_in' => $checkIn,
                    'duration_hours' => round($duration, 2),
                    'total_hours' => $volunteer->total_hours,
                    'certificates_updated' => true,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء إنهاء المهمة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * الحصول على المهمة الحالية للمتطوع
     * 
     * @api {get} /api/volunteer/tasks/current Get Current Task
     * @apiHeader Authorization Bearer {token}
     */
    public function currentTask(Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunteer;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }

        $task = VolunteerTask::where('volunteer_id', $volunteer->id)
            ->where('status', 'قيد التنفيذ')
            ->with(['supervisor', 'checkIns', 'beneficiary', 'aidApplication', 'visit', 'campaign'])
            ->first();

        if (!$task) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لا توجد مهمة حالية',
                'data' => null
            ], 404);
        }

        $task->status_text = $task->status_text;
        $task->elapsed_time = $task->formatted_elapsed_time;
        $task->is_in_progress = $task->is_in_progress;
        $task->source_type = $task->source_type;
        $task->source_name = $task->source_name;
        $task->beneficiary_name = $task->beneficiary_name;

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب المهمة الحالية بنجاح',
            'data' => $task
        ], 200);
    }

    /**
     * الحصول على تقييمات المتطوع
     * 
     * @api {get} /api/volunteer/evaluations Get Evaluations
     * @apiHeader Authorization Bearer {token}
     */
    public function evaluations(Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunteer;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }

        $evaluations = VolunteerEvaluation::where('volunteer_id', $volunteer->id)
            ->with(['task', 'supervisor'])
            ->whereNotNull('rating')
            ->orderBy('evaluated_at', 'desc')
            ->get();

        $averageRating = $evaluations->avg('rating') ?? 0;
        $latestEvaluation = $evaluations->first();

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب التقييمات بنجاح',
            'data' => [
                'evaluations' => $evaluations,
                'average_rating' => round($averageRating, 1),
                'total_evaluations' => $evaluations->count(),
                'latest_feedback' => $latestEvaluation ? [
                    'rating' => $latestEvaluation->rating,
                    'feedback' => $latestEvaluation->feedback,
                    'supervisor_name' => $latestEvaluation->supervisor?->name,
                    'task_title' => $latestEvaluation->task?->title,
                    'date' => $latestEvaluation->evaluated_at?->format('d/m/Y'),
                ] : null,
            ]
        ], 200);
    }

    // ==================== PRIVATE METHODS ====================

    /**
     * تحديث الشهادات تلقائياً عند إكمال المهام
     */
    private function updateCertificates($volunteer)
    {
        $certificates = VolunteerCertificate::where('volunteer_id', $volunteer->id)->get();
        
        foreach ($certificates as $cert) {
            $cert->update([
                'hours_completed' => min($cert->hours_required, $volunteer->total_hours),
            ]);
            
            if ($cert->hours_completed >= $cert->hours_required && !$cert->issued_at) {
                $cert->update([
                    'issued_at' => now(),
                    'certificate_number' => 'CERT-' . date('Ymd') . '-' . str_pad($cert->id, 4, '0', STR_PAD_LEFT),
                    'is_active' => true,
                ]);
                
                $user = $volunteer->user;
                if ($user) {
                    Notification::sendPushOnly(
                        $user->id,
                        '🎉 تهانينا! حصلت على شهادة جديدة',
                        "حصلت على شهادة '{$cert->title}' بعد إكمال {$cert->hours_required} ساعة تطوع",
                        'certificate',
                        ['certificate_id' => $cert->id]
                    );
                }
            }
        }
    }
 
public function statistics(Request $request)
{
    $user = $request->user();
    $volunteer = $user->volunteer;

    if (!$volunteer) {
        return response()->json([
            'code' => '404',
            'success' => false,
            'message' => 'لم يتم العثور على ملف المتطوع',
        ], 404);
    }

   
    $totalHours = $volunteer->total_hours ?? 0;

    
    $completedTasks = VolunteerTask::where('volunteer_id', $volunteer->id)
        ->where('status', 'مكتملة')
        ->count();

    
    $averageRating = VolunteerEvaluation::where('volunteer_id', $volunteer->id)
        ->whereNotNull('rating')
        ->avg('rating') ?? 0;

    $stats = [
        'total_tasks' => VolunteerTask::where('volunteer_id', $volunteer->id)->count(),
        'in_progress' => VolunteerTask::where('volunteer_id', $volunteer->id)
            ->where('status', 'قيد التنفيذ')->count(),
        'new_tasks' => VolunteerTask::where('volunteer_id', $volunteer->id)
            ->where('status', 'جديدة')->count(),
        'certificates' => VolunteerCertificate::where('volunteer_id', $volunteer->id)->count(),
    ];

    return response()->json([
        'code' => '200',
        'success' => true,
        'message' => 'تم جلب إحصائيات المتطوع بنجاح',
        'data' => [
            'volunteer' => [
                'id' => $volunteer->id,
                'name' => $user->name,
            ],
            'statistics' => [
                'total_hours' => round($totalHours, 1),  // 120h
                'completed_tasks' => $completedTasks,     // 12
                'average_rating' => round($averageRating, 1), // 4.8
                'total_tasks' => $stats['total_tasks'],
                'in_progress' => $stats['in_progress'],
                'new_tasks' => $stats['new_tasks'],
                'certificates' => $stats['certificates'],
            ]
        ]
    ], 200);
}
// app/Http/Controllers/VolunteerTaskController.php

/**
 * الحصول على نقاط المتطوع والشارات
 * 
 * @api {get} /api/volunteer/points Get Volunteer Points
 * @apiHeader Authorization Bearer {token}
 */
public function points(Request $request)
{
    $user = $request->user();
    $volunteer = $user->volunteer;

    if (!$volunteer) {
        return response()->json([
            'code' => '404',
            'success' => false,
            'message' => 'لم يتم العثور على ملف المتطوع',
        ], 404);
    }

    // ✅ حساب النقاط
    $points = $volunteer->points ?? 0;
    
    // ✅ حساب الترتيب
    $rank = $this->calculateRank($volunteer->id);
    
    // ✅ جلب الشارات
    $badges = $volunteer->badges()->orderBy('earned_at', 'desc')->get();

    return response()->json([
        'code' => '200',
        'success' => true,
        'data' => [
            'total_points' => $points,
            'rank' => $rank,
            'badges' => $badges->map(function($badge) {
                return [
                    'name' => $badge->name,
                    'icon' => $badge->icon,
                    'description' => $badge->description,
                    'earned_at' => $badge->earned_at?->format('Y-m-d'),
                ];
            }),
        ]
    ], 200);
}

/**
 * لوحة المتصدرين
 * 
 * @api {get} /api/volunteer/leaderboard Get Leaderboard
 * @apiHeader Authorization Bearer {token}
 */
public function leaderboard(Request $request)
{
    $user = $request->user();
    $volunteer = $user->volunteer;

    if (!$volunteer) {
        return response()->json([
            'code' => '404',
            'success' => false,
            'message' => 'لم يتم العثور على ملف المتطوع',
        ], 404);
    }

    // ✅ جلب جميع المتطوعين مرتبين حسب النقاط
    $topVolunteers = VolunterProfile::with('user')
        ->orderBy('points', 'desc')
        ->limit(10)
        ->get();

    // ✅ تحويل البيانات
    $leaderboard = $topVolunteers->map(function($v, $index) use ($volunteer) {
        return [
            'rank' => $index + 1,
            'name' => $v->user?->name ?? 'متطوع',
            'points' => $v->points ?? 0,
            'badge' => $this->getRankBadge($index + 1),
            'is_current_user' => $v->id === $volunteer->id,
        ];
    });

    // ✅ ترتيب المستخدم الحالي
    $userRank = $this->calculateRank($volunteer->id);
    $totalVolunteers = VolunterProfile::count();

    return response()->json([
        'code' => '200',
        'success' => true,
        'data' => [
            'leaderboard' => $leaderboard,
            'user_rank' => $userRank,
            'total_volunteers' => $totalVolunteers,
        ]
    ], 200);
}

/**
 * حساب ترتيب المتطوع
 */
private function calculateRank($volunteerId)
{
    $points = VolunterProfile::where('id', $volunteerId)->value('points') ?? 0;
    
    return VolunterProfile::where('points', '>', $points)->count() + 1;
}

/**
 * الحصول على شارة الترتيب
 */
private function getRankBadge($rank)
{
    return match($rank) {
        1 => '🥇',
        2 => '🥈',
        3 => '🥉',
        default => '⭐',
    };
}

/**
 * تحديث النقاط تلقائياً عند إكمال المهمة
 */
private function updatePoints($volunteer, $duration)
{
    // ✅ نقطة لكل ساعة تطوع
    $pointsEarned = round($duration * 10); // 10 نقاط لكل ساعة
    
    $volunteer->increment('points', $pointsEarned);
    
    // ✅ تحديث الشارات بناءً على النقاط
    $this->updateBadges($volunteer);
}

/**
 * تحديث الشارات بناءً على النقاط
 */
private function updateBadges($volunteer)
{
    $points = $volunteer->points ?? 0;
    $badges = [];
    
    if ($points >= 500) {
        $badges[] = ['name' => 'نشط', 'icon' => '🔥', 'description' => '500+ نقطة'];
    }
    if ($points >= 1000) {
        $badges[] = ['name' => 'ممتاز', 'icon' => '⭐', 'description' => '1000+ نقطة'];
    }
    if ($points >= 2000) {
        $badges[] = ['name' => 'إنساني', 'icon' => '❤️', 'description' => '2000+ نقطة'];
    }
    if ($points >= 3000) {
        $badges[] = ['name' => 'أسطورة', 'icon' => '🏆', 'description' => '3000+ نقطة'];
    }
    if ($points >= 5000) {
        $badges[] = ['name' => 'نشط جدا', 'icon' => '🔥🔥', 'description' => '5000+ نقطة'];
    }
    
    foreach ($badges as $badgeData) {
        VolunteerBadge::firstOrCreate([
            'volunteer_id' => $volunteer->id,
            'name' => $badgeData['name'],
        ], [
            'icon' => $badgeData['icon'],
            'description' => $badgeData['description'],
            'earned_at' => now(),
        ]);
    }
}
}