<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\DonationRequest as RequestsDonationRequest;
use App\Http\Requests\Donor\DonationRequest;
use App\Models\Donation;
use App\Models\Campaign;
use App\Models\DonorProfile;
use App\Models\PaymentTransaction;
use App\Models\Notification;
use App\Services\PayerurlService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

class DonationController extends Controller
{
    protected $payerurlService;
    protected $stripeService;

    public function __construct(StripeService $stripeService, PayerurlService $payerurlService)
    {
        $this->stripeService = $stripeService;
        $this->payerurlService = $payerurlService;
    }

    /**
     * Create a new donation (Local Payment)
     * POST /api/donor/donations
     */
  public function store(RequestsDonationRequest $request)
{
    $user = $request->user();
    $donor = $user->donor;

    if (!$donor) {
        return response()->json([
            'success' => false,
            'message' => 'لم يتم العثور على ملف المتبرع'
        ], 404);
    }

    $campaign = Campaign::find($request->campaign_id);

    if (!$campaign) {
        return response()->json([
            'success' => false,
            'message' => 'الحملة غير موجودة'
        ], 404);
    }

    if ($campaign->status !== 'active') {
        return response()->json([
            'success' => false,
            'message' => 'هذه الحملة غير نشطة حالياً'
        ], 400);
    }

    try {
        DB::beginTransaction();

        $donation = Donation::create([
            'donor_id'        => $donor->id,
            'campaign_id'     => $campaign->id,
            'amount'          => $request->amount,
            'currency'        => $request->currency ?? 'USD',
            'payment_method'  => $request->payment_method,
            'payment_gateway' => 'local',
            'status'          => 'pending',
            'is_anonymous'   => $request->is_anonymous ?? false,
            'is_recurring'   => $request->is_recurring ?? false,
            'is_gift'        => false,
            'on_behalf_of'    => $request->on_behalf_of,
            'gift_message'    => $request->gift_message,
            'donated_at'      => now()
        ]);

        $transaction = PaymentTransaction::create([
            'donation_id' => $donation->id,
            'gateway_ref' => 'TXN_' . Str::random(16),
            'amount'      => $request->amount,
            'currency'    => $request->currency ?? 'USD',
            'status'      => 'pending'
        ]);

        // ✅ معالجة الدفع داخل الترانزاكشن أو جلب البيانات الحقيقية منها
        $paymentResult = $this->processPayment($donation, $transaction);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء التبرع بنجاح',
            'data'    => [
                'donation'       => $donation,
                'payment_intent' => [
                    // إرجاع client_secret الحقيقي القادم من معالجة الدفع إن وجد
                    'client_secret' => $paymentResult['client_secret'] ?? ('sim_' . Str::random(32)),
                    'amount'        => $donation->amount,
                    'currency'      => $donation->currency
                ]
            ]
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء إنشاء التبرع',
            'error'   => $e->getMessage()
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

        $donor = $donation->donor;
        if ($donor && $donor->user) {
            Notification::sendPushOnly(
                $donor->user->id,
                'تبرع ناجح 🎉',
                "شكراً لك على تبرعك بقيمة $" . number_format($donation->amount, 2) . " لحملة " . $donation->campaign->title,
                'donation',
                ['donation_id' => $donation->id]
            );
        }

        return true;
    }

    /**
     * ✅ Create a donation via Stripe Checkout
     * POST /api/donor/donations/stripe
     */
   public function createStripePayment(RequestsDonationRequest $request)
{
    $user = $request->user();
    $donor = $user->donor;

    if (!$donor) {
        return response()->json([
            'success' => false,
            'message' => 'لم يتم العثور على ملف المتبرع'
        ], 404);
    }

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
            'donor_id' => $donor->id,
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

        // ✅ استخدام Stripe Checkout Session
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        $checkoutSession = $stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($request->currency ?? 'usd'),
                    'product_data' => [
                        'name' => "تبرع لحملة: {$campaign->title}",
                        'description' => $campaign->description ?? 'تبرع خيري',
                    ],
                    'unit_amount' => (int) ($request->amount * 100),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => config('services.frontend_url') . '/payment/success?donation=' . $donation->id,
            'cancel_url' => config('services.frontend_url') . '/payment/cancel?donation=' . $donation->id,
            'metadata' => [
                'donation_id' => (string) $donation->id,
                'user_id' => (string) $user->id,
                'campaign_id' => (string) $campaign->id,
            ],
            'client_reference_id' => (string) $donation->id,
        ]);

        // ✅ حفظ session_id في gateway_payment_id قبل الـ commit
        $donation->update([
            'gateway_payment_id' => $checkoutSession->id
        ]);

        DB::commit(); // ✅ الـ commit بعد التأكد من نجاح كل شي

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء طلب الدفع بنجاح',
            'data' => [
                'donation_id' => $donation->id,
                'checkout_url' => $checkoutSession->url,
                'session_id' => $checkoutSession->id,
            ]
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack(); // ✅ هلق فعلاً بيلغي إنشاء التبرع لو صار خطأ بأي خطوة

        Log::error('Stripe checkout creation failed: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء إنشاء التبرع',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * ✅ Handle Stripe Webhook
     * POST /api/stripe/webhook
     */
    /**
 * ✅ Handle Stripe Webhook
 * POST /api/stripe/webhook
 */
public function handleStripeWebhook(Request $request)
{
    $payload = $request->getContent();
    $signature = $request->header('Stripe-Signature');

    // التحقق من صحة توقيع Stripe
    if (!$this->stripeService->verifyWebhookSignature($payload, $signature)) {
        Log::warning('Stripe webhook signature verification failed');
        return response()->json(['error' => 'Invalid signature'], 401);
    }

    $event = json_decode($payload, true);

    Log::info('Stripe Webhook Received', [
        'type' => $event['type'] ?? null,
        'payload' => $event,
    ]);

    // نعالج فقط حدث نجاح الـ Checkout
    if (($event['type'] ?? '') === 'checkout.session.completed') {

        $session = $event['data']['object'] ?? null;
        $sessionId = $session['id'] ?? null;

        Log::info('Checkout Session', [
            'session_id' => $sessionId,
            'client_reference_id' => $session['client_reference_id'] ?? null,
            'metadata' => $session['metadata'] ?? [],
        ]);

        if (!$sessionId) {
            Log::error('Stripe webhook: Session ID is missing');
            return response()->json(['status' => 'error'], 400);
        }

        // البحث عن التبرع
        $donation = Donation::where('gateway_payment_id', $sessionId)->first();

        // إذا لم يجده بالـ session_id نجرب بالـ client_reference_id
        if (!$donation && !empty($session['client_reference_id'])) {
            $donation = Donation::find($session['client_reference_id']);
        }

        // إذا لم يجده نجرب بالـ metadata
        if (!$donation && !empty($session['metadata']['donation_id'])) {
            $donation = Donation::find($session['metadata']['donation_id']);
        }

        if (!$donation) {
            Log::error('Donation not found', [
                'session_id' => $sessionId,
                'client_reference_id' => $session['client_reference_id'] ?? null,
                'metadata' => $session['metadata'] ?? [],
            ]);

            return response()->json(['status' => 'donation_not_found'], 404);
        }

        Log::info("Donation Found", [
            'donation_id' => $donation->id,
            'current_status' => $donation->status,
        ]);

        if ($donation->status !== 'completed') {

            $donation->update([
                'status' => 'completed',
                'gateway_status' => 'completed',
            ]);

            if ($donation->campaign) {
                $donation->campaign->updateCollectedAmount();
            }

            if ($donation->donor) {
                $donation->donor->addDonation($donation->amount);
            }

            if ($donation->donor && $donation->donor->user) {
                Notification::sendPushOnly(
                    $donation->donor->user->id,
                    'تبرع ناجح 🎉',
                    "تم تبرعك بقيمة {$donation->amount} {$donation->currency} لحملة {$donation->campaign->title} بنجاح",
                    'donation',
                    ['donation_id' => $donation->id]
                );
            }

            Log::info("Donation {$donation->id} marked as completed.");
        }
    }

    return response()->json(['status' => 'success'], 200);
}
    /**
     * Create a donation via PayerURL
     * POST /api/donor/donations/payerurl
     */
    public function createPayerurlPayment(RequestsDonationRequest $request)
    {
        $user = $request->user();
        $donor = $user->donor;

        if (!$donor) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتبرع'
            ], 404);
        }

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
                'donor_id' => $donor->id,
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
        $donor = $user->donor;

        if (!$donor) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتبرع'
            ], 404);
        }

        $donation = Donation::where('donor_id', $donor->id)
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
     * Handle PayerURL Webhook
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

                $donor = $donation->donor;
                if ($donor) {
                    $donor->addDonation($donation->amount);
                }

                if ($donor && $donor->user) {
                    Notification::sendPushOnly(
                        $donor->user->id,
                        'تبرع ناجح 🎉',
                        "تم تبرعك بقيمة {$donation->amount} {$donation->currency} لحملة {$donation->campaign->title} بنجاح",
                        'donation',
                        ['donation_id' => $donation->id]
                    );
                }
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
     * API: Check payment status
     * GET /api/donor/payments/{donation}/status
     */
    public function checkPaymentStatus($donationId, Request $request)
    {
        $user = $request->user();
        $donor = $user->donor;

        if (!$donor) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتبرع'
            ], 404);
        }

        $donation = Donation::where('donor_id', $donor->id)
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
        $donor = $user->donor;

        if (!$donor) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتبرع'
            ], 404);
        }

        $donation = Donation::with(['campaign'])
            ->where('donor_id', $donor->id)
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
        $donor = $user->donor;

        if (!$donor) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتبرع'
            ], 404);
        }

        $donations = Donation::with(['campaign'])
            ->where('donor_id', $donor->id)
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
        $donor = $user->donor;

        if (!$donor) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتبرع'
            ], 404);
        }

        $monthlyTrend = Donation::where('donor_id', $donor->id)
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
            'total_donations' => Donation::where('donor_id', $donor->id)
                ->where('status', 'completed')
                ->count(),
            'total_amount' => (float) Donation::where('donor_id', $donor->id)
                ->where('status', 'completed')
                ->sum('amount'),
            'campaigns_supported' => Donation::where('donor_id', $donor->id)
                ->where('status', 'completed')
                ->distinct('campaign_id')
                ->count('campaign_id'),
            'last_donation' => Donation::where('donor_id', $donor->id)
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
        $donor = $user->donor;

        if (!$donor) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتبرع'
            ], 404);
        }

        $donation = Donation::with(['campaign', 'paymentTransaction'])
            ->where('donor_id', $donor->id)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $donation
        ], 200);
    }

    /**
     * Download donation receipt as PDF
     * GET /api/donor/donations/{id}/pdf
     */
    public function downloadReceiptPdf($id, Request $request)
    {$user = $request->user();
        $donorProfile = DonorProfile::where('user_id', $user->id)->firstOrFail();

        $donation = Donation::with(['campaign', 'donor.user'])
            ->where('donor_id', $donorProfile->id)
            ->where('id', $id)
            ->firstOrFail();

        $data = [
        'receipt_number'   => 'DON-' . str_pad($donation->id, 8, '0', STR_PAD_LEFT),
        'campaign_title'   => $donation->campaign->title,
        'campaign_category'=> $donation->campaign->category ?? 'عامة',
        'amount'           => number_format($donation->amount, 2),
        'currency'         => $donation->currency,
        'payment_method'   => $this->getPaymentMethodName($donation->payment_method),
        'status'           => $this->getStatusName($donation->status),
        'date'             => $donation->donated_at->format('Y-m-d H:i:s'),
        'donor_name'       => $donation->is_anonymous ? 'متبرع مجهول' : $donation->user->name,
        'is_anonymous'     => $donation->is_anonymous,
        'campaign_id'      => $donation->campaign_id,
        'donation_id'      => $donation->id,
        ];
    $html = view('pdf.donation_receipt', $data)->render();
    $mpdf = new Mpdf([
        'mode'        => 'utf-8',
        'format'      => 'A4',
        'orientation' => 'P',
        'direction'   => 'rtl',
    ]);
    $mpdf->WriteHTML($html);
        // $pdf = Pdf::loadView('pdf.donation_receipt', $data)->setOption('is_unicode', true)->setOption('enable_html5_parser', true);
    return response($mpdf->Output('receipt.pdf', 'S'))->header('Content-Type', 'application/pdf');
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
    public function indexForAdmin()
    {
        $dorations = Donation::with('donor.user','campaign')->latest()->get();
        return response()->json($dorations);
    }


    /**
     * 1. تبرع حر (Free Donation) - دفع لمرة واحدة
     */
    public function createFreeStripePayment(Request $request)
    {
        $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'amount' => 'required|numeric|min:1',
            'is_anonymous' => 'boolean',
        ]);

        $user = $request->user();
        $donor = $user->donor;
        $campaign = Campaign::findOrFail($request->campaign_id);

        try {
            $donation = Donation::create([
                'donor_id' => $donor->id,
                'campaign_id' => $campaign->id,
                'amount' => $request->amount,
                'currency' => 'USD',
                'payment_method' => 'stripe',
                'payment_gateway' => 'stripe',
                'status' => 'pending',
                'is_anonymous' => $request->is_anonymous ?? false,
                'is_recurring' => false,
                'is_gift' => false,
                'donated_at' => now()
            ]);

            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => ['name' => "تبرع لحملة: {$campaign->title}"],
                        'unit_amount' => (int) ($request->amount * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment', // دفع لمرة واحدة
                'success_url' => config('services.frontend_url') . '/payment/success?donation=' . $donation->id,
                'cancel_url' => config('services.frontend_url') . '/payment/cancel?donation=' . $donation->id,
                'metadata' => ['donation_id' => (string) $donation->id],
            ]);

            $donation->update(['gateway_payment_id' => $session->id]);

            return response()->json([
                'success' => true,
                'checkout_url' => $session->url
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 2. تبرع إهدائي (Gift Donation) - مستقل تماماً
     */
    public function createGiftStripePayment(Request $request)
    {
        $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'amount' => 'required|numeric|min:1',
            'on_behalf_of' => 'required|string|max:255', // اسم المهدى إليه
            'gift_message' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $donor = $user->donor;
        $campaign = Campaign::findOrFail($request->campaign_id);

        try {
            $donation = Donation::create([
                'donor_id' => $donor->id,
                'campaign_id' => $campaign->id,
                'amount' => $request->amount,
                'currency' => 'USD',
                'payment_method' => 'stripe',
                'payment_gateway' => 'stripe',
                'status' => 'pending',
                'is_gift' => true, // تبرع إهدائي
                'on_behalf_of' => $request->on_behalf_of,
                'gift_message' => $request->gift_message,
                'donated_at' => now()
            ]);

            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => ['name' => "تبرع إهدائي لحملة: {$campaign->title}"],
                        'unit_amount' => (int) ($request->amount * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => config('services.frontend_url') . '/payment/success?donation=' . $donation->id,
                'cancel_url' => config('services.frontend_url') . '/payment/cancel?donation=' . $donation->id,
                'metadata' => ['donation_id' => (string) $donation->id],
            ]);

            $donation->update(['gateway_payment_id' => $session->id]);

            return response()->json([
                'success' => true,
                'checkout_url' => $session->url
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 3. تبرع دوري (Recurring Donation) - اشتراك شهري
     */
    public function createRecurringStripePayment(Request $request)
    {
        $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'amount' => 'required|numeric|min:1', // المبلغ الشهري
        ]);

        $user = $request->user();
        $donor = $user->donor;
        $campaign = Campaign::findOrFail($request->campaign_id);

        try {
            $donation = Donation::create([
                'donor_id' => $donor->id,
                'campaign_id' => $campaign->id,
                'amount' => $request->amount, // المبلغ المتكرر
                'currency' => 'USD',
                'payment_method' => 'stripe',
                'payment_gateway' => 'stripe',
                'status' => 'pending',
                'is_recurring' => true, // تبرع دوري
                'donated_at' => now()
            ]);

            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            // في التبرع الدوري، يجب إنشاء Price أو استخدام Price موجود مسبقاً في Stripe
            // هنا نقوم بإنشاء Price مؤقت شهري (كل شهر)
            $price = $stripe->prices->create([
                'unit_amount' => (int) ($request->amount * 100),
                'currency' => 'usd',
                'recurring' => ['interval' => 'month'], // يتكرر شهرياً
                'product_data' => ['name' => "اشتراك شهري لحملة: {$campaign->title}"],
            ]);

            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $price->id,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription', // وضع الاشتراك الدوري
                'success_url' => config('services.frontend_url') . '/payment/success?donation=' . $donation->id,
                'cancel_url' => config('services.frontend_url') . '/payment/cancel?donation=' . $donation->id,
                'metadata' => ['donation_id' => (string) $donation->id],
            ]);

            $donation->update(['gateway_payment_id' => $session->id]);

            return response()->json([
                'success' => true,
                'checkout_url' => $session->url
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }



    }
