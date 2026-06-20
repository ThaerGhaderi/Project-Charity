<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\DonorProfile;
use App\Models\VolunterProfile;
use App\Models\BeneficiaryProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfileCompletionController extends Controller
{
    /**
     * Complete profile - Requires the token from registration
     */
    public function completeProfile(Request $request)
    {
        // المستخدم يتم جلبه من التوكن الذي حصل عليه عند التسجيل
        $user = $request->user();
        
        // التحقق من وجود ملف شخصي مسبق
        if ($this->hasProfile($user)) {
            return response()->json([
                'code' => 400,
                'message' => 'You already have a completed profile'
            ], 400);
        }
        
        // حسب الدور، نطلب بيانات مختلفة
        switch ($user->role) {
            case 'Donor':
                $validated = $request->validate([
                    'preferred_cause' => 'nullable|string|max:255',
                    'total_donated' => 'nullable|numeric|min:0',
                ]);
                
                DonorProfile::create([
                    'user_id' => $user->id,
                    'preferred_cause' => $validated['preferred_cause'] ?? null,
                    'total_donated' => $validated['total_donated'] ?? 0,
                ]);
                break;
                
            case 'volunteer':
                $validated = $request->validate([
                    'skills' => 'nullable|string|max:500',
                    'availability' => 'nullable|string|max:255',
                ]);
                
                VolunterProfile::create([
                    'user_id' => $user->id,
                    'skills' => $validated['skills'] ?? null,
                    'availability' => $validated['availability'] ?? null,
                    'total_hours' => 0,
                    'status' => 'pending',
                ]);
                break;
                
            case 'Beneficiary':
                $validated = $request->validate([
                    'address' => 'nullable|string|max:500',
                    'region' => 'nullable|string|max:255',
                    'category' => 'nullable|in:orphan,refugee,disabled,poor',
                    'priority_score' => 'nullable|integer|min:0|max:100',
                ]);
                
                BeneficiaryProfile::create([
                    'user_id' => $user->id,
                    'address' => $validated['address'] ?? null,
                    'region' => $validated['region'] ?? null,
                    'category' => $validated['category'] ?? null,
                    'priority_score' => $validated['priority_score'] ?? 0,
                ]);
                break;
                
            default:
                return response()->json([
                    'code' => 400,
                    'message' => 'Invalid role'
                ], 400);
        }
        
        return response()->json([
            'code' => 200,
            'message' => 'Profile completed successfully. You can now login manually.',
            'user' => $user->load($this->getRelationName($user->role)),
        ], 200);
    }
    
    private function hasProfile($user)
    {
        switch ($user->role) {
            case 'Donor':
                return $user->donor !== null;
            case 'volunteer':
                return $user->volunteer !== null;
            case 'Beneficiary':
                return $user->beneficiary !== null;
            default:
                return false;
        }
    }
    
    private function getRelationName($role)
    {
        return match ($role) {
            'Donor' => 'donor',
            'volunteer' => 'volunteer',
            'Beneficiary' => 'beneficiary',
            default => '',
        };
    }
}