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

    public function index(Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunterProfile;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }

        $query = VolunteerTask::where('volunteer_id', $volunteer->id)
            ->with(['supervisor', 'beneficiary', 'aidApplication', 'visit', 'campaign', 'checkIns']);

        // تصفية حسب الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // تصفية حسب نوع المهمة
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
                case 'pending':
                    $query->where('status', 'معلقة');
                    break;
            }
        }

        // تصفية حسب المصدر
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

        // إضافة الخصائص المحسوبة
        $tasks->transform(function ($task) {
            $task->status_text = $task->status_text;
            $task->elapsed_time = $task->formatted_elapsed_time;
            $task->is_in_progress = $task->is_in_progress;
            $task->is_completed = $task->is_completed;
            $task->is_new = $task->is_new;
            $task->is_pending = $task->status === 'معلقة';
            $task->source_type = $task->source_type;
            $task->source_name = $task->source_name;
            $task->beneficiary_name = $task->beneficiary_name;

            // ✅ معلومات طلب الحضور والانصراف من check-ins
            $latestCheckIn = $task->checkIns->last();
            if ($latestCheckIn) {
                $task->check_in_status = $latestCheckIn->status;
                $task->check_in_time = $latestCheckIn->check_in_time?->format('Y-m-d H:i:s');
                $task->check_out_time = $latestCheckIn->check_out_time?->format('Y-m-d H:i:s');
                $task->is_check_in_pending = $latestCheckIn->status === 'حاضر' && !$latestCheckIn->check_out_time;
                $task->is_check_out_pending = $latestCheckIn->status === 'منصرف' && $latestCheckIn->check_out_time;
                $task->is_checked_in = $latestCheckIn->status === 'حاضر' && !$latestCheckIn->check_out_time;
                $task->is_checked_out = $latestCheckIn->status === 'منصرف';
            }

            if ($task->campaign) {
                $task->campaign_title = $task->campaign->title;
                $task->campaign_progress = $task->campaign->progress_percentage;
            }

            if ($task->aidApplication) {
                $task->aid_type = $task->aidApplication->type;
                $task->aid_status = $task->aidApplication->status;
                $task->aid_is_urgent = $task->aidApplication->is_urgent;
            }

            if ($task->visit) {
                $task->visit_date = $task->visit->formatted_date;
                $task->visit_time = $task->visit->formatted_time;
            }

            return $task;
        });

        // إحصائيات
        $stats = [
            'total' => $tasks->count(),
            'new' => $tasks->where('status', 'جديدة')->count(),
            'in_progress' => $tasks->where('status', 'قيد التنفيذ')->count(),
            'completed' => $tasks->where('status', 'مكتملة')->count(),
            'pending' => $tasks->where('status', 'معلقة')->count(),
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
                    'is_available' => $this->isVolunteerAvailable($volunteer->id),
                ]
            ]
        ], 200);
    }


    public function availableTasks(Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunterProfile;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }


        if (!$this->isVolunteerAvailable($volunteer->id)) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'غير متاح حالياً، لديك مهام معلقة أو قيد التنفيذ',
                'data' => [
                    'has_pending_tasks' => $this->hasPendingTasks($volunteer->id),
                    'has_in_progress_tasks' => $this->hasInProgressTasks($volunteer->id),
                ]
            ], 403);
        }


        $query = VolunteerTask::whereNull('volunteer_id')
            ->where('status', 'جديدة')
            ->with(['supervisor', 'beneficiary', 'visit', 'campaign', 'aidApplication']);


        if ($request->filled('source')) {
            switch ($request->source) {
                case 'visit':
                    $query->whereNotNull('visit_id');
                    break;
                case 'campaign':
                    $query->whereNotNull('campaign_id');
                    break;
                case 'beneficiary':
                    $query->whereNotNull('beneficiary_id');
                    break;
                case 'aid':
                    $query->whereNotNull('aid_application_id');
                    break;
            }
        }


        if ($request->filled('type')) {
            switch ($request->type) {
                case 'visit':
                    $query->whereNotNull('visit_id');
                    break;
                case 'aid':
                    $query->whereNotNull('aid_application_id');
                    break;
                case 'campaign':
                    $query->whereNotNull('campaign_id');
                    break;
            }
        }


        if ($request->filled('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }


        if ($request->boolean('urgent_only')) {
            $query->whereHas('aidApplication', function($q) {
                $q->where('is_urgent', true);
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $tasks = $query->get();

        $tasks->transform(function ($task) {
            $task->status_text = $task->status_text;
            $task->source_type = $task->source_type;
            $task->source_name = $task->source_name;
            $task->beneficiary_name = $task->beneficiary_name;
            $task->is_available = true;

            if ($task->visit) {
                $task->visit_date = $task->visit->formatted_date;
                $task->visit_time = $task->visit->formatted_time;
            }

            if ($task->aidApplication) {
                $task->aid_type = $task->aidApplication->type;
                $task->aid_status = $task->aidApplication->status;
                $task->aid_is_urgent = $task->aidApplication->is_urgent;
                $task->aid_amount = $task->aidApplication->amount_requested;
            }

            return $task;
        });


        $stats = [
            'total' => $tasks->count(),
            'from_visits' => $tasks->whereNotNull('visit_id')->count(),
            'from_aid_applications' => $tasks->whereNotNull('aid_application_id')->count(),
            'from_campaigns' => $tasks->whereNotNull('campaign_id')->count(),
            'urgent' => $tasks->filter(function($task) {
                return $task->aidApplication && $task->aidApplication->is_urgent;
            })->count(),
        ];

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب المهام المتاحة بنجاح',
            'data' => [
                'tasks' => $tasks,
                'stats' => $stats,
                'volunteer' => [
                    'id' => $volunteer->id,
                    'name' => $user->name,
                    'is_available' => true,
                ]
            ]
        ], 200);
    }


    public function show($id, Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunterProfile;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }

        $task = VolunteerTask::where('id', $id)
            ->with(['supervisor', 'checkIns', 'beneficiary', 'aidApplication', 'visit', 'campaign'])
            ->first();

        if (!$task) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'المهمة غير موجودة',
            ], 404);
        }

        // التحقق من أن المتطوع لديه صلاحية
        if ($task->volunteer_id && $task->volunteer_id != $volunteer->id) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'ليس لديك صلاحية لعرض هذه المهمة',
            ], 403);
        }

        $task->status_text = $task->status_text;
        $task->elapsed_time = $task->formatted_elapsed_time;
        $task->is_in_progress = $task->is_in_progress;
        $task->is_completed = $task->is_completed;
        $task->is_new = $task->is_new;
        $task->is_pending = $task->status === 'معلقة';
        $task->source_type = $task->source_type;
        $task->source_name = $task->source_name;
        $task->beneficiary_name = $task->beneficiary_name;


        $latestCheckIn = $task->checkIns->last();
        if ($latestCheckIn) {
            $task->check_in_status = $latestCheckIn->status;
            $task->check_in_time = $latestCheckIn->check_in_time?->format('Y-m-d H:i:s');
            $task->check_out_time = $latestCheckIn->check_out_time?->format('Y-m-d H:i:s');
            $task->is_checked_in = $latestCheckIn->status === 'حاضر' && !$latestCheckIn->check_out_time;
            $task->is_checked_out = $latestCheckIn->status === 'منصرف';
            $task->check_in_notes = $latestCheckIn->notes;
            $task->location_verified = $latestCheckIn->location_verified;
            $task->latitude = $latestCheckIn->latitude;
            $task->longitude = $latestCheckIn->longitude;
        }

        // معلومات إضافية حسب المصدر
        if ($task->aidApplication) {
            $task->aid_details = [
                'type' => $task->aidApplication->type,
                'status' => $task->aidApplication->status,
                'is_urgent' => $task->aidApplication->is_urgent,
                'amount_requested' => $task->aidApplication->amount_requested,
                'amount_approved' => $task->aidApplication->amount_approved,
                'admin_notes' => $task->aidApplication->admin_notes,
                'created_at' => $task->aidApplication->created_at,
            ];
        }

        if ($task->visit) {
            $task->visit_details = [
                'id' => $task->visit->id,
                'date' => $task->visit->formatted_date,
                'time' => $task->visit->formatted_time,
                'type' => $task->visit->visit_type,
                'status' => $task->visit->status,
                'location' => $task->visit->location,
                'notes' => $task->visit->notes,
            ];
        }

        if ($task->campaign) {
            $task->campaign_details = [
                'id' => $task->campaign->id,
                'title' => $task->campaign->title,
                'progress' => $task->campaign->progress_percentage,
                'status' => $task->campaign->status,
            ];
        }

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب تفاصيل المهمة بنجاح',
            'data' => $task
        ], 200);
    }


    public function requestStartTask($id, Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunterProfile;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }


        $task = VolunteerTask::find($id);

        if (!$task) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'المهمة غير موجودة',
            ], 404);
        }


        if ($task->status !== 'جديدة') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لا يمكن طلب بدء مهمة غير جديدة',
                'current_status' => $task->status,
            ], 400);
        }


        if (!$task->volunteer_id) {
            $task->update(['volunteer_id' => $volunteer->id]);
            $task->refresh();
        }


        if ($task->volunteer_id != $volunteer->id) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'هذه المهمة مأخوذة من قبل متطوع آخر',
                'task_volunteer_id' => $task->volunteer_id,
            ], 403);
        }


        $existingActiveCheckIn = VolunteerCheckIn::where('task_id', $task->id)
            ->where('volunteer_id', $volunteer->id)
            ->whereNull('check_out_time')
            ->first();

        if ($existingActiveCheckIn) {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لديك تسجيل حضور نشط لهذه المهمة بالفعل',
                'check_in_time' => $existingActiveCheckIn->check_in_time->format('Y-m-d H:i:s'),
            ], 400);
        }

        try {
            DB::beginTransaction();


            $checkIn = VolunteerCheckIn::create([
                'task_id' => $task->id,
                'volunteer_id' => $volunteer->id,
                'check_in_time' => now(),
                'check_out_time' => null,
                'status' => 'حاضر',
                'location_verified' => true,
                'latitude' => $request->latitude ?? null,
                'longitude' => $request->longitude ?? null,
                'notes' => $request->notes ?? 'بانتظار موافقة الأدمن',
            ]);


            $task->update([
                'status' => 'معلقة',
                'start_time' => now(),
            ]);

            DB::commit();

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم تسجيل الحضور بنجاح، المهمة معلقة بانتظار موافقة الأدمن',
                'data' => [
                    'task' => $task,
                    'check_in' => $checkIn,
                    'status' => 'معلقة',
                    'message_ar' => 'تم تسجيل حضورك، ينتظر الأدمن لتأكيد بدء المهمة',
                    'assigned_to_you' => true,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل الحضور',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function requestEndTask($id, Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunterProfile;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }

        $task = VolunteerTask::find($id);

        if (!$task) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'المهمة غير موجودة',
            ], 404);
        }


        if (!in_array($task->status, ['قيد التنفيذ', 'معلقة'])) {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لا يمكن طلب انصراف لمهمة ليست قيد التنفيذ أو معلقة',
                'current_status' => $task->status,
            ], 400);
        }

        if ($task->volunteer_id != $volunteer->id) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'هذه المهمة غير مخصصة لك',
            ], 403);
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
                'notes' => $request->notes ?? 'بانتظار موافقة الأدمن',
            ]);

            // ✅ تحديث حالة المهمة إلى "معلقة" (بانتظار موافقة الأدمن)
            $task->update([
                'status' => 'معلقة',
                'end_time' => now(),
            ]);

            DB::commit();

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم تسجيل الانصراف بنجاح، المهمة معلقة بانتظار موافقة الأدمن',
                'data' => [
                    'task' => $task,
                    'check_in' => $checkIn,
                    'status' => 'معلقة',
                    'message_ar' => 'تم تسجيل انصرافك، ينتظر الأدمن لتأكيد إكمال المهمة',
                    'check_in_time' => $checkIn->check_in_time->format('Y-m-d H:i:s'),
                    'check_out_time' => $checkIn->check_out_time->format('Y-m-d H:i:s'),
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل الانصراف',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function currentTask(Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunterProfile;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }


        $task = VolunteerTask::where('volunteer_id', $volunteer->id)
            ->whereIn('status', ['قيد التنفيذ', 'معلقة'])
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
        $task->is_pending = $task->status === 'معلقة';
        $task->source_type = $task->source_type;
        $task->source_name = $task->source_name;
        $task->beneficiary_name = $task->beneficiary_name;

        $latestCheckIn = $task->checkIns->last();
        if ($latestCheckIn) {
            $task->check_in_time = $latestCheckIn->check_in_time?->format('Y-m-d H:i:s');
            $task->check_out_time = $latestCheckIn->check_out_time?->format('Y-m-d H:i:s');
            $task->is_checked_in = $latestCheckIn->status === 'حاضر' && !$latestCheckIn->check_out_time;
            $task->is_checked_out = $latestCheckIn->status === 'منصرف';
        }

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب المهمة الحالية بنجاح',
            'data' => $task
        ], 200);
    }


    public function pendingRequests(Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunterProfile;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }


        $pendingTasks = VolunteerTask::where('volunteer_id', $volunteer->id)
            ->where('status', 'معلقة')
            ->with(['checkIns'])
            ->get();

        $result = $pendingTasks->map(function($task) {
            $checkIn = $task->checkIns->last();
            $isCheckInRequest = $checkIn && $checkIn->status === 'حاضر' && !$checkIn->check_out_time;
            $isCheckOutRequest = $checkIn && $checkIn->status === 'منصرف';

            return [
                'id' => $task->id,
                'title' => $task->title,
                'status' => 'معلقة',
                'request_type' => $isCheckOutRequest ? 'انصراف' : ($isCheckInRequest ? 'حضور' : 'غير معروف'),
                'requested_at' => $checkIn?->check_in_time?->format('Y-m-d H:i:s') ?? $task->created_at->format('Y-m-d H:i:s'),
                'check_in_time' => $checkIn?->check_in_time?->format('Y-m-d H:i:s'),
                'check_out_time' => $checkIn?->check_out_time?->format('Y-m-d H:i:s'),
                'notes' => $checkIn?->notes,
            ];
        });

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب الطلبات المعلقة بنجاح',
            'data' => [
                'pending_requests' => $result,
                'total_pending' => $result->count(),
            ]
        ], 200);
    }


    public function evaluations(Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunterProfile;

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


    public function statistics(Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunterProfile;

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
            'pending' => VolunteerTask::where('volunteer_id', $volunteer->id)
                ->where('status', 'معلقة')->count(),
            'new_tasks' => VolunteerTask::where('volunteer_id', $volunteer->id)
                ->where('status', 'جديدة')->count(),
            'completed' => $completedTasks,
            'cancelled' => VolunteerTask::where('volunteer_id', $volunteer->id)
                ->where('status', 'ملغية')->count(),
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
                    'is_available' => $this->isVolunteerAvailable($volunteer->id),
                ],
                'statistics' => [
                    'total_hours' => round($totalHours, 1),
                    'completed_tasks' => $completedTasks,
                    'average_rating' => round($averageRating, 1),
                    'total_tasks' => $stats['total_tasks'],
                    'in_progress' => $stats['in_progress'],
                    'pending' => $stats['pending'],
                    'new_tasks' => $stats['new_tasks'],
                    'cancelled' => $stats['cancelled'],
                    'certificates' => $stats['certificates'],
                ]
            ]
        ], 200);
    }


    public function points(Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunterProfile;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }

        $points = $volunteer->points ?? 0;
        $rank = $this->calculateRank($volunteer->id);
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


    public function leaderboard(Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunterProfile;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }

        $topVolunteers = VolunterProfile::with('user')
            ->orderBy('points', 'desc')
            ->limit(10)
            ->get();

        $leaderboard = $topVolunteers->map(function($v, $index) use ($volunteer) {
            return [
                'rank' => $index + 1,
                'name' => $v->user?->name ?? 'متطوع',
                'points' => $v->points ?? 0,
                'badge' => $this->getRankBadge($index + 1),
                'is_current_user' => $v->id === $volunteer->id,
            ];
        });

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


    private function isVolunteerAvailable($volunteerId)
    {

        $hasInProgressTasks = VolunteerTask::where('volunteer_id', $volunteerId)
            ->where('status', 'قيد التنفيذ')
            ->exists();

        $hasPendingTasks = VolunteerTask::where('volunteer_id', $volunteerId)
            ->where('status', 'معلقة')
            ->exists();


        $hasActiveCheckIn = VolunteerCheckIn::where('volunteer_id', $volunteerId)
            ->whereNull('check_out_time')
            ->exists();

        return !($hasInProgressTasks || $hasPendingTasks || $hasActiveCheckIn);
    }


    private function hasPendingTasks($volunteerId)
    {
        return VolunteerTask::where('volunteer_id', $volunteerId)
            ->where('status', 'معلقة')
            ->exists();
    }


    private function hasInProgressTasks($volunteerId)
    {
        return VolunteerTask::where('volunteer_id', $volunteerId)
            ->where('status', 'قيد التنفيذ')
            ->exists();
    }


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
                        ' تهانينا حصلت على شهادة جديدة',
                        "حصلت على شهادة '{$cert->title}' بعد إكمال {$cert->hours_required} ساعة تطوع",
                        'certificate',
                        ['certificate_id' => $cert->id]
                    );
                }
            }
        }
    }


    private function calculateRank($volunteerId)
    {
        $points = VolunterProfile::where('id', $volunteerId)->value('points') ?? 0;
        return VolunterProfile::where('points', '>', $points)->count() + 1;
    }


    private function getRankBadge($rank)
    {
        return match($rank) {
            1 => '🥇',
            2 => '🥈',
            3 => '🥉',
            default => '⭐',
        };
    }


    private function updatePoints($volunteer, $duration)
    {
        $pointsEarned = round($duration * 10);
        $volunteer->increment('points', $pointsEarned);
        $this->updateBadges($volunteer);
    }


    private function updateBadges($volunteer)
    {
        $points = $volunteer->points ?? 0;
        $badges = [];

        if ($points >= 500) {
            $badges[] = ['name' => 'نشط',  'description' => '500+ نقطة'];
        }
        if ($points >= 1000) {
            $badges[] = ['name' => 'ممتاز', 'description' => '1000+ نقطة'];
        }
        if ($points >= 2000) {
            $badges[] = ['name' => 'إنساني', 'description' => '2000+ نقطة'];
        }
        if ($points >= 3000) {
            $badges[] = ['name' => 'أسطورة', 'description' => '3000+ نقطة'];
        }
        if ($points >= 5000) {
            $badges[] = ['name' => 'نشط جدا', 'description' => '5000+ نقطة'];
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
      public function startTask($id, Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunterProfile;
        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }

        $task = VolunteerTask::find($id);

        if (!$task) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'المهمة غير موجودة',
            ], 404);
        }

        if ($task->status !== 'جديدة') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لا يمكن بدء مهمة غير جديدة',
            ], 400);
        }

        if ($task->volunteer_id && $task->volunteer_id != $volunteer->id) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'هذه المهمة مأخوذة من قبل متطوع آخر',
                'task_volunteer_id' => $task->volunteer_id,
            ], 403);
        }

        $wasOpenTask = is_null($task->volunteer_id);

        // ✅ مهمة أسندها الأدمن مسبقًا -> بدء مباشر بدون موافقة إضافية
        if (!$wasOpenTask) {
            return $this->activateTask($task, $volunteer, $request, null);
        }

        // ✅ مهمة مفتوحة والمتطوع بدو ياخدها لحاله -> ترسل كطلب بانتظار موافقة الإدارة
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

        $task->update([
            'volunteer_id' => $volunteer->id,
            'status' => 'معلقة',
            'awaiting_approval' => 'start',
            'requested_at' => now(),
            'requested_latitude' => $request->latitude,
            'requested_longitude' => $request->longitude,
            'rejection_reason' => null,
        ]);

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم إرسال طلب بدء المهمة، بانتظار موافقة الإدارة',
            'data' => [
                'task' => $task->fresh(),
                'pending_approval' => true,
            ],
        ], 200);
    }
public function endTask($id, Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunterProfile;
        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }

        $task = VolunteerTask::find($id);

        if (!$task) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'المهمة غير موجودة',
            ], 404);
        }

        if ($task->status !== 'قيد التنفيذ') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'المهمة ليست قيد التنفيذ',
            ], 400);
        }

        if ($task->awaiting_approval === 'end') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'تم إرسال طلب إنهاء لهذه المهمة سابقًا وهو بانتظار المراجعة',
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

        $task->update([
            'awaiting_approval' => 'end',
            'requested_at' => now(),
            'rejection_reason' => null,
        ]);

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم إرسال طلب إنهاء المهمة، بانتظار مراجعة الإدارة للتأكد من إنجازها',
            'data' => [
                'task' => $task->fresh(),
            ],
        ], 200);
    }
public function reviewStartRequest($id, Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:accept,reject',
            'rejection_reason' => 'required_if:action,reject|nullable|string|max:500',
        ]);

        $task = VolunteerTask::with('volunteer.user')->find($id);
        if (!$task) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'المهمة غير موجودة',
            ], 404);
        }

        if ($task->awaiting_approval !== 'start') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لا يوجد طلب بدء بانتظار المراجعة لهذه المهمة',
            ], 400);
        }

        $volunteer = $task->volunteer;

        if ($validated['action'] === 'accept') {
            $result = $this->activateTask($task, $volunteer, $request, null);

            if ($volunteer && $volunteer->user) {
                Notification::sendPushOnly(
                    $volunteer->user->id,
                    'تم قبول طلبك',
                    "تم قبول طلبك لبدء المهمة '{$task->title}'",
                    'task_start_approved',
                    ['task_id' => $task->id]
                );
            }

            return $result;
        }
        $task->update([
            'status' => 'جديدة',
            'volunteer_id' => null,
            'awaiting_approval' => null,
            'requested_at' => null,
            'requested_latitude' => null,
            'requested_longitude' => null,
            'rejection_reason' => $validated['rejection_reason'] ?? null,
            'reviewed_by' => null,
            'reviewed_at' => now(),
        ]);

        if ($volunteer && $volunteer->user) {
            Notification::sendPushOnly(
                $volunteer->user->id,
                '❌ تم رفض طلبك',
                "تم رفض طلبك لبدء المهمة '{$task->title}'" . (!empty($validated['rejection_reason']) ? " - السبب: {$validated['rejection_reason']}" : ''),
                'task_start_rejected',
                ['task_id' => $task->id]
            );
        }

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم رفض طلب بدء المهمة، وأصبحت متاحة للمتطوعين الآخرين',
            'data' => ['task' => $task->fresh()],
        ], 200);
    }
public function reviewEndRequest($id, Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:accept,reject',
            'rejection_reason' => 'required_if:action,reject|nullable|string|max:500',
        ]);

        $task = VolunteerTask::with('volunteer.user')->find($id);
        if (!$task) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'المهمة غير موجودة',
            ], 404);
        }

        if ($task->awaiting_approval !== 'end') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لا يوجد طلب إنهاء بانتظار المراجعة لهذه المهمة',
            ], 400);
        }

        $volunteer = $task->volunteer;

        $checkIn = VolunteerCheckIn::where('task_id', $task->id)
            ->where('volunteer_id', $task->volunteer_id)
            ->whereNull('check_out_time')
            ->first();

        if (!$checkIn) {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لا يوجد تسجيل حضور نشط لهذه المهمة',
            ], 400);
        }
        if ($validated['action'] === 'reject') {
            $task->update([
                'awaiting_approval' => null,
                'requested_at' => null,
                'rejection_reason' => $validated['rejection_reason'] ?? null,
                'reviewed_by' => null,
                'reviewed_at' => now(),
            ]);

            if ($volunteer && $volunteer->user) {
                Notification::sendPushOnly(
                    $volunteer->user->id,
                    'تم رفض طلب إنهاء المهمة',
                    "لم تتم الموافقة على إنهاء المهمة '{$task->title}'، الرجاء متابعة العمل عليها" . (!empty($validated['rejection_reason']) ? " - السبب: {$validated['rejection_reason']}" : ''),
                    'task_end_rejected',
                    ['task_id' => $task->id]
                );
            }

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم رفض طلب إنهاء المهمة، وبقيت قيد التنفيذ',
                'data' => ['task' => $task->fresh()],
            ], 200);
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
                'awaiting_approval' => null,
                'requested_at' => null,
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => now(),
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

            if ($task->aidApplication) {
                $task->aidApplication->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                Notification::sendPushOnly(
                    $task->aidApplication->user_id,
                    'تم إكمال طلب المساعدة',
                    "تم إكمال طلب المساعدة '{$task->title}' بواسطة المتطوع {$volunteer->user->name}",
                    'aid_completed',
                    ['application_id' => $task->aidApplication->id]
                );
            }


            if ($task->visit) {
                $task->visit->update(['status' => 'مكتملة']);
            }

            $this->updateCertificates($volunteer);
            $this->updatePoints($volunteer, $duration);

            if ($task->beneficiary_id) {
                Notification::sendPushOnly(
                    $task->beneficiary_id,
                    'تم إكمال المهمة',
                    "تم إكمال المهمة '{$task->title}' بواسطة المتطوع {$volunteer->user->name}",
                    'task_completed',
                    ['task_id' => $task->id]
                );
            }

            if ($volunteer->user) {
                Notification::sendPushOnly(
                    $volunteer->user->id,
                    '✅ تم قبول إنهاء المهمة',
                    "تمت الموافقة على إنهاء المهمة '{$task->title}' بنجاح",
                    'task_end_approved',
                    ['task_id' => $task->id]
                );
            }

            DB::commit();

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم قبول طلب الإنهاء، وتم إكمال المهمة بنجاح',
                'data' => [
                    'task' => $task->fresh(),
                    'check_in' => $checkIn,
                    'duration_hours' => round($duration, 2),
                    'total_hours' => $volunteer->total_hours,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء قبول إنهاء المهمة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
public function pendingApprovals(Request $request)
    {
        $query = VolunteerTask::whereNotNull('awaiting_approval')
            ->with(['volunteer.user', 'supervisor', 'campaign', 'beneficiary', 'aidApplication', 'visit']);

        if ($request->filled('type')) {
            $query->where('awaiting_approval', $request->type); // start | end
        }

        $tasks = $query->orderBy('requested_at', 'desc')->paginate(20);

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب طلبات المراجعة المعلقة بنجاح',
            'data' => $tasks,
        ], 200);
    }
    /**
     * ✅ تابع جديد: جلب كل مهام المتطوعين في التطبيق (بدون شروط)
     */
    public function getAllTasks(Request $request)
    {
        $query = VolunteerTask::with(['supervisor', 'beneficiary', 'aidApplication', 'visit', 'campaign', 'checkIns']);

        // تصفية حسب الحالة (اختياري للواجهة)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tasks = $query->latest()->get();

        $tasks->transform(function ($task) {
            $task->status_text = $task->status_text;
            $task->source_type = $task->source_type;
            $task->source_name = $task->source_name;
            $task->beneficiary_name = $task->beneficiary_name;

            // إضافة معلومات إضافية إذا كانت المهمة مرتبطة بحملة أو طلب مساعدة
            if ($task->campaign) {
                $task->campaign_title = $task->campaign->title;
            }
            if ($task->aidApplication) {
                $task->aid_type = $task->aidApplication->type;
                $task->aid_is_urgent = $task->aidApplication->is_urgent;
            }
            if ($task->visit) {
                $task->visit_date = $task->visit->formatted_date;
            }

            return $task;
        });

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب جميع المهام بنجاح',
            'data' => $tasks
        ], 200);
    }

    /**
     * ✅ تابع جديد: جلب مهام متطوع محدد (عبر الـ ID)
     */
    public function getVolunteerTasks($volunteerId, Request $request)
    {
        $volunteer = VolunterProfile::find($volunteerId);

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'المتطوع غير موجود',
            ], 404);
        }

        $query = VolunteerTask::where('volunteer_id', $volunteerId)
            ->with(['supervisor', 'beneficiary', 'aidApplication', 'visit', 'campaign', 'checkIns']);

        // تصفية حسب الحالة (اختياري للواجهة)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tasks = $query->latest()->get();

        $tasks->transform(function ($task) {
            $task->status_text = $task->status_text;
            $task->source_type = $task->source_type;
            $task->source_name = $task->source_name;
            $task->beneficiary_name = $task->beneficiary_name;

            if ($task->campaign) {
                $task->campaign_title = $task->campaign->title;
            }
            if ($task->aidApplication) {
                $task->aid_type = $task->aidApplication->type;
                $task->aid_is_urgent = $task->aidApplication->is_urgent;
            }
            if ($task->visit) {
                $task->visit_date = $task->visit->formatted_date;
            }

            return $task;
        });

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب مهام المتطوع بنجاح',
            'data' => [
                'volunteer' => [
                    'id' => $volunteer->id,
                    'name' => $volunteer->user->name ?? 'غير معروف',
                    'total_hours' => $volunteer->total_hours,
                ],
                'tasks' => $tasks
            ]
        ], 200);
    }
        /**
     * ✅ تابع إنشاء مهمة متطوع يدوياً (مطابق للـ Migration)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'priority' => 'nullable|string',
            'volunteerId' => 'nullable|integer|exists:volunter_profiles,id',
            'due' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        try {
            // 1. تحديد المشرف (الأدمن)
            $admin = User::whereIn('role', ['admin', 'Admin'])->first();
            $supervisorId = $admin ? $admin->id : 1;

            // 2. ترجمة الأولوية (Priority) لتتطابق مع الـ Enum في قاعدة البيانات
            $priorityMap = [
                'عاجل' => 'عاجلة',
                'متوسط' => 'متوسطة',
                'عادي' => 'منخفضة',
            ];
            $priority = $priorityMap[$validated['priority'] ?? 'متوسط'] ?? 'متوسطة';

            // 3. ترجمة الحالة (Status) لتتطابق مع الـ Enum في قاعدة البيانات
            $statusMap = [
                'جديد' => 'جديدة',
                'قيد التنفيذ' => 'قيد التنفيذ',
                'مكتمل' => 'مكتملة',
                'ملغي' => 'ملغية',
                'معلقة' => 'معلقة',
            ];
            $status = $statusMap[$validated['status'] ?? 'جديد'] ?? 'جديدة';

            // 4. دمج (التصنيف والملاحظات) في حقل الوصف (description) لأن الجدول لا يملك حقل category
            $description = $validated['note'] ?? '';
            if (isset($validated['category']) && $validated['category'] !== 'مهام عامة') {
                $description = "التصنيف: " . $validated['category'] . "\n" . $description;
            }

            // 5. إنشاء المهمة بحقول تطابق الـ Migration تماماً
            $task = VolunteerTask::create([
                'title' => $validated['title'],
                'description' => $description,
                'location' => $validated['location'] ?? null,
                'priority' => $priority,
                'due_date' => $validated['due'] ?? null, // الرايكت يرسل due والداتا بيز تطلب due_date
                'volunteer_id' => $validated['volunteerId'] ?? null, // الرايكت يرسل volunteerId والداتا بيز تطلب volunteer_id
                'supervisor_id' => $supervisorId,
                'status' => $status,
                'progress_percentage' => 0,
                'points_earned' => 0,
            ]);

            return response()->json([
                'code' => '201',
                'success' => true,
                'message' => 'تم إنشاء المهمة بنجاح',
                'data' => $task
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء المهمة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ تابع جديد: إتمام مهمة متطوع يدوياً من لوحة التحكم
     */
    public function completeTask($id, Request $request)
    {
        $task = VolunteerTask::find($id);

        if (!$task) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'المهمة غير موجودة'
            ], 404);
        }

        try {
            // تحديث حالة المهمة إلى مكتملة
            $task->update([
                'status' => 'مكتملة',
                'completed_at' => now(),
                'progress_percentage' => 100,
                'awaiting_approval' => null, // إلغاء أي طلبات معلقة
            ]);

            // إذا كانت المهمة مرتبطة بطلب مساعدة، نحدث حالته أيضاً
            if ($task->aidApplication) {
                $task->aidApplication->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            // إذا كانت المهمة مرتبطة بزيارة، نحدث حالتها
            if ($task->visit) {
                $task->visit->update(['status' => 'مكتملة']);
            }

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم إتمام المهمة بنجاح',
                'data' => $task->fresh()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء إتمام المهمة',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * ✅ إسناد مهمة لمتطوع معين (للأدمن)
     */
    public function assign($id, Request $request)
    {
        $validated = $request->validate([
            'volunteer_id' => 'required|integer|exists:volunter_profiles,id',
        ]);

        $task = VolunteerTask::find($id);
        if (!$task) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'المهمة غير موجودة'
            ], 404);
        }

        try {
            // إسناد المهمة للمتطوع وتغيير حالتها إلى قيد التنفيذ
            $task->update([
                'volunteer_id' => $validated['volunteer_id'],
                'status' => 'قيد التنفيذ',
                'supervisor_id' => $request->user()->id ?? 1,
            ]);

            // إرسال إشعار للمتطوع (اختياري)
            $volunteer = VolunterProfile::with('user')->find($validated['volunteer_id']);
            if ($volunteer && $volunteer->user) {
                Notification::sendPushOnly(
                    $volunteer->user->id,
                    '📢 تم إسناد مهمة جديدة لك',
                    "تم إسناد المهمة '{$task->title}' إليك من قبل الإدارة.",
                    'task_assigned',
                    ['task_id' => $task->id]
                );
            }

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم إسناد المهمة بنجاح',
                'data' => $task->fresh()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء إسناد المهمة',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * ✅ تابع جديد: إتمام مهمة متطوع يدوياً من لوحة التحكم
     */
    public function completeTaskForAdmin($id, Request $request)
    {
        $task = VolunteerTask::find($id);

        if (!$task) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'المهمة غير موجودة'
            ], 404);
        }

        try {
            // تحديث حالة المهمة إلى مكتملة
            $task->update([
                'status' => 'مكتملة',
                'completed_at' => now(),
                'progress_percentage' => 100,
                'awaiting_approval' => null, // إلغاء أي طلبات معلقة
            ]);

            // إذا كانت المهمة مرتبطة بطلب مساعدة، نحدث حالته أيضاً
            if ($task->aidApplication) {
                $task->aidApplication->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            // إذا كانت المهمة مرتبطة بزيارة، نحدث حالتها
            if ($task->visit) {
                $task->visit->update(['status' => 'مكتملة']);
            }

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم إتمام المهمة بنجاح',
                'data' => $task->fresh()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء إتمام المهمة',
                'error' => $e->getMessage()
            ], 500);
        }
    }



}
