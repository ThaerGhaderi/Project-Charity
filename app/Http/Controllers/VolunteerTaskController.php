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

    /**
     * عرض المهام المفتوحة للجميع (بدون متطوع محدد)
     *
     * @api {get} /api/volunteer/tasks/available Get Available Tasks
     * @apiHeader Authorization Bearer {token}
     */
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

        // ✅ التحقق من أن المتطوع متاح
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

        // المهام المفتوحة (بدون متطوع)
        $query = VolunteerTask::whereNull('volunteer_id')
            ->where('status', 'جديدة')
            ->with(['supervisor', 'beneficiary', 'visit', 'campaign', 'aidApplication']);

        // تصفية حسب المصدر
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

        // تصفية حسب نوع المهمة
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

        // تصفية حسب الموقع
        if ($request->filled('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        // تصفية حسب الطلبات العاجلة
        if ($request->boolean('urgent_only')) {
            $query->whereHas('aidApplication', function($q) {
                $q->where('is_urgent', true);
            });
        }

        // ترتيب حسب الأحدث
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $tasks = $query->get();

        // إضافة خصائص إضافية
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

        // إحصائيات المهام المتاحة
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

    /**
     * عرض تفاصيل مهمة معينة
     *
     * @api {get} /api/volunteer/tasks/{id} Get Task Details
     * @apiHeader Authorization Bearer {token}
     */
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

        // ✅ معلومات الحضور والانصراف من check-ins
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

    /**
     * طلب بدء المهمة (تسجيل حضور)
     * 
     * ✅ المتطوع يسجل حضور، والمهمة تصبح "معلقة" لحين موافقة الأدمن
     *
     * @api {post} /api/volunteer/tasks/{id}/request-start Request Start Task
     * @apiHeader Authorization Bearer {token}
     */
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

        // البحث عن المهمة
        $task = VolunteerTask::find($id);

        if (!$task) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'المهمة غير موجودة',
            ], 404);
        }

        // ✅ التحقق: المهمة جديدة
        if ($task->status !== 'جديدة') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لا يمكن طلب بدء مهمة غير جديدة',
                'current_status' => $task->status,
            ], 400);
        }

        // ✅ إذا كانت المهمة مفتوحة، خصصها لهذا المتطوع
        if (!$task->volunteer_id) {
            $task->update(['volunteer_id' => $volunteer->id]);
            $task->refresh();
        }

        // ✅ التحقق: المهمة مخصصة لهذا المتطوع
        if ($task->volunteer_id != $volunteer->id) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'هذه المهمة مأخوذة من قبل متطوع آخر',
                'task_volunteer_id' => $task->volunteer_id,
            ], 403);
        }

        // ✅ التحقق من وجود تسجيل حضور نشط
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

            // ✅ إنشاء تسجيل حضور (حاضر) مع بقاء check_out_time = null
            $checkIn = VolunteerCheckIn::create([
                'task_id' => $task->id,
                'volunteer_id' => $volunteer->id,
                'check_in_time' => now(),
                'check_out_time' => null,
                'status' => 'حاضر', // ✅ الحضور مسجل
                'location_verified' => true,
                'latitude' => $request->latitude ?? null,
                'longitude' => $request->longitude ?? null,
                'notes' => $request->notes ?? 'بانتظار موافقة الأدمن',
            ]);

            // ✅ تحديث حالة المهمة إلى "معلقة" (بانتظار موافقة الأدمن)
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

    /**
     * طلب إنهاء المهمة (تسجيل انصراف)
     * 
     * ✅ المتطوع يسجل انصراف، والمهمة تبقى "معلقة" لحين موافقة الأدمن
     *
     * @api {post} /api/volunteer/tasks/{id}/request-end Request End Task
     * @apiHeader Authorization Bearer {token}
     */
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

        // ✅ التحقق: المهمة في حالة قيد التنفيذ أو معلقة
        if (!in_array($task->status, ['قيد التنفيذ', 'معلقة'])) {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لا يمكن طلب انصراف لمهمة ليست قيد التنفيذ أو معلقة',
                'current_status' => $task->status,
            ], 400);
        }

        // ✅ التحقق: المهمة مخصصة لهذا المتطوع
        if ($task->volunteer_id != $volunteer->id) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'هذه المهمة غير مخصصة لك',
            ], 403);
        }

        // ✅ البحث عن تسجيل حضور نشط (بدون انصراف)
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

            // ✅ تحديث تسجيل الحضور بتسجيل الانصراف
            $checkIn->update([
                'check_out_time' => now(),
                'status' => 'منصرف', // ✅ تم الانصراف
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

        // ✅ البحث عن مهمة نشطة (قيد التنفيذ أو معلقة)
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

    /**
     * الحصول على طلبات المتطوع المعلقة
     *
     * @api {get} /api/volunteer/tasks/pending-requests Get Pending Requests
     * @apiHeader Authorization Bearer {token}
     */
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

        // ✅ المهام المعلقة (بانتظار موافقة الأدمن)
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

    /**
     * الحصول على تقييمات المتطوع
     *
     * @api {get} /api/volunteer/evaluations Get Evaluations
     * @apiHeader Authorization Bearer {token}
     */
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

    /**
     * إحصائيات المتطوع
     */
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

    /**
     * الحصول على نقاط المتطوع والشارات
     */
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

    /**
     * لوحة المتصدرين
     */
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

    // ==================== PRIVATE METHODS ====================

    /**
     * التحقق من توفر المتطوع
     */
    private function isVolunteerAvailable($volunteerId)
    {
        // ✅ غير متاح إذا كان لديه:
        // 1. مهام بحالة "قيد التنفيذ"
        // 2. مهام بحالة "معلقة"
        $hasInProgressTasks = VolunteerTask::where('volunteer_id', $volunteerId)
            ->where('status', 'قيد التنفيذ')
            ->exists();

        $hasPendingTasks = VolunteerTask::where('volunteer_id', $volunteerId)
            ->where('status', 'معلقة')
            ->exists();

        // ✅ التحقق من وجود تسجيل حضور نشط (دون انصراف)
        $hasActiveCheckIn = VolunteerCheckIn::where('volunteer_id', $volunteerId)
            ->whereNull('check_out_time')
            ->exists();

        return !($hasInProgressTasks || $hasPendingTasks || $hasActiveCheckIn);
    }

    /**
     * التحقق من وجود مهام معلقة
     */
    private function hasPendingTasks($volunteerId)
    {
        return VolunteerTask::where('volunteer_id', $volunteerId)
            ->where('status', 'معلقة')
            ->exists();
    }

    /**
     * التحقق من وجود مهام قيد التنفيذ
     */
    private function hasInProgressTasks($volunteerId)
    {
        return VolunteerTask::where('volunteer_id', $volunteerId)
            ->where('status', 'قيد التنفيذ')
            ->exists();
    }

    /**
     * تحديث الشهادات
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
     * تحديث النقاط
     */
    private function updatePoints($volunteer, $duration)
    {
        $pointsEarned = round($duration * 10);
        $volunteer->increment('points', $pointsEarned);
        $this->updateBadges($volunteer);
    }

    /**
     * تحديث الشارات
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