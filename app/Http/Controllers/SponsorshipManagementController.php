<?php
// app/Http/Controllers/Admin/SponsorshipManagementController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Sponsorship;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SponsorshipManagementController extends Controller
{
    private function findSponsorship($id, array $with = [])
    {
        return Sponsorship::with($with)->find($id);
    }
    public function dashboard(Request $request)
    {
        $stats = [
            'total' => Sponsorship::count(),
            'pending' => Sponsorship::where('status', 'قيد الانتظار')->count(),
            'active' => Sponsorship::where('status', 'نشطة')->count(),
            'completed' => Sponsorship::where('status', 'مكتملة')->count(),
            'cancelled' => Sponsorship::where('status', 'ملغية')->count(),
            'suspended' => Sponsorship::where('status', 'معلقة')->count(),

            'total_amount_active' => Sponsorship::where('status', 'نشطة')->sum('amount'),
            'total_paid_all' => Sponsorship::sum('total_paid'),

            'by_type' => Sponsorship::selectRaw('type, count(*) as count')->groupBy('type')->pluck('count', 'type'),
            'pending_review' => Sponsorship::where('status', 'قيد الانتظار')
                ->with(['sponsor:id,name,email', 'beneficiary:id,name,email'])
                ->orderBy('created_at', 'asc')
                ->take(10)
                ->get(),
            'overdue_payments' => Sponsorship::where('status', 'نشطة')
                ->whereNotNull('next_payment_date')
                ->where('next_payment_date', '<', now())
                ->with(['sponsor:id,name', 'beneficiary:id,name'])
                ->get(),
        ];

        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => $stats,
        ], 200);
    }
    public function approve(Request $request, $id)
    {
      $sponsorship = $this->findSponsorship($id, ['sponsor', 'beneficiary']);
        if (!$sponsorship) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'الكفالة غير موجودة',
            ], 404);
        }
        // if ($sponsorship->status !== 'قيد الانتظار') {
        //     return response()->json([
        //         'code' => '400',
        //         'success' => false,
        //         'message' => 'لا يمكن الموافقة إلا على كفالة قيد الانتظار',
        //     ], 400);
        // }

        $user = $request->user();
        // 👈 إذا لم يكن مسجلاً للدخول، نضع 1 كافتراضي لتفادي الخطأ
        $approvedById = $user ? $user->id : 1;

        DB::transaction(function () use ($sponsorship, $approvedById) {
            $sponsorship->status = 'نشطة';
            $sponsorship->approved_by = $approvedById; // 👈 استخدمنا المتغير هنا
            $sponsorship->approved_at = now();
            $sponsorship->next_payment_date = $sponsorship->type === 'مرة واحدة'
                ? null
                : now()->addMonth();
            $sponsorship->save();

            // 👈 تحقق إضافي لمنع الخطأ إذا لم يكن هناك كفيل أو مستفيد
            $sponsorName = $sponsorship->sponsor ? $sponsorship->sponsor->name : 'كفيل محذوف';
            $beneficiaryName = $sponsorship->beneficiary ? $sponsorship->beneficiary->name : 'مستفيد محذوف';

            if ($sponsorship->beneficiary_id) {
                Notification::sendPushOnly($sponsorship->beneficiary_id,
                    'تم الموافقة على كفالتك',
                    "تمت الموافقة على كفالتك من {$sponsorName}.",
                    'sponsorship',
                    ['sponsorship_id' => $sponsorship->id]
                );
            }
            if ($sponsorship->sponsor_id) {
                Notification::sendPushOnly(
                    $sponsorship->sponsor_id,
                    'تم الموافقة على طلب الكفالة',
                    "تمت الموافقة على كفالة المستفيد {$beneficiaryName}.",
                    'sponsorship',
                    ['sponsorship_id' => $sponsorship->id]
                );
            }
        });

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تمت الموافقة على الكفالة',
            'data' => $sponsorship,
        ], 200);
    }
         public function reject(Request $request, $id)
    {
        $reason = $request->input('reason', 'تم الرفض من الإدارة');

        $sponsorship = $this->findSponsorship($id, ['sponsor', 'beneficiary']);
        if (!$sponsorship) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'الكفالة غير موجودة',
            ], 404);
        }
        // if ($sponsorship->status !== 'قيد الانتظار') {
        //     return response()->json([
        //         'code' => '400',
        //         'success' => false,
        //         'message' => 'لا يمكن رفض كفالة إلا وهي قيد الانتظار',
        //     ], 400);
        // }
        $sponsorship->status = 'ملغية';
        $sponsorship->cancelled_reason = $reason;
        $sponsorship->cancelled_at = now();
        $sponsorship->save();

        if ($sponsorship->sponsor_id) {
            Notification::sendPushOnly(
                $sponsorship->sponsor_id,
                'تم رفض طلب الكفالة',
                "تم رفض طلب الكفالة. السبب: {$reason}",
                'sponsorship',
                ['sponsorship_id' => $sponsorship->id]
            );
        }

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم رفض طلب الكفالة',
            'data' => $sponsorship, // 👈 أضف هذا السطر فقط
        ], 200);

    }
    public function suspend(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $sponsorship = $this->findSponsorship($id);
        if (!$sponsorship) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'الكفالة غير موجودة',
            ], 404);
        }

        if ($sponsorship->status !== 'نشطة') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'يمكن تعليق الكفالات النشطة فقط',
            ], 400);
        }

        $sponsorship->status = 'معلقة';
        $sponsorship->admin_notes = $validated['reason'] ?? $sponsorship->admin_notes;
        $sponsorship->save();

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم تعليق الكفالة',
            'data' => $sponsorship,
        ], 200);
    }
    public function resume(Request $request, $id)
    {
       $sponsorship = $this->findSponsorship($id);
        if (!$sponsorship) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'الكفالة غير موجودة',
            ], 404);
        }

        if ($sponsorship->status !== 'معلقة') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'يمكن إعادة تفعيل الكفالات المعلقة فقط',
            ], 400);
        }

        $sponsorship->status = 'نشطة';
        $sponsorship->save();

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تمت إعادة تفعيل الكفالة',
            'data' => $sponsorship,
        ], 200);
    }
    public function updateNotes(Request $request, $id)
    {
        $validated = $request->validate([
            'admin_notes' => 'required|string|max:1000',
        ]);

       $sponsorship = $this->findSponsorship($id);
        if (!$sponsorship) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'الكفالة غير موجودة',
            ], 404);
        }
        $sponsorship->admin_notes = $validated['admin_notes'];
        $sponsorship->save();

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم تحديث الملاحظات',
        ], 200);
    }


    public function index(Request $request)
    {
        // جلب جميع الكفالات مع العلاقات (مفتوحة بدون شروط صلاحيات)
        $query = Sponsorship::with(['sponsor.profile.city', 'beneficiary', 'campaign']);

        // الفلاتر تبقى تعمل إذا أرسلها الأدمن من الواجهة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // ترتيب افتراضي حسب الحالة
            $query->orderByRaw("CASE
                WHEN status = 'نشطة' THEN 1
                WHEN status = 'قيد الانتظار' THEN 2
                WHEN status = 'مكتملة' THEN 3
                WHEN status = 'معلقة' THEN 4
                WHEN status = 'ملغية' THEN 5
                ELSE 6
            END");
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', $request->min_amount);
        }
        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', $request->max_amount);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('sponsor', function ($sq) use ($search) {
                    $sq->where('name', 'LIKE', $search)
                       ->orWhere('email', 'LIKE', $search);
                })->orWhereHas('beneficiary', function ($sq) use ($search) {
                    $sq->where('name', 'LIKE', $search)
                       ->orWhere('email', 'LIKE', $search);
                });
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSortColumns = ['created_at', 'amount', 'start_date', 'end_date', 'updated_at'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $sponsorships = $query->get();

        $sponsorships->transform(function ($sponsorship) {
            $sponsorship->status_text = $sponsorship->status;
            $sponsorship->type_text = $sponsorship->type;
            $sponsorship->progress_percentage = round($sponsorship->progress_percentage, 2);
            $sponsorship->remaining_amount = $sponsorship->remaining_amount;
            $sponsorship->is_active = $sponsorship->is_active;

            if ($sponsorship->is_anonymous && $sponsorship->sponsor) {
                $sponsorship->sponsor->name = 'مجهول';
            }

            return $sponsorship;
        });

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب الكفالات بنجاح',
            'data' => $sponsorships,
        ], 200);
    }
    public function show($id, Request $request)
    {
        $sponsorship = Sponsorship::with([
            'sponsor.profile.city', // 👈 تم التعديل هنا
            'beneficiary',
            'beneficiary.profile',
            'campaign',
            'payments' => function ($q) {
                $q->orderBy('paid_at', 'desc');
            },
            // تم تعديل هذا السطر ليجلب كل الرسائل بدون شرط المستخدم (ليشاهدها الأدمن)
            'messages' => function ($q) {
                $q->orderBy('created_at', 'asc');
            }
        ])->findOrFail($id);

        $sponsorship->status_text = $sponsorship->status;
        $sponsorship->type_text = $sponsorship->type;
        $sponsorship->progress_percentage = round($sponsorship->progress_percentage, 2);
        $sponsorship->remaining_amount = $sponsorship->remaining_amount;
        $sponsorship->is_active = $sponsorship->is_active;

        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => $sponsorship,
        ], 200);
    }

        public function store(Request $request)
    {
        // نبحث عن الكفيل والمستفيد بالاسم المرسل من الواجهة، أو نضع 1 كافتراضي
        $sponsor = \App\Models\User::where('name', $request->sponsorName)->first();
        $beneficiary = \App\Models\User::where('name', $request->beneficiaryName)->first();

        try {
            $sponsorship = Sponsorship::create([
                'sponsor_id' => $sponsor ? $sponsor->id : 1,
                'beneficiary_id' => $beneficiary ? $beneficiary->id : 1,
                'type' => $request->type ?? 'شهرية',
                'amount' => $request->amount ?? 0,
                'currency' => $request->currency ?? 'SYP',
                'start_date' => $request->startDate ?? now(),
                'end_date' => $request->endDate ?? null,
                'status' => $request->status ?? 'قيد الانتظار',
                'is_anonymous' => $request->is_anonymous ?? false,
                'message' => $request->notes ?? null,
                'payment_method' => $request->payment_method ?? 'card',
                'payment_frequency' => $request->type ?? 'شهرية',
                'auto_renew' => $request->auto_renew ?? false,
                'remaining_payments' => 12, // قيمة افتراضية
                'total_paid' => 0,
            ]);

            return response()->json([
                'code' => '201',
                'success' => true,
                'message' => 'تم إنشاء الكفالة بنجاح من لوحة التحكم',
                'data' => $sponsorship->load(['sponsor', 'beneficiary']),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الكفالة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف كفالة معينة
     */
    public function destroy($id, Request $request)
    {
        $sponsorship = $this->findSponsorship($id);

        if (!$sponsorship) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'الكفالة غير موجودة',
            ], 404);
        }

        try {
            $sponsorship->delete();

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم حذف الكفالة بنجاح',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الكفالة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف جميع الكفالات
     */
    public function deleteAll(Request $request)
    {
        try {
            Sponsorship::query()->delete();

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم حذف جميع الكفالات بنجاح',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الكفالات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
