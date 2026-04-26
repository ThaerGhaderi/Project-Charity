<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\DonorProfile;
use App\Models\VolunterProfile;
use App\Models\BeneficiaryProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompleteProfileController extends Controller
{
    public function completeProfile(Request $request)
    {
        $user = $request->user();
        
        
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'code' => 403,
                'status' => 'error',
                'message' => 'Please verify your email first using OTP.'
            ], 403);
        }
        
       
        if ($this->hasProfile($user)) {
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'You already have a completed profile'
            ], 400);
        }
        
        try {
            switch ($user->role) {
                case 'Donor':
                  
                    $validated = $request->validate([
                        'skills' => 'required|string|max:500',
                        'availability' => 'required|string|max:255',
                        'region' => 'required|string|max:255',
                        'status' => 'required|in:pending,active,inactive',
                        'total_hours' => 'required|integer|min:0',
                        'total_donated' => 'required|numeric|min:0',
                    ]);
                    
                   
                    $profile = DonorProfile::create([
                        'user_id' => $user->id,
                        'skills' => $validated['skills'],
                        'availability' => $validated['availability'],
                        'region' => $validated['region'],
                        'status' => $validated['status'],
                        'total_hours' => $validated['total_hours'],
                        'total_donated' => $validated['total_donated'],
                    ]);
                    break;
                    
                case 'volunteer':
                   
                    $validated = $request->validate([
                        'skills' => 'required|string|max:500',
                        'availability' => 'required|string|max:255',
                        'region' => 'required|string|max:255',
                        'status' => 'required|in:pending,active,inactive',
                        'total_hours' => 'required|integer|min:0',
                    ]);
                    
                   
                    $profile = VolunterProfile::create([
                        'user_id' => $user->id,
                        'skills' => $validated['skills'],
                        'availability' => $validated['availability'],
                        'region' => $validated['region'],
                        'status' => $validated['status'],
                        'total_hours' => $validated['total_hours'],
                    ]);
                    break;
                    
                case 'Beneficiary':
                  
                    $validated = $request->validate([
                        'address' => 'required|string|max:500',
                        'region' => 'required|string|max:255',
                        'category' => 'required|in:orphan,refugee,disabled,poor',
                        'birth_date' => 'required|date|before:today',
                        'gender' => 'required|in:male,female',
                        'marital_status' => 'required|in:single,married,divorced,widowed',
                        'priority_score' => 'required|integer|min:0|max:100',
                        'is_anonymized' => 'required|boolean',
                    ]);
                    
                   
                    $profile = BeneficiaryProfile::create([
                        'user_id' => $user->id,
                        'address' => $validated['address'],
                        'region' => $validated['region'],
                        'category' => $validated['category'],
                        'birth_date' => $validated['birth_date'],
                        'gender' => $validated['gender'],
                        'marital_status' => $validated['marital_status'],
                        'priority_score' => $validated['priority_score'],
                        'is_anonymized' => $validated['is_anonymized'],
                    ]);
                    break;
                    
                default:
                    return response()->json([
                        'code' => 400,
                        'status' => 'error',
                        'message' => 'Invalid role'
                    ], 400);
            }
            
     
            $user->profile_completed = true;
            $user->save();
            
       
            $user->load($this->getRelationName($user->role));
            
            return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => 'Profile completed successfully. You can now use the app.',
                'data' => [
                    'user' => $user,
                    'profile' => $profile
                ]
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'code' => 422,
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => 'error',
                'message' => 'An error occurred while creating the profile',
                'error' => $e->getMessage()
            ], 500);
        }
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