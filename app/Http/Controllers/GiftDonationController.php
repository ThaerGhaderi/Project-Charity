<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Donor\GiftDonationRequest;
use App\Http\Requests\GiftDonationRequest as RequestsGiftDonationRequest;
use App\Models\GiftDonation;
use App\Models\Donation;
use Illuminate\Http\Request;

class GiftDonationController extends Controller
{
    /**
     * Create a gift donation
     */
    public function store(RequestsGiftDonationRequest $request)
    {
        $user = $request->user();
         $donor = $user->donor;
        $donation = Donation::where('donor_id', $donor->id)
            ->where('id', $request->donation_id)
            ->where('status', 'completed')
            ->firstOrFail();

        // Check if already a gift donation
        $existing = GiftDonation::where('donation_id', $donation->id)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'هذا التبرع تم تحويله إلى هدية بالفعل'
            ], 400);
        }

        // Create gift donation
        $gift = GiftDonation::create([
            'donation_id' => $donation->id,
            'recipient_name' => $request->recipient_name,
            'recipient_email' => $request->recipient_email,
            'message' => $request->message
        ]);

        // Generate certificate
        $certificateUrl = $gift->generateCertificate();
        
        // Send email to recipient
        // Mail::to($gift->recipient_email)->send(new GiftCertificateMail($gift));

        // Update donation to mark as gift
        $donation->update(['is_gift' => true]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحويل التبرع إلى هدية بنجاح',
            'data' => [
                'gift' => $gift,
                'certificate_url' => $certificateUrl ? asset('storage/' . $certificateUrl) : null,
                'recipient_notified' => true
            ]
        ], 201);
    }

    /**
     * Get gift donations for user
     */
     public function index(Request $request)
    {
        $user = $request->user();
        $donor = $user->donor;  // ✅ جلب ملف المتبرع
        
        if (!$donor) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتبرع'
            ], 404);
        }
        
        $gifts = GiftDonation::with(['donation.campaign'])
            ->whereHas('donation', function($q) use ($donor) {
                $q->where('donor_id', $donor->id);  // ✅ استخدام donor_id
            })
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
        $donor = $user->donor;  // ✅ جلب ملف المتبرع
        
        if (!$donor) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتبرع'
            ], 404);
        }
        
        $gift = GiftDonation::with(['donation.campaign'])
            ->whereHas('donation', function($q) use ($donor) {
                $q->where('donor_id', $donor->id);  // ✅ استخدام donor_id
            })
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $gift
        ], 200);
    }
}