<?php
// app/Http/Controllers/VisitController.php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateVisitRequest as RequestsUpdateVisitRequest;
use App\Models\Visit;
use App\Models\Notification;
use App\Models\User;
use App\Http\Requests\Visit\VisitRequest;
use App\Http\Requests\Visit\UpdateVisitRequest;
use App\Http\Requests\VisitRequest as RequestsVisitRequest;
use App\Models\VolunteerTask;
use App\Models\VolunterProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VisitController extends Controller
{
   
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->beneficiary) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'فقط المستفيدين يمكنهم عرض الزيارات',
            ], 403);
        }

        $query = Visit::where('beneficiary_id', $user->id)
            ->with(['socialWorker', 'creator', 'volunteerTask']);

        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        
        if ($request->filled('visit_type')) {
            $query->where('visit_type', $request->visit_type);
        }

       
        if ($request->boolean('upcoming_only')) {
            $query->where('visit_date', '>=', now()->startOfDay())
                ->whereIn('status', ['قيد الانتظار', 'مؤكدة']);
        }

        
        $sortBy = $request->get('sort_by', 'visit_date');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $visits = $query->get();

      
        $visits->transform(function ($visit) {
            $visit->status_text = $visit->status;
            $visit->type_text = $visit->visit_type;
            $visit->formatted_date = $visit->formatted_date;
            $visit->formatted_time = $visit->formatted_time;
            $visit->is_upcoming = $visit->is_upcoming;
            $visit->is_pending = $visit->is_pending;
            $visit->is_confirmed = $visit->is_confirmed;
            $visit->is_completed = $visit->is_completed;
            $visit->is_cancelled = $visit->is_cancelled;
            return $visit;
        });

       
        $stats = [
            'total' => $visits->count(),
            'upcoming' => $visits->where('is_upcoming', true)->count(),
            'pending' => $visits->where('status', 'قيد الانتظار')->count(),
            'confirmed' => $visits->where('status', 'مؤكدة')->count(),
            'completed' => $visits->where('status', 'مكتملة')->count(),
            'cancelled' => $visits->where('status', 'ملغية')->count(),
        ];

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب الزيارات بنجاح',
            'data' => [
                'visits' => $visits,
                'stats' => $stats,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ]
            ]
        ], 200);
    }

    
    public function show($id, Request $request)
    {
        $user = $request->user();

        $visit = Visit::with(['beneficiary', 'socialWorker', 'creator', 'volunteerTask'])
            ->findOrFail($id);

        $this->authorizeAccess($visit, $user);

        $visit->status_text = $visit->status;
        $visit->type_text = $visit->visit_type;
        $visit->formatted_date = $visit->formatted_date;
        $visit->formatted_time = $visit->formatted_time;
        $visit->is_upcoming = $visit->is_upcoming;
        $visit->is_pending = $visit->is_pending;
        $visit->is_confirmed = $visit->is_confirmed;
        $visit->is_completed = $visit->is_completed;
        $visit->is_cancelled = $visit->is_cancelled;

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب تفاصيل الزيارة بنجاح',
            'data' => $visit
        ], 200);
    }

    
    public function store(RequestsVisitRequest $request)
    {
        $user = $request->user();

        $isAdmin = in_array($user->role, ['admin', 'Admin']);
        $isBeneficiary = $user->beneficiary !== null;

        if (!$isAdmin && !$isBeneficiary) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'ليس لديك صلاحية لإنشاء زيارة',
            ], 403);
        }

        try {
            $validated = $request->validated();

            if ($isBeneficiary && !$isAdmin) {
                $validated['beneficiary_id'] = $user->id;
                $validated['status'] = 'قيد الانتظار';
            }

            $validated['created_by'] = $user->id;

            $visit = Visit::create($validated);

            // ✅ ✅ ✅ إنشاء مهمة وربطها مع جميع المتطوعين
            $this->createTaskForAllVolunteers($visit);

            $this->notifyAdmins($visit, 'new');
            
            if ($isAdmin) {
                $this->notifyBeneficiary($visit, 'new');
            }

            return response()->json([
                'code' => '201',
                'success' => true,
                'message' => 'تم إنشاء الزيارة والمهمة المرتبطة بها بنجاح',
                'data' => $visit->load(['beneficiary', 'socialWorker', 'volunteerTask']),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الزيارة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

   
   public function update(RequestsUpdateVisitRequest $request, $id)
{
    
    if (empty($request->all())) {
        $jsonData = $request->json()->all();
        if (!empty($jsonData)) {
            $request->merge($jsonData);
        }
    }

    $user = $request->user();
    
    
    $visit = Visit::find($id);
    
    if (!$visit) {
        return response()->json([
            'code' => '404',
            'success' => false,
            'message' => 'الزيارة غير موجودة',
        ], 404);
    }

   
    $isAdmin = in_array($user->role, ['admin', 'Admin']);
    $isBeneficiary = $user->id === $visit->beneficiary_id;
    $isSocialWorker = $user->id === $visit->social_worker_id;

    if (!$isAdmin && !$isBeneficiary && !$isSocialWorker) {
        return response()->json([
            'code' => '403',
            'success' => false,
            'message' => 'ليس لديك صلاحية لتحديث هذه الزيارة',
            'debug' => [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'beneficiary_id' => $visit->beneficiary_id,
                'social_worker_id' => $visit->social_worker_id,
            ]
        ], 403);
    }

    // ✅ لا يمكن تحديث زيارة مكتملة (إلا للأدمن)
    if ($visit->status === 'مكتملة' && !$isAdmin) {
        return response()->json([
            'code' => '400',
            'success' => false,
            'message' => 'لا يمكن تحديث زيارة مكتملة',
        ], 400);
    }

    try {
        $validated = $request->validated();

      
        if (isset($validated['status'])) {
            $oldStatus = $visit->status;
            $newStatus = $validated['status'];

            if ($newStatus === 'مؤكدة' && $oldStatus === 'قيد الانتظار') {
                $visit->confirmed_at = now();
                $this->notifyBeneficiary($visit, 'confirmed');
            }

            if ($newStatus === 'مكتملة') {
                $visit->completed_at = now();
                
                // ✅ إذا كانت هناك مهمة مرتبطة، حدّث حالتها إلى مكتملة
                if ($visit->volunteerTask) {
                    $visit->volunteerTask->update(['status' => 'مكتملة']);
                }
                
                $this->notifyBeneficiary($visit, 'completed');
            }

            if ($newStatus === 'ملغية') {
                $visit->cancelled_at = now();
                $visit->cancelled_reason = $validated['cancelled_reason'] ?? 'تم الإلغاء';
                
                // ✅ إذا كانت هناك مهمة مرتبطة، حدّث حالتها إلى ملغية
                if ($visit->volunteerTask) {
                    $visit->volunteerTask->update(['status' => 'ملغية']);
                }
                
                $this->notifyBeneficiary($visit, 'cancelled');
            }

            $visit->status = $newStatus;
        }

        
        unset($validated['status'], $validated['cancelled_reason']);
        
        if (!empty($validated)) {
            $visit->fill($validated);
        }

        $visit->save();

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم تحديث الزيارة بنجاح',
            'data' => $visit->fresh()->load(['beneficiary', 'socialWorker', 'volunteerTask']),
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'code' => '422',
            'success' => false,
            'message' => 'خطأ في التحقق من البيانات',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'code' => '500',
            'success' => false,
            'message' => 'حدث خطأ أثناء تحديث الزيارة',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    public function destroy($id, Request $request)
    {
        $user = $request->user();
        $visit = Visit::findOrFail($id);

        $this->authorizeAccess($visit, $user);

        if ($visit->status === 'مكتملة') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لا يمكن إلغاء زيارة مكتملة',
            ], 400);
        }

        try {
            $visit->update([
                'status' => 'ملغية',
                'cancelled_at' => now(),
                'cancelled_reason' => 'تم الإلغاء من قبل المستخدم',
            ]);

            $this->notifyBeneficiary($visit, 'cancelled');

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم إلغاء الزيارة بنجاح',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء إلغاء الزيارة',
                'error' => $e->getMessage(),
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
                'message' => 'فقط المستفيدين يمكنهم عرض الإحصائيات',
            ], 403);
        }

        $stats = [
            'total' => Visit::where('beneficiary_id', $user->id)->count(),
            'upcoming' => Visit::where('beneficiary_id', $user->id)
                ->where('visit_date', '>=', now()->startOfDay())
                ->whereIn('status', ['قيد الانتظار', 'مؤكدة'])
                ->count(),
            'pending' => Visit::where('beneficiary_id', $user->id)
                ->where('status', 'قيد الانتظار')
                ->count(),
            'confirmed' => Visit::where('beneficiary_id', $user->id)
                ->where('status', 'مؤكدة')
                ->count(),
            'completed' => Visit::where('beneficiary_id', $user->id)
                ->where('status', 'مكتملة')
                ->count(),
            'cancelled' => Visit::where('beneficiary_id', $user->id)
                ->where('status', 'ملغية')
                ->count(),
        ];

        $nextVisit = Visit::where('beneficiary_id', $user->id)
            ->where('visit_date', '>=', now()->startOfDay())
            ->whereIn('status', ['قيد الانتظار', 'مؤكدة'])
            ->orderBy('visit_date', 'asc')
            ->orderBy('visit_time', 'asc')
            ->first();

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب إحصائيات الزيارات بنجاح',
            'data' => [
                'stats' => $stats,
                'next_visit' => $nextVisit ? [
                    'id' => $nextVisit->id,
                    'visit_type' => $nextVisit->type_text,
                    'visit_date' => $nextVisit->formatted_date,
                    'visit_time' => $nextVisit->formatted_time,
                    'location' => $nextVisit->location,
                    'status' => $nextVisit->status_text,
                ] : null,
            ]
        ], 200);
    }

    // ==================== PRIVATE METHODS ====================

    private function authorizeAccess(Visit $visit, $user)
    {
        $isAdmin = in_array($user->role, ['admin', 'Admin']);
        $isBeneficiary = $user->id === $visit->beneficiary_id;
        $isSocialWorker = $user->id === $visit->social_worker_id;

        if (!$isAdmin && !$isBeneficiary && !$isSocialWorker) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'ليس لديك صلاحية لعرض هذه الزيارة'
            );
        }
    }

    private function notifyAdmins(Visit $visit, string $type)
    {
        $admins = User::whereIn('role', ['admin', 'Admin'])->get();

        $title = ' طلب زيارة جديد';
        $body = "طلب زيارة جديد من {$visit->beneficiary->name} في {$visit->location}";

        foreach ($admins as $admin) {
            Notification::sendPushOnly(
                $admin->id,
                $title,
                $body,
                'visit',
                [
                    'visit_id' => $visit->id,
                    'beneficiary_id' => $visit->beneficiary_id,
                ]
            );
        }
    }

    private function notifyBeneficiary(Visit $visit, string $type)
    {
        $messages = [
            'new' => [
                'title' => ' تم إنشاء زيارة جديدة',
                'body' => "تم إنشاء زيارة جديدة لك في {$visit->location} بتاريخ {$visit->formatted_date}",
            ],
            'confirmed' => [
                'title' => ' تم تأكيد الزيارة',
                'body' => "تم تأكيد زيارتك في {$visit->location} بتاريخ {$visit->formatted_date} الساعة {$visit->formatted_time}",
            ],
            'completed' => [
                'title' => ' تم إكمال الزيارة',
                'body' => "تم إكمال زيارتك في {$visit->location} بتاريخ {$visit->formatted_date}",
            ],
            'cancelled' => [
                'title' => ' تم إلغاء الزيارة',
                'body' => "تم إلغاء زيارتك في {$visit->location}",
            ],
        ];

        $message = $messages[$type] ?? $messages['new'];

        Notification::sendPushOnly(
            $visit->beneficiary_id,
            $message['title'],
            $message['body'],
            'visit',
            [
                'visit_id' => $visit->id,
                'type' => $type,
            ]
        );
    }

    /**
     * ✅ إنشاء مهمة وربطها مع جميع المتطوعين
     * 
     * @param Visit $visit
     * @return void
     */
    private function createTaskForAllVolunteers($visit)
    {
        // ✅ جلب جميع المتطوعين المتاحين
        $volunteers = VolunterProfile::where('status', 'متاح')->get();
        
        // ✅ إذا لم يوجد متطوعين، أنشئ متطوع افتراضي
        if ($volunteers->isEmpty()) {
            $user = User::create([
                'name' => 'متطوع افتراضي',
                'email' => 'volunteer_default_' . time() . '@test.com',
                'password' => bcrypt('password123'),
                'role' => 'volunteer',
                'profile_completed' => true,
                'email_verified_at' => now(),
            ]);
            
            $volunteer = VolunterProfile::create([
                'user_id' => $user->id,
                'Favorite_period' => 'صباحاً',
                'Commitment_type' => 'منتظم',
                'Educational_level' => 'بكالوريوس',
                'status' => 'متاح',
                'total_hours' => 0,
            ]);
            
            $volunteers = collect([$volunteer]);
        }

        $admin = User::whereIn('role', ['admin', 'Admin'])->first();
        $supervisorId = $admin ? $admin->id : 1;

        // ✅ ✅ ✅ إنشاء مهمة لكل متطوع
        foreach ($volunteers as $volunteer) {
            VolunteerTask::create([
                'visit_id' => $visit->id,
                'beneficiary_id' => $visit->beneficiary_id,
                'volunteer_id' => $volunteer->id,
                'title' => "زيارة ميدانية - {$visit->location}",
                'description' => "زيارة المستفيد {$visit->beneficiary->name} في {$visit->location}",
                'location' => $visit->location,
                'status' => 'جديدة',
                'supervisor_id' => $supervisorId,
                'expected_end_time' => $visit->visit_date,
            ]);
        }
    }
}