<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Donor\CartRequest;
use App\Models\DonationCart;
use App\Models\Campaign;
use App\Models\Donation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DonationCartController extends Controller
{
    /**
     * Get user's cart items
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $cartItems = DonationCart::with(['campaign'])
            ->where('user_id', $user->id)
            ->get();

        $total = $cartItems->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $cartItems,
                'total' => $total,
                'items_count' => $cartItems->count()
            ]
        ], 200);
    }

    /**
     * Add item to cart
     */
    public function add(Request $request)
    {
        $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'amount' => 'required|numeric|min:1|max:100000'
        ]);

        $user = $request->user();
        $campaign = Campaign::findOrFail($request->campaign_id);

        // Check if campaign is active
        if ($campaign->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'هذه الحملة غير نشطة حالياً'
            ], 400);
        }

        // Check if item already in cart
        $existing = DonationCart::where('user_id', $user->id)
            ->where('campaign_id', $request->campaign_id)
            ->first();

        if ($existing) {
            $existing->update(['amount' => $request->amount]);
            $message = 'تم تحديث المبلغ في السلة';
        } else {
            DonationCart::create([
                'user_id' => $user->id,
                'campaign_id' => $request->campaign_id,
                'amount' => $request->amount
            ]);
            $message = 'تم إضافة الحملة إلى السلة';
        }

        return response()->json([
            'success' => true,
            'message' => $message
        ], 200);
    }

    /**
     * Update cart item amount
     */
    public function update($id, Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:100000'
        ]);

        $user = $request->user();
        
        $cartItem = DonationCart::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $cartItem->update(['amount' => $request->amount]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المبلغ بنجاح'
        ], 200);
    }

    /**
     * Remove item from cart
     */
    public function remove($id, Request $request)
    {
        $user = $request->user();
        
        $cartItem = DonationCart::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $cartItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم إزالة الحملة من السلة'
        ], 200);
    }

    /**
     * Clear entire cart
     */
    public function clear(Request $request)
    {
        $user = $request->user();
        
        DonationCart::where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تفريغ السلة بنجاح'
        ], 200);
    }

    /**
     * Checkout - process all items in cart
     */
    public function checkout(Request $request)
    {
        $user = $request->user();
        
        $cartItems = DonationCart::with(['campaign'])
            ->where('user_id', $user->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'السلة فارغة'
            ], 400);
        }

        $total = $cartItems->sum('amount');

        // Create donations for each cart item
        $donations = [];
        
        foreach ($cartItems as $item) {
            $donation = Donation::create([
                'donor_id' => $user->id,
               // 'user_id' => $user->id,
                'campaign_id' => $item->campaign_id,
                'amount' => $item->amount,
                'currency' => 'USD',
                'payment_method' => $request->payment_method ?? 'stripe',
                'status' => 'pending',
                'donated_at' => now()
            ]);
            
            $donations[] = $donation;
        }

        // Process payment for total amount
        // In real implementation, this would integrate with payment gateway
        
        // Clear cart after successful checkout
        DonationCart::where('user_id', $user->id)->delete();

        // Mark all donations as completed
        foreach ($donations as $donation) {
            $donation->markAsCompleted();
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إتمام التبرع بنجاح',
            'data' => [
                'donations' => $donations,
                'total_amount' => $total,
                'receipt_url' => null
            ]
        ], 200);
    }
}