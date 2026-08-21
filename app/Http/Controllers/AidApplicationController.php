<?php

namespace App\Http\Controllers;

use App\Models\AidApplication;
use App\Models\BeneficiaryProfile;
use App\Models\Notification;
use App\Http\Requests\AidApplicationRequest;
use App\Http\Requests\UpdateAidApplicationStatusRequest;
use App\Models\User;
use App\Models\VolunteerTask;
use App\Models\VolunterProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AidApplicationController extends Controller
{
    /**
     * عرض طلبات المساعدة الخاصة بالمستفيد
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->beneficiary) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'Only beneficiaries can access aid applications.',
            ], 403);
        }

        $query = AidApplication::where('user_id', $user->id)
            ->with(['beneficiary', 'reviewer', 'volunteerTask']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('is_urgent')) {
            $query->where('is_urgent', $request->boolean('is_urgent'));
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $applications = $query->paginate($perPage);

        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => [
                'applications' => $applications->items(),
                'pagination' => [
                    'current_page' => $applications->currentPage(),
                    'last_page' => $applications->lastPage(),
                    'per_page' => $applications->perPage(),
                    'total' => $applications->total(),
                ]
            ]
        ], 200);
    }

    /**
     * عرض تفاصيل طلب مساعدة
     */
    public function show($id, Request $request)
    {
        $user = $request->user();

        $application = AidApplication::where('user_id', $user->id)
            ->with(['beneficiary', 'reviewer', 'volunteerTask'])
            ->findOrFail($id);

        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => $application
        ], 200);
    }

    /**
     * إنشاء طلب مساعدة جديد
     */
    public function store(AidApplicationRequest $request)
    {
        $user = $request->user();

        if (!$user->beneficiary) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'Only beneficiaries can create aid applications.',
            ], 403);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'Please verify your email first.',
            ], 403);
        }

        try {
            $validated = $request->validated();

            $application = AidApplication::create([
                'beneficiary_profile_id' => $user->beneficiary->id,
                'user_id' => $user->id,
                'type' => $validated['type'],
                'description' => $validated['description'],
                'is_urgent' => $validated['is_urgent'] ?? false,
                'amount_requested' => $validated['amount_requested'] ?? null,
                'status' => 'pending',
            ]);

            $this->createOpenTask($application);
            $this->notifyAdmins($application);

            return response()->json([
                'code' => '201',
                'success' => true,
                'message' => 'تم إنشاء طلب المساعدة والمهمة المفتوحة بنجاح',
                'data' => $application->load('volunteerTask')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء طلب المساعدة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحديث طلب مساعدة
     */
    public function update(AidApplicationRequest $request, $id)
    {
        $user = $request->user();

        $application = AidApplication::where('user_id', $user->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        try {
            $validated = $request->validated();
            $application->update($validated);

            if ($application->volunteerTask) {
                $application->volunteerTask->update([
                    'title' => "مساعدة: {$application->type} - {$application->user->name}",
                    'description' => $application->description,
                ]);
            }

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم تحديث طلب المساعدة بنجاح.',
                'data' => $application->load('volunteerTask')
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث طلب المساعدة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف طلب مساعدة
     */
    public function destroy($id, Request $request)
    {
        $user = $request->user();

        $application = AidApplication::where('user_id', $user->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        try {
            if ($application->volunteerTask) {
                $application->volunteerTask->delete();
            }

            $application->delete();

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم حذف طلب المساعدة والمهمة المرتبطة بنجاح.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف طلب المساعدة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض جميع طلبات المساعدة (للمشرفين)
     */
    public function adminIndex(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'Admin'])) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'Access denied. Admin only.',
            ], 403);
        }

        $query = AidApplication::with(['user', 'beneficiary', 'volunteerTask']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->orderByRaw("FIELD(status, 'pending', 'reviewing', 'approved', 'rejected', 'completed', 'cancelled')");
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('is_urgent')) {
            $query->where('is_urgent', $request->boolean('is_urgent'));
        }

        if ($request->has('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $applications = $query->paginate($perPage);

        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => $applications
        ], 200);
    }

    /**
     * تحديث حالة طلب المساعدة (للمشرفين)
     */
    public function updateStatus(UpdateAidApplicationStatusRequest $request, $id)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'Admin'])) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'Access denied. Admin only.',
            ], 403);
        }

        $application = AidApplication::with(['user', 'beneficiary', 'volunteerTask'])->findOrFail($id);

        try {
            $validated = $request->validated();

            $oldStatus = $application->status;
            $newStatus = $validated['status'];

            $application->status = $newStatus;
            $application->reviewed_by = $user->id;
            $application->reviewed_at = now();

            if (isset($validated['admin_notes'])) {
                $application->admin_notes = $validated['admin_notes'];
            }

            if (isset($validated['amount_approved'])) {
                $application->amount_approved = $validated['amount_approved'];
            }

            $application->save();

            if ($application->volunteerTask) {
                $taskStatus = $this->mapStatusToTaskStatus($newStatus);
                $application->volunteerTask->update([
                    'status' => $taskStatus,
                    'supervisor_notes' => $validated['admin_notes'] ?? null,
                ]);

                if ($newStatus === 'approved' && !$application->volunteerTask->volunteer_id) {
                    // المهمة تبقى مفتوحة للمتطوعين
                }
            }

            $this->notifyBeneficiary($application, $oldStatus, $newStatus);

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم تحديث حالة الطلب والمهمة المرتبطة بنجاح.',
                'data' => $application->load('volunteerTask')
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث حالة الطلب',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إحصائيات طلبات المساعدة
     */
    public function statistics(Request $request)
    {
        $user = $request->user();

        if (!$user->beneficiary) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'Only beneficiaries can access this data.',
            ], 403);
        }

        $stats = [
            'total' => AidApplication::where('user_id', $user->id)->count(),
            'pending' => AidApplication::where('user_id', $user->id)->where('status', 'pending')->count(),
            'reviewing' => AidApplication::where('user_id', $user->id)->where('status', 'reviewing')->count(),
            'approved' => AidApplication::where('user_id', $user->id)->where('status', 'approved')->count(),
            'rejected' => AidApplication::where('user_id', $user->id)->where('status', 'rejected')->count(),
            'completed' => AidApplication::where('user_id', $user->id)->where('status', 'completed')->count(),
            'urgent' => AidApplication::where('user_id', $user->id)->where('is_urgent', true)->count(),
        ];

        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => $stats
        ], 200);
    }

    /**
     * أنواع المساعدات
     */
    public function types()
    {
        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => AidApplication::TYPES
        ], 200);
    }

    /**
     * حالات المساعدات
     */
    public function statuses()
    {
        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => [
                'pending' => 'قيد الانتظار',
                'reviewing' => 'قيد المراجعة',
                'approved' => 'مقبولة',
                'rejected' => 'مرفوضة',
                'completed' => 'مكتملة',
                'cancelled' => 'ملغية'
            ]
        ], 200);
    }

    // ==================== ADMIN METHODS (DASHBOARD) ====================

    /**
     * (للداش بورد) جلب كل الطلبات
     */
    public function getAll(Request $request)
    {
       $query = AidApplication::with(['user.profile.city', 'beneficiary']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $applications = $query->latest()->get();

        $data = $applications->map(function ($item) {
            return [
                'id'          => $item->id,
                'name'        => optional($item->user)->name,
                'phone'       => optional($item->user?->profile)->phone,
                'city'        => optional($item->user?->profile?->city)->name,
                'type'        => $item->type,
                'is_urgent'   => $item->is_urgent,
                'status'      => $item->status,
                'status_text' => $item->status_text, // يأتي تلقائياً من الموديل
                'created_at'  => $item->created_at->format('Y-m-d'),
            ];
        });

        return response()->json([
            'data'  => $data,
            'total' => $data->count(),
        ]);
    }

    /**
     * (للداش بورد) عرض تفاصيل طلب واحد
     */
    public function display($id)
    {
        $item = AidApplication::with(['user.profile.city', 'beneficiary', 'reviewer'])->findOrFail($id);

        return response()->json([
            'data' => [
                'id'                => $item->id,
                'name'              => optional($item->user)->name,
                'phone'             => optional($item->user?->profile)->phone,
                'city'              => optional($item->user?->profile?->city)->name,
                'type'              => $item->type,
                'description'       => $item->description,
                'is_urgent'         => $item->is_urgent,
                'amount_requested'  => $item->amount_requested,
                'amount_approved'   => $item->amount_approved,
                'status'            => $item->status,
                'status_text'       => $item->status_text, // يأتي تلقائياً من الموديل
                'admin_notes'       => $item->admin_notes,
                'reviewed_by'       => optional($item->reviewer)->name,
                'reviewed_at'       => optional($item->reviewed_at)?->format('Y-m-d H:i'),
                'created_at'        => $item->created_at->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * (للداش بورد) تحديث حالة الطلب
     */
    public function updateStory(Request $request, $id)
    {
        $validated = $request->validate([
            'status'          => 'required|in:pending,reviewing,approved,rejected,completed,cancelled,قيد الانتظار,مراجعة,موافقة,مرفوض,مكتمل,ملغة',
            'admin_notes'     => 'nullable|string|max:1000',
            'amount_approved' => 'nullable|numeric',
        ]);

        $item = AidApplication::find($id);
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Aid Application not found.'
            ], 404);
        }

        // ✅ ترجمة الحالة من العربية إلى الإنجليزية قبل حفظها في الداتا بيز
        $statusMap = [
            'قيد الانتظار' => 'pending',
            'مراجعة' => 'reviewing',
            'موافقة' => 'approved',
            'مرفوض' => 'rejected',
            'مكتمل' => 'completed',
            'ملغة' => 'cancelled',
        ];

        $rawStatus = $validated['status'];
        $finalStatus = $statusMap[$rawStatus] ?? $rawStatus; // إذا كانت إنجليزية أصلاً تترك كما هي

        $oldStatus = $item->status;
        $item->status = $finalStatus; // نحفظ الإنجليزية
        $item->reviewed_by = null;
        $item->reviewed_at = now();

        if ($request->filled('admin_notes')) {
            $item->admin_notes = $validated['admin_notes'];
        }

        if ($request->filled('amount_approved')) {
            $item->amount_approved = $validated['amount_approved'];
        }

        $item->save();

        // ✅ إرسال الإشعارات إذا تغيرت الحالة
        if ($item->status !== $oldStatus) {

            $statusMessages = [
                'approved' => '✅ تم قبول طلبك! سنتواصل معك قريباً.',
                'rejected' => '❌ عذراً، تم رفض طلبك. يمكنك مراجعة الأسباب في الملاحظات.',
                'reviewing' => '🔄 طلبك قيد المراجعة من قبل الفريق المختص.',
                'completed' => '✅ تم إكمال طلبك بنجاح.',
                'cancelled' => '❌ تم إلغاء طلبك.',
                'pending' => '⏳ تم إعادة طلبك إلى حالة الانتظار.',
            ];

            $title = "📢 تحديث حالة طلب المساعدة";
            $body = $statusMessages[$item->status] ?? "تم تحديث حالة طلبك إلى: " . $item->status_text;

            if ($item->status === 'approved' && $item->amount_approved) {
                $body .= " المبلغ المعتمد: {$item->amount_approved} \$";
            }

            if ($item->status === 'rejected' && $item->admin_notes) {
                $body .= " السبب: {$item->admin_notes}";
            }

            Notification::sendPushOnly(
                $item->user_id,
                $title,
                $body,
                'aid_application_status',
                [
                    'application_id' => $item->id,
                    'old_status' => $oldStatus,
                    'new_status' => $item->status
                ]
            );

            // ✅ إشعار للمتطوعين بفتح مهمة جديدة (عند الموافقة فقط)
            if ($item->status === 'approved') {
                $volunteers = User::where('role', 'volunteer')->get();
                $taskTitle = "مساعدة: {$item->type} - {$item->user->name}";

                foreach ($volunteers as $volunteer) {
                    Notification::sendPushOnly(
                        $volunteer->id,
                        'مهمة متاحة جديدة 📝',
                        "تم فتح مهمة جديدة باسم: {$taskTitle}. يمكنك الاطلاع عليها في المهام المتاحة.",
                        'volunteer_task',
                        [
                            'application_id' => $item->id,
                            'task_title' => $taskTitle
                        ]
                    );
                }
            }
        }

        return response()->json([
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'data' => [
                'id'          => $item->id,
                'status'      => $item->status,
                'status_text' => $item->status_text,
            ],
        ]);
    }

    // ==================== PRIVATE METHODS ====================

    private function createOpenTask($application)
    {
        $admin = User::whereIn('role', ['admin', 'Admin'])->first();
        $supervisorId = $admin ? $admin->id : 1;

        $location = 'غير محدد';
        if ($application->user && $application->user->profile) {
            $location = $application->user->profile->city?->name ?? 'غير محدد';
        }

        VolunteerTask::create([
            'aid_application_id' => $application->id,
            'beneficiary_id' => $application->user_id,
            'volunteer_id' => null,
            'supervisor_id' => $supervisorId,
            'visit_id' => null,
            'campaign_id' => null,
            'title' => "مساعدة: {$application->type} - {$application->user->name}",
            'description' => $application->description,
            'location' => $location,
            'start_time' => null,
            'end_time' => null,
            'expected_end_time' => now()->addDays(3),
            'status' => 'جديدة',
            'progress_percentage' => 0,
            'points_earned' => 0,
            'supervisor_notes' => null,
            'completed_at' => null,
            'cancelled_at' => null,
        ]);
    }

    private function mapStatusToTaskStatus($status)
    {
        return match($status) {
            'pending', 'reviewing' => 'جديدة',
            'approved' => 'جديدة',
            'completed' => 'مكتملة',
            'rejected', 'cancelled' => 'ملغية',
            default => 'جديدة',
        };
    }

    private function notifyAdmins(AidApplication $application)
    {
        $admins = User::whereIn('role', ['admin', 'Admin'])->get();

        $title = $application->is_urgent
            ? '🚨 طلب مساعدة عاجل جديد!'
            : '📋 طلب مساعدة جديد';

        $body = $application->is_urgent
            ? "طلب عاجل من المستفيد {$application->user->name} من نوع {$application->type}"
            : "طلب مساعدة جديد من المستفيد {$application->user->name} من نوع {$application->type}";

        foreach ($admins as $admin) {
            Notification::sendPushOnly(
                $admin->id,
                $title,
                $body,
                'aid_application',
                [
                    'application_id' => $application->id,
                    'is_urgent' => $application->is_urgent
                ]
            );
        }
    }

    private function notifyBeneficiary(AidApplication $application, $oldStatus, $newStatus)
    {
        $statusMessages = [
            'approved' => '✅ تم قبول طلبك! سنتواصل معك قريباً.',
            'rejected' => '❌ عذراً، تم رفض طلبك. يمكنك مراجعة الأسباب في الملاحظات.',
            'reviewing' => '🔄 طلبك قيد المراجعة من قبل الفريق المختص.',
            'completed' => '✅ تم إكمال طلبك بنجاح.',
            'cancelled' => '❌ تم إلغاء طلبك.',
        ];

        $title = "📢 تحديث حالة طلب المساعدة";
        $body = $statusMessages[$newStatus] ?? "تم تحديث حالة طلبك إلى: " . $this->getStatusText($newStatus);

        if ($newStatus === 'approved' && $application->amount_approved) {
            $body .= " المبلغ المعتمد: {$application->amount_approved} \$";
        }

        if ($newStatus === 'rejected' && $application->admin_notes) {
            $body .= " السبب: {$application->admin_notes}";
        }

        Notification::sendPushOnly(
            $application->user_id,
            $title,
            $body,
            'aid_application_status',
            [
                'application_id' => $application->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]
        );
    }

    private function getStatusText($status)
    {
        return match($status) {
            'pending' => 'قيد الانتظار',
            'reviewing' => 'قيد المراجعة',
            'approved' => 'مقبولة',
            'rejected' => 'مرفوضة',
            'completed' => 'مكتملة',
            'cancelled' => 'ملغية',
            default => $status,
        };
    }
}
