<?php

namespace App\Http\Controllers;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\DonationRequest as RequestsDonationRequest;
use App\Http\Requests\Donor\DonationRequest;
use App\Models\Donation;
use App\Models\Campaign;
use App\Models\PaymentTransaction;
use App\Models\Notification;
use App\Services\PayerurlService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DonationController extends Controller
{
    protected $payerurlService;
      protected $stripeService;

  /*  public function __construct(PayerurlService $payerurlService)
    {
        $this->payerurlService = $payerurlService;
    }*/

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;  // ✅ يجب أن يتم تعيينه
    }
    /**
     * Create a new donation (Local Payment)
     * POST /api/donor/donations
     */
    public function store(RequestsDonationRequest $request)
    {
        $user = $request->user();
        $campaign = Campaign::findOrFail($request->campaign_id);

        if ($campaign->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'هذه الحملة غير نشطة حالياً'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $donation = Donation::create([
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
                'amount' => $request->amount,
                'currency' => $request->currency ?? 'USD',
                'payment_method' => $request->payment_method,
                'payment_gateway' => 'local',
                'status' => 'pending',
                'is_anonymous' => $request->is_anonymous ?? false,
                'is_recurring' => $request->is_recurring ?? false,
                'is_gift' => false,
                'on_behalf_of' => $request->on_behalf_of,
                'gift_message' => $request->gift_message,
                'donated_at' => now()
            ]);

            $transaction = PaymentTransaction::create([
                'donation_id' => $donation->id,
                'gateway_ref' => 'TXN_' . Str::random(16),
                'amount' => $request->amount,
                'currency' => $request->currency ?? 'USD',
                'status' => 'pending'
            ]);

            DB::commit();

            $this->processPayment($donation, $transaction);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء التبرع بنجاح',
                'data' => [
                    'donation' => $donation,
                    'payment_intent' => [
                        'client_secret' => 'sim_' . Str::random(32),
                        'amount' => $donation->amount,
                        'currency' => $donation->currency
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء التبرع',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process payment (webhook handler)
     */
    public function processPayment(Donation $donation, PaymentTransaction $transaction)
    {
        $transaction->update([
            'status' => 'success',
            'gateway_response' => json_encode(['status' => 'completed', 'message' => 'Payment successful']),
            'processed_at' => now()
        ]);

        $donation->markAsCompleted();

        Notification::sendPushOnly(
            $donation->user_id,
            'تبرع ناجح 🎉',
            "شكراً لك على تبرعك بقيمة $" . number_format($donation->amount, 2) . " لحملة " . $donation->campaign->title,
            'donation',
            ['donation_id' => $donation->id]
        );

        return true;
    }

    /**
     * Create a donation via PayerURL
     * POST /api/donor/donations/payerurl
     */
    public function createPayerurlPayment(RequestsDonationRequest $request)
    {
        $user = $request->user();
        $campaign = Campaign::findOrFail($request->campaign_id);

        if ($campaign->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'هذه الحملة غير نشطة حالياً'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $donation = Donation::create([
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
                'amount' => $request->amount,
                'currency' => $request->currency ?? 'USD',
                'payment_method' => $request->payment_method ?? 'crypto',
                'payment_gateway' => 'payerurl',
                'status' => 'pending',
                'gateway_status' => 'pending',
                'is_anonymous' => $request->is_anonymous ?? false,
                'is_recurring' => false,
                'is_gift' => false,
                'on_behalf_of' => $request->on_behalf_of,
                'gift_message' => $request->gift_message,
                'donated_at' => now()
            ]);

            DB::commit();

            $invoiceId = 'DON-' . str_pad($donation->id, 8, '0', STR_PAD_LEFT);
            $amount = (float) $request->amount;
            $currency = $request->currency ?? 'USD';

            // ✅ API Callbacks (بدون web.php)
            $paymentData = [
                'amount' => $amount,
                'currency' => $currency,
                'invoice_id' => $invoiceId,
                'description' => "تبرع لحملة: {$campaign->title}",
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'redirect_url' => config('app.frontend_url') . '/payment/success?donation=' . $donation->id,
                'cancel_url' => config('app.frontend_url') . '/payment/cancel?donation=' . $donation->id,
            ];

            $payment = $this->payerurlService->createPayment($paymentData);

            if (!$payment['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $payment['message']
                ], 400);
            }

            $donation->update([
                'gateway_payment_id' => $payment['payment_id']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب الدفع بنجاح',
                'data' => [
                    'donation_id' => $donation->id,
                    'payment_id' => $payment['payment_id'],
                    'redirect_url' => $payment['redirect_url'],
                    'qr_code' => $payment['qr_code'] ?? null,
                    'expires_at' => $payment['expires_at'] ?? null,
                    'amount' => $amount,
                    'currency' => $currency
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء التبرع',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get donation QR code
     * GET /api/donor/donations/{id}/qr
     */
    public function getDonationQR($id, Request $request)
    {
        $user = $request->user();
        
        $donation = Donation::where('user_id', $user->id)
            ->where('id', $id)
            ->where('payment_gateway', 'payerurl')
            ->firstOrFail();

        if (!$donation->gateway_payment_id) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد رمز QR لهذا التبرع'
            ], 404);
        }

        $payment = $this->payerurlService->getPaymentStatus($donation->gateway_payment_id);

        return response()->json([
            'success' => true,
            'data' => [
                'qr_code' => $payment['qr_code'] ?? null,
                'payment_id' => $donation->gateway_payment_id,
                'status' => $donation->status
            ]
        ], 200);
    }

    /**
     * Handle PayerURL Webhook (تأكيد الدفع من PayerURL)
     * POST /api/payerurl/webhook
     */
    public function handlePayerurlWebhook(Request $request)
    {
        $payload = $request->all();
        
        Log::info('PayerURL Webhook received', $payload);
        
        $paymentId = $payload['payment_id'] ?? $payload['id'] ?? null;
        
        if (!$paymentId) {
            return response()->json(['error' => 'No payment ID'], 400);
        }
        
        $donation = Donation::where('gateway_payment_id', $paymentId)->first();
        
        if (!$donation) {
            return response()->json(['error' => 'Donation not found'], 404);
        }
        
        $status = $payload['status'] ?? 'pending';
        
        if ($status === 'completed' || $status === 'paid') {
            if ($donation->status !== 'completed') {
                $donation->update([
                    'status' => 'completed',
                    'gateway_status' => $status,
                    'crypto_currency' => $payload['crypto_currency'] ?? null,
                    'crypto_amount' => $payload['crypto_amount'] ?? null,
                ]);
                
                $donation->campaign->updateCollectedAmount();
                
                if ($donation->user && $donation->user->donor) {
                    $donation->user->donor->addDonation($donation->amount);
                }
                
                Notification::sendPushOnly(
                    $donation->user_id,
                    'تبرع ناجح 🎉',
                    "تم تبرعك بقيمة {$donation->amount} {$donation->currency} لحملة {$donation->campaign->title} بنجاح",
                    'donation',
                    ['donation_id' => $donation->id]
                );
            }
        } elseif ($status === 'failed' || $status === 'cancelled') {
            $donation->update([
                'status' => 'failed',
                'gateway_status' => $status
            ]);
        }
        
        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * ✅ API: Check payment status (بدلاً من صفحات Redirect)
     * GET /api/donor/payments/{donation}/status
     */
    public function checkPaymentStatus($donationId, Request $request)
    {
        $user = $request->user();
        
        $donation = Donation::where('user_id', $user->id)
            ->where('id', $donationId)
            ->firstOrFail();

        $status = 'pending';
        $message = 'جاري معالجة الدفع';

        if ($donation->gateway_payment_id) {
            $paymentStatus = $this->payerurlService->getPaymentStatus($donation->gateway_payment_id);
            
            if ($paymentStatus['success']) {
                $status = $paymentStatus['status'];
                
                if ($status === 'completed') {
                    $donation->update([
                        'status' => 'completed',
                        'gateway_status' => 'completed'
                    ]);
                    $donation->campaign->updateCollectedAmount();
                    $message = 'تم الدفع بنجاح';
                } elseif ($status === 'failed' || $status === 'cancelled') {
                    $donation->update([
                        'status' => 'failed',
                        'gateway_status' => $status
                    ]);
                    $message = 'فشل الدفع';
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'donation_id' => $donation->id,
                'status' => $donation->status,
                'gateway_status' => $donation->gateway_status,
                'amount' => $donation->amount,
                'currency' => $donation->currency,
                'message' => $message
            ]
        ], 200);
    }

    /**
     * Get donation receipt
     * GET /api/donor/donations/{id}/receipt
     */
    public function receipt($id, Request $request)
    {
        $user = $request->user();
        
        $donation = Donation::with(['campaign'])
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'receipt_number' => 'DON-' . str_pad($donation->id, 8, '0', STR_PAD_LEFT),
                'campaign' => $donation->campaign->title,
                'amount' => $donation->amount,
                'currency' => $donation->currency,
                'payment_method' => $donation->payment_method,
                'status' => $donation->status,
                'date' => $donation->donated_at,
                'is_anonymous' => $donation->is_anonymous,
                'receipt_url' => $donation->receipt_url
            ]
        ], 200);
    }

    /**
     * Get user's donation history
     * GET /api/donor/donations
     */
    public function history(Request $request)
    {
        $user = $request->user();
        
        $donations = Donation::with(['campaign'])
            ->where('user_id', $user->id)
            ->orderBy('donated_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $donations
        ], 200);
    }

    /**
     * Get donation statistics for donor
     * GET /api/donor/donations/statistics
     */
    public function statistics(Request $request)
    {
        $user = $request->user();
        
        $monthlyTrend = Donation::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('donated_at', '>=', now()->subMonths(6))
            ->orderBy('donated_at', 'asc')
            ->get()
            ->groupBy(function($donation) {
                return $donation->donated_at->format('Y-m');
            })
            ->map(function($group) {
                return $group->sum('amount');
            })
            ->map(function($total, $month) {
                return ['month' => $month, 'total' => (float) $total];
            })
            ->values();
        
        $stats = [
            'total_donations' => Donation::where('user_id', $user->id)
                ->where('status', 'completed')
                ->count(),
            'total_amount' => (float) Donation::where('user_id', $user->id)
                ->where('status', 'completed')
                ->sum('amount'),
            'campaigns_supported' => Donation::where('user_id', $user->id)
                ->where('status', 'completed')
                ->distinct('campaign_id')
                ->count('campaign_id'),
            'last_donation' => Donation::where('user_id', $user->id)
                ->where('status', 'completed')
                ->latest('donated_at')
                ->first(),
            'monthly_trend' => $monthlyTrend
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ], 200);
    }

    /**
     * Get single donation details
     * GET /api/donor/donations/{id}
     */
    public function show($id, Request $request)
    {
        $user = $request->user();
        
        $donation = Donation::with(['campaign', 'paymentTransaction'])
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $donation
        ], 200);
    }

  public function downloadReceiptPdf($id, Request $request)
    {
        $user = $request->user();
        
        $donation = Donation::with(['campaign', 'user'])
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        // تحضير البيانات
        $data = [
            'receipt_number' => 'DON-' . str_pad($donation->id, 8, '0', STR_PAD_LEFT),
            'campaign_title' => $donation->campaign->title,
            'campaign_category' => $donation->campaign->category ?? 'عامة',
            'amount' => number_format($donation->amount, 2),
            'currency' => $donation->currency,
            'payment_method' => $this->getPaymentMethodName($donation->payment_method),
            'status' => $this->getStatusName($donation->status),
            'date' => $donation->donated_at->format('Y-m-d H:i:s'),
            'donor_name' => $donation->is_anonymous ? 'متبرع مجهول' : $donation->user->name,
            'is_anonymous' => $donation->is_anonymous,
            'campaign_id' => $donation->campaign_id,
            'donation_id' => $donation->id,
        ];

        // إنشاء PDF
        $pdf = Pdf::loadView('pdf.donation_receipt', $data);
        
        // تحميل PDF
        return $pdf->download("إيصال_تبرع_{$data['receipt_number']}.pdf");
    }

    /**
     * ترجمة طريقة الدفع
     */
    private function getPaymentMethodName($method): string
    {
        $methods = [
            'stripe' => 'بطاقة ائتمان (Stripe)',
            'paypal' => 'باي بال',
            'tap' => 'Tap',
            'moyasar' => 'Moyasar',
            'mada' => 'مدى',
            'apple_pay' => 'Apple Pay',
            'google_pay' => 'Google Pay',
            'crypto' => 'عملة رقمية',
            'payerurl' => 'PayerURL',
            'local' => 'دفع مباشر',
        ];
        
        return $methods[$method] ?? $method;
    }

    /**
     * ترجمة حالة التبرع
     */
    private function getStatusName($status): string
    {
        $statuses = [
            'pending' => 'قيد الانتظار',
            'completed' => 'مكتمل ✅',
            'failed' => 'فشل ❌',
            'refunded' => 'مسترد',
            'cancelled' => 'ملغي',
        ];
        
        return $statuses[$status] ?? $status;
    }




































    public function createStripePayment(RequestsDonationRequest $request)
    {
        $user = $request->user();
        $campaign = Campaign::findOrFail($request->campaign_id);

        if ($campaign->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'هذه الحملة غير نشطة حالياً'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // إنشاء التبرع
            $donation = Donation::create([
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
                'amount' => $request->amount,
                'currency' => $request->currency ?? 'USD',
                'payment_method' => 'stripe',
                'payment_gateway' => 'stripe',
                'status' => 'pending',
                'gateway_status' => 'pending',
                'is_anonymous' => $request->is_anonymous ?? false,
                'is_recurring' => $request->is_recurring ?? false,
                'is_gift' => false,
                'on_behalf_of' => $request->on_behalf_of,
                'gift_message' => $request->gift_message,
                'donated_at' => now()
            ]);

            // إنشاء Payment Intent عبر Stripe
            $paymentData = [
                'amount' => $request->amount,
                'currency' => $request->currency ?? 'usd',
                'donation_id' => $donation->id,
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
                'description' => "تبرع لحملة: {$campaign->title}",
                'email' => $user->email,
            ];

            $stripePayment = $this->stripeService->createPaymentIntent($paymentData);

            if (!$stripePayment['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $stripePayment['message']
                ], 400);
            }

            // تحديث التبرع بمعرف الدفع
            $donation->update([
                'gateway_payment_id' => $stripePayment['payment_intent_id']
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب الدفع بنجاح',
                'data' => [
                    'donation_id' => $donation->id,
                    'client_secret' => $stripePayment['client_secret'],
                    'payment_intent_id' => $stripePayment['payment_intent_id'],
                    'amount' => $stripePayment['amount'],
                    'currency' => $stripePayment['currency'],
                    'publishable_key' => config('services.stripe.key'),
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء التبرع',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ تأكيد دفع Stripe (Client-side confirmation)
     * POST /api/donor/donations/stripe/confirm
     */
    public function confirmStripePayment(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
            'donation_id' => 'required|exists:donations,id',
        ]);

        $user = $request->user();
        $donation = Donation::where('user_id', $user->id)
            ->where('id', $request->donation_id)
            ->firstOrFail();

        // التحقق من حالة الدفع من Stripe
        $paymentStatus = $this->stripeService->confirmPayment($request->payment_intent_id);

        if (!$paymentStatus['success']) {
            return response()->json([
                'success' => false,
                'message' => $paymentStatus['message']
            ], 400);
        }

        if ($paymentStatus['status'] === 'succeeded') {
            // تحديث التبرع
            $donation->update([
                'status' => 'completed',
                'gateway_status' => 'completed'
            ]);

            // تحديث المبلغ المجمع
            $donation->campaign->updateCollectedAmount();

            // تحديث ملف المتبرع
            if ($donation->user && $donation->user->donor) {
                $donation->user->donor->addDonation($donation->amount);
            }

            // إرسال إشعار
            Notification::sendPushOnly(
                $donation->user_id,
                'تبرع ناجح 🎉',
                "تم تبرعك بقيمة {$donation->amount} {$donation->currency} لحملة {$donation->campaign->title} بنجاح",
                'donation',
                ['donation_id' => $donation->id]
            );

            return response()->json([
                'success' => true,
                'message' => 'تم الدفع بنجاح',
                'data' => [
                    'donation_id' => $donation->id,
                    'status' => 'completed',
                    'amount' => $donation->amount,
                    'currency' => $donation->currency
                ]
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'الدفع لم يكتمل بعد',
            'status' => $paymentStatus['status']
        ], 400);
    }

    /**
     * ✅ Webhook Stripe
     * POST /api/stripe/webhook
     */
    public function handleStripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        // التحقق من التوقيع
        $verified = $this->stripeService->verifyWebhookSignature($payload, $signature);

        if (!$verified) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = json_decode($payload, true);
        $eventData = $this->stripeService->handleWebhookEvent($event);

        if ($eventData['type'] === 'payment_intent.succeeded') {
            $paymentIntentId = $eventData['payment_intent_id'];
            
            $donation = Donation::where('gateway_payment_id', $paymentIntentId)->first();

            if ($donation && $donation->status !== 'completed') {
                $donation->update([
                    'status' => 'completed',
                    'gateway_status' => 'completed'
                ]);

                $donation->campaign->updateCollectedAmount();

                if ($donation->user && $donation->user->donor) {
                    $donation->user->donor->addDonation($donation->amount);
                }

                Notification::sendPushOnly(
                    $donation->user_id,
                    'تبرع ناجح 🎉',
                    "تم تبرعك بقيمة {$donation->amount} {$donation->currency} لحملة {$donation->campaign->title} بنجاح",
                    'donation',
                    ['donation_id' => $donation->id]
                );
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

    // ========== باقي الدوال موجودة (store, processPayment, createPayerurlPayment, إلخ) ==========
    // ...
}