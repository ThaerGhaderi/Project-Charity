<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Donor\DonationRequest;
use App\Models\Donation;
use App\Models\Campaign;
use App\Models\PaymentTransaction;
use App\Models\Notification;
use Illuminate\Http\Request;  // ✅ تصحيح: استخدم Illuminate\Http\Request
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class DomainController extends Controller
{
    /**
     * Create a new donation
     */
    public function store(DonationRequest $request)
    {
        $user = $request->user();
        $campaign = Campaign::findOrFail($request->campaign_id);

        // Check if campaign is active
        if ($campaign->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'هذه الحملة غير نشطة حالياً'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Create donation record
            $donation = Donation::create([
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
                'amount' => $request->amount,
                'currency' => $request->currency ?? 'USD',
                'payment_method' => $request->payment_method,
                'status' => 'pending',
                'is_anonymous' => $request->is_anonymous ?? false,
                'is_recurring' => $request->is_recurring ?? false,
                'is_gift' => false,
                'on_behalf_of' => $request->on_behalf_of,
                'gift_message' => $request->gift_message,
                'donated_at' => now()
            ]);

            // Create payment transaction record
            $transaction = PaymentTransaction::create([
                'donation_id' => $donation->id,
                'gateway_ref' => 'TXN_' . Str::random(16),
                'amount' => $request->amount,
                'currency' => $request->currency ?? 'USD',
                'status' => 'pending'
            ]);

            DB::commit();

            // Here you would integrate with payment gateway
            // For now, simulate successful payment
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
        // Simulate payment processing
        $transaction->update([
            'status' => 'success',
            'gateway_response' => json_encode(['status' => 'completed', 'message' => 'Payment successful']),
            'processed_at' => now()
        ]);

        $donation->markAsCompleted();

        // Send notification to donor
        Notification::send(
            $donation->user_id,
            'تبرع ناجح',
            "شكراً لك على تبرعك بقيمة $" . number_format($donation->amount, 2) . " لحملة " . $donation->campaign->title,
            'donation'
        );

        return true;
    }

    /**
     * Get donation receipt
     */
    public function receipt($id, Request $request)  // ✅ تصحيح: أضف $request كمعامل
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
     */
    public function statistics(Request $request)
    {
        $user = $request->user();
        
        // ✅ تصحيح: استخدم طريقة متوافقة مع جميع قواعد البيانات
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
                return ['month' => $month, 'total' => $total];
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
}