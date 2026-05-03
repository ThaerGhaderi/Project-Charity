<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DonorProfile;
use App\Http\Requests\DonorProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DonorProfileController extends Controller
{
    /**
     * Complete Donor Profile
     * 
     * @param DonorProfileRequest $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @api {post} /api/donor/complete-profile Complete Donor Profile
     * @apiHeader Authorization Bearer {token}
     */
    public function completeProfile(DonorProfileRequest $request)
    {
        $user = $request->user();
        
        // التحقق من توثيق البريد الإلكتروني
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email first using OTP.',
                'code' => 'EMAIL_NOT_VERIFIED'
            ], 403);
        }
        
        // التحقق من أن الدور هو Donor
        if ($user->role !== 'Donor') {
            return response()->json([
                'success' => false,
                'message' => 'Your role is not Donor. You cannot complete donor profile.',
                'code' => 'INVALID_ROLE'
            ], 403);
        }
        
        // التحقق من أن البروفايل لم يتم إنشاؤه مسبقاً
        if ($user->donor) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a donor profile.',
                'code' => 'PROFILE_ALREADY_EXISTS'
            ], 400);
        }
        
        try {
            $validated = $request->validated();
            
            $profile = DonorProfile::create([
                'user_id' => $user->id,
                'skills' => $validated['skills'],
                'availability' => $validated['availability'],
                'region' => $validated['region'],
                'status' => 'pending',
                'total_hours' => 0,
                'total_donated' => $validated['total_donated'] ?? 0,
            ]);
            
            // تحديث حالة إكمال البروفايل
            $user->profile_completed = true;
            $user->save();
            
            return response()->json([
                'code' => '201',
                'success' => true,
                'message' => 'Donor profile completed successfully.',
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
                'message' => 'An error occurred while creating donor profile.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get Donor Profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        
        if (!$user->donor) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'Donor profile not found.'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ],
                'profile' => $user->donor
            ]
        ]);
    }
    
}