<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BeneficiaryProfile;
use App\Http\Requests\BeneficiaryProfileRequest;
use Illuminate\Http\Request;

class BeneficiaryProfileController extends Controller
{
    /**
     * Complete Beneficiary Profile
     * 
     * @param BeneficiaryProfileRequest $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @api {post} /api/beneficiary/complete-profile Complete Beneficiary Profile
     * @apiHeader Authorization Bearer {token}
     */
    public function completeProfile(BeneficiaryProfileRequest $request)
    {
        $user = $request->user();
        
        // التحقق من توثيق البريد الإلكتروني
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'Please verify your email first using OTP.',
            ], 403);
        }
        
        // التحقق من أن الدور هو Beneficiary
        if ($user->role !== 'Beneficiary') {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'Your role is not Beneficiary. You cannot complete beneficiary profile.',
            ], 403);
        }
        
        // التحقق من أن البروفايل لم يتم إنشاؤه مسبقاً
        if ($user->beneficiary) {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'You already have a beneficiary profile.',
            ], 400);
        }
        
        try {
            $validated = $request->validated();
            
            $profile = BeneficiaryProfile::create([
                'user_id' => $user->id,
                'address' => $validated['address'],
                'region' => $validated['region'],
                'category' => $validated['category'],
                'birth_date' => $validated['birth_date'],
                'gender' => $validated['gender'],
                'marital_status' => $validated['marital_status'],
                'priority_score' => $validated['priority_score'],
                'is_anonymized' => $validated['is_anonymized'] ?? false,
            ]);
            
            // تحديث حالة إكمال البروفايل
            $user->profile_completed = true;
            $user->save();
            
            return response()->json([
                'code' => '201',
                'success' => true,
                'message' => 'Beneficiary profile completed successfully.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'profile_completed' => $user->profile_completed
                    ],
                    'profile' => $profile
                ]
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'An error occurred while creating beneficiary profile.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get Beneficiary Profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        
        if (!$user->beneficiary) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'Beneficiary profile not found.'
            ], 404);
        }
        $profileData = $user->beneficiary->toArray();
        if ($user->beneficiary->is_anonymized) {
            unset($profileData['address']);
            unset($profileData['birth_date']);
            $profileData['name_anonymized'] = 'Confidential';
        }
        
        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->is_anonymized ? 'Anonymous' : $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ],
                'profile' => $profileData
            ]
        ]);
    }
}