<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\VolunteerProfileRequest;
use App\Models\VolunterProfile;
use Illuminate\Http\Request;

class VolunterProfileController extends Controller
{
    /**
     * Complete Volunteer Profile
     * 
     * @param VolunteerProfileRequest $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @api {post} /api/volunteer/complete-profile Complete Volunteer Profile
     * @apiHeader Authorization Bearer {token}
     */
    public function completeProfile(VolunteerProfileRequest $request)
    {
        $user = $request->user();
        
        // التحقق من توثيق البريد الإلكتروني
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'code' => 'EMAIL_NOT_VERIFIED',
                'success' => false,
                'message' => 'Please verify your email first using OTP.',
                
            ], 403);
        }
        
        // التحقق من أن الدور هو Volunteer
        if ($user->role !== 'volunteer') {
            return response()->json([
                   'code' => 'INVALID_ROLE',
                'success' => false,
                'message' => 'Your role is not Volunteer. You cannot complete volunteer profile.',
             
            ], 403);
        }
        
        if ($user->volunteer) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a volunteer profile.',
                'code' => 'PROFILE_ALREADY_EXISTS'
            ], 400);
        }
        
        try {
            $validated = $request->validated();
            
            $profile = VolunterProfile::create([
                'user_id' => $user->id,
                'skills' => $validated['skills'],
                'availability' => $validated['availability'],
                'region' => $validated['region'],
                'status' => 'pending',
                'total_hours' => 0,
            ]);
            
            $user->profile_completed = true;
            $user->save();
            
            return response()->json([
                'code'=>'201',
                'success' => true,
                'message' => 'Volunteer profile completed successfully.',
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
                  'code'=>'500',
                'success' => false,
                'message' => 'An error occurred while creating volunteer profile.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function getProfile(Request $request)
    {
        $user = $request->user();
        
        if (!$user->volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'Volunteer profile not found.'
            ], 404);
        }
        
        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ],
                'profile' => $user->volunteer
            ]
        ]);
    }
    
}