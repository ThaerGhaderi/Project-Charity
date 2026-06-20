<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyPoints;
use App\Models\User;
use Illuminate\Http\Request;

class LoyaltyPointsController extends Controller
{
    /**
     * Get donor's loyalty points and badge
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $loyalty = $user->donor;
        
        if (!$loyalty) {
            return response()->json([
                'success' => true,
                'data' => [
                    'points' => 0,
                    'badge' => null,
                    'next_badge' => 'برونزية',
                    'points_to_next' => 300,
                    'total_donated' => 0
                ]
            ], 200);
        }

        $nextBadge = null;
        $pointsToNext = 0;
        
        if ($loyalty->loyalty_points < 300) {
            $nextBadge = 'برونزية';
            $pointsToNext = 300 - $loyalty->loyalty_points;
        } elseif ($loyalty->loyalty_points < 1000) {
            $nextBadge = 'فضية';
            $pointsToNext = 1000 - $loyalty->loyalty_points;
        } elseif ($loyalty->loyalty_points < 3000) {
            $nextBadge = 'ذهبية';
            $pointsToNext = 3000 - $loyalty->loyalty_points;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'points' => $loyalty->loyalty_points,
                'badge' => $loyalty->loyalty_tier,
                'next_badge' => $nextBadge,
                'points_to_next' => $pointsToNext,
                'total_donated' => $loyalty->total_donated
            ]
        ], 200);
    }

    /**
     * Get leaderboard of top donors
     */
    public function leaderboard(Request $request)
    {
        $limit = $request->get('limit', 10);
        
        $topDonors = User::whereHas('donor', function($q) {
                $q->where('total_donated', '>', 0);
            })
            ->with('donor')
            ->get()
            ->sortByDesc(function($user) {
                return $user->donor->total_donated;
            })
            ->take($limit)
            ->map(function($user, $index) {
                return [
                    'rank' => $index + 1,
                    'name' => $user->donor->is_anonymous ? 'متبرع مجهول' : $user->name,
                    'total_donated' => $user->donor->total_donated,
                    'badge' => $user->donor->loyalty_tier
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $topDonors
        ], 200);
    }

    /**
     * Get donor's rank
     */
    public function rank(Request $request)
    {
        $user = $request->user();
        
        $donors = User::whereHas('donor', function($q) {
                $q->where('total_donated', '>', 0);
            })
            ->with('donor')
            ->get()
            ->sortByDesc(function($user) {
                return $user->donor->total_donated;
            });

        $rank = 1;
        foreach ($donors as $index => $donor) {
            if ($donor->id === $user->id) {
                $rank = $index + 1;
                break;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'rank' => $rank,
                'total_donors' => $donors->count()
            ]
        ], 200);
    }
}