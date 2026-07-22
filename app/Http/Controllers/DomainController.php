<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\DonationRequest as RequestsDonationRequest;
use App\Http\Requests\Donor\DonationRequest;
use App\Models\Donation;
use App\Models\Campaign;
use App\Models\PaymentTransaction;
use App\Models\Notification;
use App\Models\User;
use App\Models\VolunteerTask;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class DomainController extends Controller
{
    /**
     * Create a new donation
     */
    public function store(RequestsDonationRequest $request)
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

        // ✅ تحديث تقدم الحملة
        $campaign = $donation->campaign;
        $campaign->updateCollectedAmount();

        // ✅ Send notification to donor (نفسه ما تغير)
        Notification::send(
            $donation->user_id,
            'تبرع ناجح',
            "شكراً لك على تبرعك بقيمة $" . number_format($donation->amount, 2) . " لحملة " . $donation->campaign->title,
            'donation'
        );

        // ✅ إشعار للمتطوعين عند وصول الحملة لنسبة 50%
        if ($campaign->progress_percentage >= 50) {
            $this->notifyVolunteersForCampaign($campaign);
        }

        // ✅ إنشاء مهام إضافية عند وصول الحملة لنسبة 75%
        if ($campaign->progress_percentage >= 75) {
            $this->createAdditionalTasksForCampaign($campaign);
        }

        return true;
    }

    /**
     * Get donation receipt
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

    // ==================== PRIVATE METHODS ====================

    /**
     * إشعار للمتطوعين عند تقدم الحملة
     */
    private function notifyVolunteersForCampaign($campaign)
    {
        $volunteers = User::where('role', 'volunteer')
            ->whereNotNull('fcm_token')
            ->get();

        $title = "🚀 حملة {$campaign->title} حققت {$campaign->progress_percentage}%";
        $body = "انضم إلينا لاستكمال المهام التطوعية للحملة";

        foreach ($volunteers as $volunteer) {
            Notification::sendPushOnly(
                $volunteer->id,
                $title,
                $body,
                'campaign_progress',
                ['campaign_id' => $campaign->id]
            );
        }
    }

    /**
     * إنشاء مهام إضافية عند وصول الحملة لنسبة 75%
     */
    private function createAdditionalTasksForCampaign($campaign)
    {
        $admin = User::whereIn('role', ['admin', 'Admin'])->first();
        $supervisorId = $admin ? $admin->id : 1;

        $tasks = [
            [
                'title' => "مرحلة التوزيع النهائي - {$campaign->title}",
                'description' => "توزيع المساعدات النهائية على المستفيدين",
                'location' => $campaign->location ?? 'غير محدد',
            ],
            [
                'title' => "توثيق الإنجازات - {$campaign->title}",
                'description' => "توثيق إنجازات الحملة وتصوير المستفيدين",
                'location' => $campaign->location ?? 'غير محدد',
            ],
            [
                'title' => "تقييم الحملة - {$campaign->title}",
                'description' => "تقييم نتائج الحملة وجمع الملاحظات",
                'location' => $campaign->location ?? 'غير محدد',
            ],
        ];

        foreach ($tasks as $taskData) {
            VolunteerTask::create([
                'campaign_id' => $campaign->id,
                'title' => $taskData['title'],
                'description' => $taskData['description'],
                'location' => $taskData['location'],
                'status' => 'جديدة',
                'supervisor_id' => $supervisorId,
                'expected_end_time' => now()->addDays(5),
            ]);
        }
    }
}