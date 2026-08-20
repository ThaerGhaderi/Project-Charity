<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\PaymentTransaction;
use App\Models\GiftDonation;
use App\Http\Requests\GiftDonationRequest;

use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Request;
class GiftDonationController extends Controller
{

public function store(GiftDonationRequest $request)
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

        // 1. إنشاء سجل التبرع الجديد مع تعليمه كـ "هدية"
        $donation = Donation::create([
            'donor_id'        => $donor->id,
            'campaign_id'     => $campaign->id,
            'amount'          => $request->amount,
            'currency'        => $request->currency ?? 'USD',
            'payment_method'  => $request->payment_method,
            'payment_gateway' => 'local',
            'status'          => 'pending',
            'is_anonymous'    => $request->is_anonymous ?? false,
            'is_recurring'    => false,
            'is_gift'         => true,
            'gift_message'    => $request->message,
            'donated_at'      => now()
        ]);

        // 2. إنشاء المعاملة المالية
        $transaction = PaymentTransaction::create([
            'donation_id' => $donation->id,
            'gateway_ref' => 'TXN_' . Str::random(16),
            'amount'      => $request->amount,
            'currency'    => $request->currency ?? 'USD',
            'status'      => 'pending'
        ]);

        // 3. إنشاء سجل الإهداء المرتبط بالتبرع
        $gift = GiftDonation::create([
            'donation_id'     => $donation->id,
            'recipient_name'  => $request->recipient_name,
            'recipient_email' => $request->recipient_email,
            'message'         => $request->message
        ]);

        // 4. معالجة بوابة الدفع
        $paymentResult = $this->processPayment($donation, $transaction);

        // 5. توليد شهادة الإهداء
        $certificateUrl = method_exists($gift, 'generateCertificate') ? $gift->generateCertificate() : null;

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء تبرع الإهداء بنجاح',
            'data'    => [
                'donation'        => $donation,
                'gift'            => $gift,
                'certificate_url' => $certificateUrl ? asset('storage/' . $certificateUrl) : null,
                'payment_intent'  => [
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
            'message' => 'حدث خطأ أثناء إنشاء تبرع الإهداء',
            'error'   => $e->getMessage()
        ], 500);
    }
}
    /**
     * Get gift donations for user
     */
       public function index(Request $request)
    {
        $user = $request->user();
        $donor = $user->donor;

        if (!$donor) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتبرع'
            ], 404);
        }

        // جلب جميع الهدايا الخاصة بهذا المتبرع
        $gifts = Donation::where('donor_id', $donor->id)
                        ->where('is_gift', true)
                        ->with('campaign')
                        ->latest()
                        ->get();

        return response()->json([
            'success' => true,
            'data' => $gifts
        ], 200);
    }
    /**
     * Get gift details
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

        // ✅ البحث في جدول التبرعات الأساسي حيث التبرع الإهدائي
        $donation = Donation::where('id', $id)
                        ->where('donor_id', $donor->id)
                        ->where('is_gift', true)
                        ->with('campaign')
                        ->first();

        if (!$donation) {
            return response()->json([
                'success' => false,
                'message' => 'التبرع الإهدائي غير موجود'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $donation
        ], 200);
    }
}
