<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\RefundRequest;
use App\Models\RefundRequest as RefundRequestModel;
use App\Models\Donation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // ✅ إضافة للمعاملة الآمنة

class RefundController extends Controller
{
    public function requestRefund(RefundRequest $request)
    {
        try {
            DB::beginTransaction();
            
            $user = $request->user();
            $donation = Donation::findOrFail($request->donation_id);

            // ✅ التحقق الإضافي (مع أن Form Request يقوم بذلك)
            // لكن يمكنك إزالة هذا لأن Form Request يتحقق منه

            // Create refund request
            $refundRequest = RefundRequestModel::create([
                'donation_id' => $donation->id,
                'user_id' => $user->id,
                'reason' => $request->reason,
                'status' => 'pending',
                'created_at' => now(), // ✅ تأكد من وجود هذه الحقول
            ]);

            DB::commit();

            // ✅ إرسال إشعار (معلق حالياً)
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
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تقديم الطلب: ' . $e->getMessage()
            ], 500);
        }
    }

    public function myRequests(Request $request)
    {
        $user = $request->user();

        $requests = RefundRequestModel::with(['donation.campaign'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15); // ✅ استخدام paginate بدلاً من get

        return response()->json([
            'success' => true,
            'data' => $requests
        ], 200);
    }

    public function status($id, Request $request)
    {
        $user = $request->user();

        $refundRequest = RefundRequestModel::with(['donation.campaign'])
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$refundRequest) {
            return response()->json([
                'success' => false,
                'message' => 'طلب الاسترداد غير موجود أو لا يخصك'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $refundRequest->status,
                'reason' => $refundRequest->reason,
                'admin_notes' => $refundRequest->admin_notes ?? null,
                'created_at' => $refundRequest->created_at,
                'processed_at' => $refundRequest->processed_at ?? null,
                'donation' => [
                    'amount' => $refundRequest->donation->amount,
                    'campaign' => $refundRequest->donation->campaign->title ?? 'حملة غير معروفة'
                ]
            ]
        ], 200);
    }
}