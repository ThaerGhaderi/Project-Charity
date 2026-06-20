<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Donor\RecurringDonationRequest;
use App\Http\Requests\RecurringDonationRequest as RequestsRecurringDonationRequest;
use App\Models\RecurringDonation;
use App\Models\Donation;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecurringDonationController extends Controller
{
    /**
     * Create a recurring donation subscription
     */
    public function subscribe(RequestsRecurringDonationRequest $request)
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

            // Create initial donation
            $donation = Donation::create([
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
                'amount' => $request->amount,
                'currency' => 'USD',
                'payment_method' => $request->payment_method,
                'status' => 'pending',
                'is_recurring' => true,
                'donated_at' => now()
            ]);

            // Calculate next charge date
            $nextChargeDate = now();
            switch ($request->frequency) {
                case 'daily':
                    $nextChargeDate = now()->addDay();
                    break;
                case 'weekly':
                    $nextChargeDate = now()->addWeek();
                    break;
                case 'monthly':
                    $nextChargeDate = now()->addMonth();
                    break;
            }

            // Create recurring donation record
            $recurring = RecurringDonation::create([
                'donation_id' => $donation->id,
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
                'amount' => $request->amount,
                'frequency' => $request->frequency,
                'next_charge_date' => $nextChargeDate,
                'is_active' => true
            ]);

            DB::commit();

            // Process first payment
            // In real implementation, this would call payment gateway
            $donation->markAsCompleted();

            return response()->json([
                'success' => true,
                'message' => 'تم تفعيل التبرع الدوري بنجاح',
                'data' => [
                    'recurring_donation' => $recurring,
                    'first_donation' => $donation,
                    'next_charge_date' => $nextChargeDate->format('Y-m-d')
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء التبرع الدوري',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's active recurring donations
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $recurringDonations = RecurringDonation::with(['campaign'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $recurringDonations
        ], 200);
    }

    /**
     * Cancel a recurring donation
     */
    public function cancel($id, Request $request)
    {
        $user = $request->user();
        
        $recurring = RecurringDonation::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        if (!$recurring->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'هذا التبرع الدوري غير نشط بالفعل'
            ], 400);
        }

        $recurring->cancel();

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء التبرع الدوري بنجاح'
        ], 200);
    }

    /**
     * Get recurring donation details
     */
    public function show($id, Request $request)
    {
        $user = $request->user();
        
        $recurring = RecurringDonation::with(['campaign', 'donation'])
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $recurring
        ], 200);
    }
}