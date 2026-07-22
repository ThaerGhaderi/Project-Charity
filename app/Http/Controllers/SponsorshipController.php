<?php
// app/Http/Controllers/SponsorshipController.php

namespace App\Http\Controllers;

use App\Models\Sponsorship;
use App\Models\SponsorshipPayment;
use App\Models\SponsorshipMessage;
use App\Models\Notification;
use App\Models\User;
use App\Models\BeneficiaryProfile;
use App\Http\Requests\Sponsorship\SponsorshipRequest;
use App\Http\Requests\SponsorshipRequest as RequestsSponsorshipRequest;
use App\Http\Requests\UpdateSponsorshipRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SponsorshipController extends Controller
{

public function index(Request $request)
{
    $user = $request->user();
    $query = Sponsorship::with(['sponsor', 'beneficiary', 'campaign']);

    
    if ($user->role === 'Donor') {
        $query->where('sponsor_id', $user->id);
    } elseif ($user->role === 'Beneficiary') {
        $query->where('beneficiary_id', $user->id);
    } elseif (!in_array($user->role, ['admin', 'Admin'])) {
        return response()->json([
            'code' => '403',
            'success' => false,
            'message' => 'ليس لديك صلاحية لعرض الكفالات',
        ], 403);
    }

    
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    } else {
        
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
        $user = $request->user();
        
        $sponsorship = Sponsorship::with([
            'sponsor', 
            'beneficiary', 
            'beneficiaryProfile',
            'campaign',
            'payments' => function ($q) {
                $q->orderBy('paid_at', 'desc');
            },
            'messages' => function ($q) use ($user) {
                $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id);
            }
        ])->findOrFail($id);

        $this->authorizeAccess($sponsorship, $user);

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

    public function store(RequestsSponsorshipRequest $request)
    {
        
        $user = $request->user();

        if ($user->role !== 'Donor') {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'فقط المتبرعين يمكنهم إنشاء كفالات',
            ], 403);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'يرجى التحقق من بريدك الإلكتروني أولاً',
            ], 403);
        }

        $beneficiary = User::where('id', $request->beneficiary_id)
            ->where('role', 'Beneficiary')
            ->first();

        if (!$beneficiary) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'المستفيد غير موجود أو غير صالح',
            ], 404);
        }

        if (!$beneficiary->profile_completed) {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'المستفيد لم يكمل ملفه الشخصي بعد',
            ], 400);
        }

        
        $existingActiveSponsorship = Sponsorship::where('beneficiary_id', $beneficiary->id)
            ->whereIn('status', ['نشطة', 'قيد الانتظار'])
            ->first();

        if ($existingActiveSponsorship) {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'هذا المستفيد لديه كفالة نشطة بالفعل أو قيد الانتظار',
            ], 400);
        }

        try {
            $validated = $request->validated();

            $sponsorship = DB::transaction(function () use ($user, $validated, $beneficiary) {
                $remainingPayments = match ($validated['type']) {
                    'شهرية' => 12,
                    'اسبوعية' => 52,
                    'سنوية' => 1,
                    'مرة واحدة' => 1,
                    default => 1,
                };

                if (isset($validated['end_date'])) {
                    $start = isset($validated['start_date']) 
                        ? new \DateTime($validated['start_date']) 
                        : new \DateTime();
                    $end = new \DateTime($validated['end_date']);
                    $diff = $start->diff($end);
                    
                    $remainingPayments = match ($validated['type']) {
                        'شهرية' => $diff->m + ($diff->y * 12),
                        'اسبوعية' => floor(($diff->days) / 7),
                        'سنوية' => $diff->y,
                        default => 1,
                    };
                    $remainingPayments = max(1, $remainingPayments);
                }

                $sponsorship = Sponsorship::create([
                    'sponsor_id' => $user->id,
                    'beneficiary_id' => $validated['beneficiary_id'],
                    'type' => $validated['type'],
                    'amount' => $validated['amount'],
                    'currency' => $validated['currency'] ?? 'SYP',
                    'start_date' => $validated['start_date'] ?? now(),
                    'end_date' => $validated['end_date'] ?? null,
                    'status' => 'قيد الانتظار',
                    'is_anonymous' => $validated['is_anonymous'] ?? false,
                    'message' => $validated['message'] ?? null,
                    'payment_method' => $validated['payment_method'] ?? 'card',
                    'payment_frequency' => $validated['payment_frequency'] ?? $validated['type'],
                    'auto_renew' => $validated['auto_renew'] ?? false,
                    'remaining_payments' => $remainingPayments,
                    'total_paid' => 0,
                ]);

                $this->notifyAdmins($sponsorship);
                $this->notifyBeneficiary($sponsorship, 'new');

                return $sponsorship;
            });

            return response()->json([
                'code' => '201',
                'success' => true,
                'message' => 'تم إنشاء طلب الكفالة بنجاح. في انتظار موافقة الإدارة.',
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


public function update(UpdateSponsorshipRequest $request, $id)
{
    $user = $request->user();
    $sponsorship = Sponsorship::find($id);
    
    if (!$sponsorship) {
        return response()->json([
            'code' => '404',
            'success' => false,
            'message' => 'الكفالة غير موجودة',
        ], 404);
    }

  
    $isAdmin = in_array($user->role, ['admin', 'Admin']);
    $isSponsor = $user->id === $sponsorship->sponsor_id;

    if (!$isAdmin && !$isSponsor) {
        return response()->json([
            'code' => '403',
            'success' => false,
            'message' => 'ليس لديك صلاحية لتحديث هذه الكفالة',
        ], 403);
    }

    
    if (in_array($sponsorship->status, ['مكتملة', 'ملغية']) && !$isAdmin) {
        return response()->json([
            'code' => '400',
            'success' => false,
            'message' => 'لا يمكن تحديث كفالة مكتملة أو ملغية',
        ], 400);
    }

    try {
        $validated = $request->validated();

       
        if (isset($validated['status'])) {
            $newStatus = $validated['status'];
            $oldStatus = $sponsorship->status;

            if ($newStatus === 'ملغية') {
              
                $sponsorship->cancelled_reason = $validated['cancelled_reason'] ?? 'تم الإلغاء من قبل المستخدم';
                $sponsorship->cancelled_at = now();
                $this->notifyBeneficiary($sponsorship, 'cancelled');
            }

            if ($newStatus === 'نشطة' && $oldStatus === 'قيد الانتظار') {
               
                if (!$isAdmin) {
                    return response()->json([
                        'code' => '403',
                        'success' => false,
                        'message' => 'فقط الإدارة يمكنها الموافقة على الكفالات',
                    ], 403);
                }
                $sponsorship->approved_by = $user->id;
                $sponsorship->approved_at = now();
                $sponsorship->next_payment_date = now()->addMonth();
                $this->notifyBeneficiary($sponsorship, 'approved');
                $this->notifySponsor($sponsorship, 'approved');
            }

            $sponsorship->status = $newStatus;
        }

       
        unset($validated['status'], $validated['cancelled_reason']);
        $sponsorship->fill($validated);
        $sponsorship->save();

        $sponsorship->load(['sponsor', 'beneficiary']);

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم تحديث الكفالة بنجاح',
            'data' => $sponsorship,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'code' => '500',
            'success' => false,
            'message' => 'حدث خطأ أثناء تحديث الكفالة',
            'error' => $e->getMessage(),
        ], 500);
    }
}
    public function destroy($id, Request $request)
    {
        $user = $request->user();
        $sponsorship = Sponsorship::findOrFail($id);

        $this->authorizeAccess($sponsorship, $user);

        if ($sponsorship->status === 'مكتملة') {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'لا يمكن إلغاء كفالة مكتملة',
            ], 400);
        }

        try {
            $sponsorship->status = 'ملغية';
            $sponsorship->cancelled_at = now();
            $sponsorship->cancelled_reason = 'تم الإلغاء من قبل المستخدم';
            $sponsorship->save();

            $this->notifyBeneficiary($sponsorship, 'cancelled');

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'تم إلغاء الكفالة بنجاح',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء إلغاء الكفالة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    
   public function payments($id, Request $request)
{
    $user = $request->user();
    $sponsorship = Sponsorship::findOrFail($id);

    $this->authorizeAccess($sponsorship, $user);

    
    $payments = $sponsorship->payments()
        ->orderBy('paid_at', 'desc')
        ->get();

    
    $payments->transform(function ($payment) {
        $payment->status_text = $payment->status;
        $payment->amount_formatted = number_format($payment->amount, 2) . ' ' . ($payment->sponsorship->currency ?? 'SYP');
        $payment->paid_at_formatted = $payment->paid_at ? $payment->paid_at->format('Y-m-d H:i:s') : null;
        return $payment;
    });

    
    $totalPaid = $payments->sum('amount');
    $paymentCount = $payments->count();

    return response()->json([
        'code' => '200',
        'success' => true,
        'message' => 'تم جلب دفعات الكفالة بنجاح',
        'data' => [
            'payments' => $payments,
            'summary' => [
                'total_paid' => $totalPaid,
                'total_payments' => $paymentCount,
                'currency' => $sponsorship->currency ?? 'SYP',
                'remaining_amount' => $sponsorship->remaining_amount,
                'remaining_payments' => $sponsorship->remaining_payments,
            ]
        ]
    ], 200);
}
    
    public function addPayment(Request $request, $id)
    {
        $user = $request->user();
        $sponsorship = Sponsorship::findOrFail($id);

        $this->authorizeAccess($sponsorship, $user);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:card,bank_transfer,wallet',
            'transaction_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $payment = DB::transaction(function () use ($sponsorship, $validated) {
                return $sponsorship->recordPayment(
                    $validated['amount'],
                    $validated['payment_method'],
                    $validated['transaction_id'] ?? null
                );
            });

            $this->notifyBeneficiary($sponsorship, 'payment_received');

            return response()->json([
                'code' => '201',
                'success' => true,
                'message' => 'تم تسجيل الدفعة بنجاح',
                'data' => $payment,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل الدفعة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

 
public function messages($id, Request $request)
{
    $user = $request->user();
    $sponsorship = Sponsorship::findOrFail($id);

    $this->authorizeAccess($sponsorship, $user);

 
    $updatedCount = SponsorshipMessage::where('sponsorship_id', $id)
        ->where('receiver_id', $user->id)
        ->where('is_read', false)
        ->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

    
    $messages = $sponsorship->messages()
        ->with(['sender', 'receiver'])
        ->orderBy('created_at', 'desc')
        ->get();

    
    $messages->transform(function ($message) use ($user) {
        $message->is_from_me = $message->sender_id === $user->id;
        $message->sender_name = $message->sender->name ?? 'غير معروف';
        $message->receiver_name = $message->receiver->name ?? 'غير معروف';
        $message->time_ago = $message->created_at->diffForHumans();
        $message->formatted_date = $message->created_at->format('Y-m-d H:i:s');
        return $message;
    });

    return response()->json([
        'code' => '200',
        'success' => true,
        'message' => 'تم جلب رسائل الكفالة بنجاح' . ($updatedCount > 0 ? " (تم تحديث {$updatedCount} رسالة إلى مقروءة)" : ''),
        'data' => [
            'messages' => $messages,
            'unread_count' => 0, 
            'total' => $messages->count(),
            'updated_to_read' => $updatedCount, 
        ]
    ], 200);
}

   
    public function sendMessage(Request $request, $id)
    {
        $user = $request->user();
        $sponsorship = Sponsorship::findOrFail($id);

        $this->authorizeAccess($sponsorship, $user);

        $validated = $request->validate([
            'message' => 'required|string|min:2|max:1000',
        ]);

        try {
            $receiverId = $user->id === $sponsorship->sponsor_id 
                ? $sponsorship->beneficiary_id 
                : $sponsorship->sponsor_id;

            $message = SponsorshipMessage::create([
                'sponsorship_id' => $sponsorship->id,
                'sender_id' => $user->id,
                'receiver_id' => $receiverId,
                'message' => $validated['message'],
                'type' => $user->id === $sponsorship->sponsor_id 
                    ? 'sponsor_to_beneficiary' 
                    : 'beneficiary_to_sponsor',
                'is_read' => false,
            ]);

            Notification::sendPushOnly(
                $receiverId,
                $user->id === $sponsorship->sponsor_id 
                    ? ' رسالة جديدة من الكافل' 
                    : ' رسالة جديدة من المستفيد',
                $validated['message'],
                'sponsorship_message',
                [
                    'sponsorship_id' => $sponsorship->id,
                    'message_id' => $message->id,
                ]
            );

            return response()->json([
                'code' => '201',
                'success' => true,
                'message' => 'تم إرسال الرسالة بنجاح',
                'data' => $message->load(['sender', 'receiver']),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'حدث خطأ أثناء إرسال الرسالة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

   
    public function availableBeneficiaries(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'Donor') {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'فقط المتبرعين يمكنهم عرض هذه البيانات',
            ], 403);
        }

        
        $beneficiaries = User::where('role', 'Beneficiary')
            ->where('profile_completed', true)
            ->whereDoesntHave('beneficiarySponsorships', function ($q) {
                $q->whereIn('status', ['نشطة', 'قيد الانتظار']);
            })
            ->with(['profile' => function ($q) {
                $q->select('user_id', 'city_id', 'phone', 'bio');
            }, 'profile.city'])
            ->select('id', 'name', 'email')
            ->get();

        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => $beneficiaries->map(function ($beneficiary) {
                return [
                    'id' => $beneficiary->id,
                    'name' => $beneficiary->name,
                    'email' => $beneficiary->email,
                    'profile' => $beneficiary->profile ? [
                        'city' => $beneficiary->profile->city?->name,
                        'phone' => $beneficiary->profile->phone,
                        'bio' => $beneficiary->profile->bio,
                    ] : null,
                ];
            }),
        ], 200);
    }

    
    private function authorizeAccess(Sponsorship $sponsorship, $user)
    {
        $isAdmin = in_array($user->role, ['admin', 'Admin']);
        $isSponsor = $user->id === $sponsorship->sponsor_id;
        $isBeneficiary = $user->id === $sponsorship->beneficiary_id;

        if (!$isAdmin && !$isSponsor && !$isBeneficiary) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'ليس لديك صلاحية لعرض هذه الكفالة'
            );
        }

        return true;
    }

    private function notifyAdmins(Sponsorship $sponsorship)
    {
        $admins = User::whereIn('role', ['admin', 'Admin'])->get();

        $title = ' طلب كفالة جديد';
        $body = "طلب كفالة جديد من {$sponsorship->sponsor->name} بقيمة {$sponsorship->amount} {$sponsorship->currency}";

        foreach ($admins as $admin) {
            Notification::sendPushOnly(
                $admin->id,
                $title,
                $body,
                'sponsorship',
                [
                    'sponsorship_id' => $sponsorship->id,
                    'sponsor_id' => $sponsorship->sponsor_id,
                    'beneficiary_id' => $sponsorship->beneficiary_id,
                ]
            );
        }
    }

    private function notifyBeneficiary(Sponsorship $sponsorship, string $type)
    {
        $messages = [
            'new' => [
                'title' => ' طلب كفالة جديد لك',
                'body' => "تم تقديم طلب كفالة لك من {$sponsorship->sponsor->name}. في انتظار موافقة الإدارة.",
            ],
            'approved' => [
                'title' => ' تم الموافقة على كفالتك',
                'body' => "تمت الموافقة على كفالتك من {$sponsorship->sponsor->name}. مبارك!",
            ],
            'cancelled' => [
                'title' => ' تم إلغاء الكفالة',
                'body' => "تم إلغاء الكفالة من {$sponsorship->sponsor->name}.",
            ],
            'payment_received' => [
                'title' => ' تم استلام دفعة جديدة',
                'body' => "تم استلام دفعة جديدة بقيمة {$sponsorship->amount} {$sponsorship->currency} من الكافل.",
            ],
        ];

        $message = $messages[$type] ?? $messages['new'];

        Notification::sendPushOnly(
            $sponsorship->beneficiary_id,
            $message['title'],
            $message['body'],
            'sponsorship',
            [
                'sponsorship_id' => $sponsorship->id,
                'type' => $type,
            ]
        );
    }

    private function notifySponsor(Sponsorship $sponsorship, string $type)
    {
        if ($type === 'approved') {
            Notification::sendPushOnly(
                $sponsorship->sponsor_id,
                ' تم الموافقة على طلب الكفالة',
                "تمت الموافقة على كفالة المستفيد {$sponsorship->beneficiary->name}.",
                'sponsorship',
                [
                    'sponsorship_id' => $sponsorship->id,
                ]
            );
        }
    }
}