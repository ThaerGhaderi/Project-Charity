<?php
// app/Http/Controllers/AidApplicationController.php

namespace App\Http\Controllers;

use App\Models\AidApplication;
use App\Models\BeneficiaryProfile;
use App\Models\Notification;
use App\Http\Requests\AidApplicationRequest;
use App\Http\Requests\UpdateAidApplicationStatusRequest;
use App\Models\User;
use App\Models\VolunteerTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AidApplicationController extends Controller
{
   
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

        $query = AidApplication::where('user_id', $user->id);

       
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

   
    public function show($id, Request $request)
    {
        $user = $request->user();

        $application = AidApplication::where('user_id', $user->id)
            ->with(['beneficiary', 'reviewer'])
            ->findOrFail($id);

        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => $application
        ], 200);
    }

  
    public function store(AidApplicationRequest $request)
    {
        $user = $request->user();

        // Check if user is beneficiary
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

            
            $this->notifyAdmins($application);

            return response()->json([
                'code' => '201',
                'success' => true,
                'message' => 'تم إنشاء طلب المساعدة بنجاح. سيتم مراجعته من قبل الفريق المختص.',
                'data' => $application
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

   
    public function update(AidApplicationRequest $request, $id)
    {
        $user = $request->user();

        $application = AidApplication::where('user_id', $user->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        try {
            $validated = $request->validated();
            $application->update($validated);

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم تحديث طلب المساعدة بنجاح.',
                'data' => $application
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

 
    public function destroy($id, Request $request)
    {
        $user = $request->user();

        $application = AidApplication::where('user_id', $user->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        try {
            $application->delete();

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم حذف طلب المساعدة بنجاح.'
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

        $query = AidApplication::with(['user', 'beneficiary']);

        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
          
            $query->orderByRaw("FIELD(status, 'قيد الانتظار', 'قيد المراجعة', 'مقبولة', 'مرفوضة', 'مكتملة', 'ملغية')");
        }

        
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter urgent
        if ($request->has('is_urgent')) {
            $query->where('is_urgent', $request->boolean('is_urgent'));
        }

        // Search by description
        if ($request->has('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        // Sort
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

        $application = AidApplication::with(['user', 'beneficiary'])->findOrFail($id);

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

            $this->notifyBeneficiary($application, $oldStatus, $newStatus);

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم تحديث حالة الطلب بنجاح.',
                'data' => $application
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

   
    public function types()
    {
        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => AidApplication::TYPES
        ], 200);
    }

  
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

   

    private function notifyAdmins(AidApplication $application)
    {
        $admins = \App\Models\User::whereIn('role', ['admin', 'Admin'])->get();

        $title = $application->is_urgent 
            ? ' طلب مساعدة عاجل جديد!' 
            : ' طلب مساعدة جديد';

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
            'مقبولة' => ' تم قبول طلبك! سنتواصل معك قريباً.',
            'مرفوضة' => ' عذراً، تم رفض طلبك. يمكنك مراجعة الأسباب في الملاحظات.',
            'قيد المراجعة' => ' طلبك قيد المراجعة من قبل الفريق المختص.',
            'مكتملة' => ' تم إكمال طلبك بنجاح.',
            'ملغية' => ' تم إلغاء طلبك.',
        ];

        $title = " تحديث حالة طلب المساعدة";
        $body = $statusMessages[$newStatus] ?? "تم تحديث حالة طلبك إلى: {$application->status_text}";

        if ($newStatus === 'مقبولة' && $application->amount_approved) {
            $body .= " المبلغ المعتمد: {$application->amount_approved} \$";
        }

        if ($newStatus === 'مرفوضة' && $application->admin_notes) {
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
    private function createTaskFromAidApplication($application)
{
    $admin = User::whereIn('role', ['admin', 'Admin'])->first();
    $supervisorId = $admin ? $admin->id : 1;

    VolunteerTask::create([
        'aid_application_id' => $application->id,
        'beneficiary_id' => $application->user_id,
        'title' => "مساعدة: {$application->type} - {$application->user->name}",
        'description' => $application->description,
        'location' => $application->user->profile?->city?->name ?? 'غير محدد',
        'status' => 'جديدة',
        'supervisor_id' => $supervisorId,
        'expected_end_time' => now()->addDays(3),
    ]);
}
}