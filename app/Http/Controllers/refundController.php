<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Donor\RefundRequest;
use App\Http\Requests\RefundRequest as RequestsRefundRequest;
use App\Models\RefundRequest as RefundRequestModel;
use App\Models\Donation;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    /**
     * Request a refund for a donation
     */
    public function requestRefund(RequestsRefundRequest $request)
    {
        $user = $request->user();
        $donation = Donation::findOrFail($request->donation_id);

        // Check if refund already requested
        $existing = RefundRequestModel::where('donation_id', $donation->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'تم تقديم طلب استرداد لهذا التبرع بالفعل'
            ], 400);
        }

        // Create refund request
        $refundRequest = RefundRequestModel::create([
            'donation_id' => $donation->id,
            'user_id' => $user->id,
            'reason' => $request->reason,
            'status' => 'pending'
        ]);

        // Notify admin (in real implementation)
        // Notification::sendToAdmins('طلب استرداد جديد', $refundRequest->toArray());

        return response()->json([
            'success' => true,
            'message' => 'تم تقديم طلب الاسترداد بنجاح. سيتم مراجعته من قبل الإدارة',
            'data' => [
                'request_id' => $refundRequest->id,
                'status' => 'pending',
                'estimated_response' => 'خلال 3-5 أيام عمل'
            ]
        ], 201);
    }

    /**
     * Get user's refund requests
     */
    public function myRequests(Request $request)
    {
        $user = $request->user();
        
        $requests = RefundRequestModel::with(['donation.campaign'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests
        ], 200);
    }

    /**
     * Get refund request status
     */
    public function status($id, Request $request)
    {
        $user = $request->user();
        
        $refundRequest = RefundRequestModel::with(['donation.campaign'])
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $refundRequest->status,
                'reason' => $refundRequest->reason,
                'admin_notes' => $refundRequest->admin_notes,
                'created_at' => $refundRequest->created_at,
                'processed_at' => $refundRequest->processed_at,
                'donation' => [
                    'amount' => $refundRequest->donation->amount,
                    'campaign' => $refundRequest->donation->campaign->title
                ]
            ]
        ], 200);
    }
}