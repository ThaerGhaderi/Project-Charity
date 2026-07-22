<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\VolunteerProfileRequest;
use App\Models\VolunteerCertificate;
use App\Models\Profile;
use App\Models\VolunterProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VolunterProfileController extends Controller
{
    public function completeProfile(VolunteerProfileRequest $request)
    {
        $user = $request->user();
        
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'code' => 'EMAIL_NOT_VERIFIED',
                'success' => false,
                'message' => 'Please verify your email first using OTP.',
            ], 403);
        }
        
        if ($user->role !== 'volunteer') {
            return response()->json([
                'code' => 'INVALID_ROLE',
                'success' => false,
                'message' => 'Your role is not Volunteer.',
            ], 403);
        }
        
        if ($user->volunteer) {
            return response()->json([
                'code' => 'PROFILE_ALREADY_EXISTS',
                'success' => false,
                'message' => 'You already have a volunteer profile.',
            ], 400);
        }

        try {
            $validated = $request->validated();
            $personalPhotoPath = null;
            $certificatePaths = [];

            if ($request->hasFile('certificates')) {
                foreach ($request->file('certificates') as $index => $file) {
                    $certificatePaths[] = [
                        'path' => $file->store('certificates', 'public'),
                        'name' => $validated['certificates_names'][$index] ?? null,
                    ];
                }
            }
            
            if ($request->hasFile('Personal_photo')) {
                $personalPhotoPath = $request->file('Personal_photo')->store('profiles', 'public');
            }

            $photoIdPath = null;
            if ($request->hasFile('photo_id')) {
                $photoIdPath = $request->file('photo_id')->store('photo_ids', 'public');
            }
            
            $result = DB::transaction(function () use ($user, $validated, $personalPhotoPath, $photoIdPath, $certificatePaths) {

                $profile = Profile::create([
                    'user_id'        => $user->id,
                    'city_id'        => $validated['city_id'],
                    'phone'          => $validated['phone'],
                    'photo_id'       => $photoIdPath,
                    'birth_date'     => $validated['birth_date'],
                    'gender'         => $validated['gender'],
                    'Personal_photo' => $personalPhotoPath ?? null,
                ]);
                
                $volunteer = VolunterProfile::create([
                    'user_id'              => $user->id,
                    'Favorite_period'      => $validated['Favorite_period'],
                    'Commitment_type'      => $validated['Commitment_type'],
                    'Educational_level'    => $validated['Educational_level'],
                    'experience_years'     => $validated['experience_years'],
                    'car'                  => $validated['car'],
                    'previous_voluntering' => $validated['previous_voluntering'],
                    'previous_work_place'  => $validated['previous_voluntering'] ? ($validated['previous_work_place'] ?? null) : null,
                    'bio'                  => $validated['bio'] ?? null,
                    'facebook'             => $validated['facebook'] ?? null,
                    'linkedin'             => $validated['linkedin'] ?? null,
                    'total_hours'          => 0,
                    'status'               => 'متاح',
                ]);
                
                foreach ($certificatePaths as $cert) {
                    VolunteerCertificate::create([
                        'volunteer_id' => $volunteer->id,
                        'title' => $cert['name'] ?? 'شهادة تطوع',
                        'file_path' => $cert['path'],
                        'hours_required' => 0,
                        'hours_completed' => 0,
                        'is_active' => true,
                    ]);
                }
                
                $volunteer->domains()->sync($validated['domain_ids']);
                $volunteer->days()->sync($validated['day_ids']);
                $volunteer->categories()->sync($validated['category_ids']);
                $volunteer->languages()->sync($validated['language_ids']);
                $volunteer->skills()->sync($validated['skill_ids']);

                // ✅ ✅ ✅ تحديث user مع profile_completed = true
                 $user->update([
        'is_active' => true,
        'profile_completed' => true,  // ✅ هذا هو الحل
    ]);
                return ['profile' => $profile, 'volunteer' => $volunteer];
            });
            
            return response()->json([
                'code'    => '201',
                'success' => true,
                'message' => 'Volunteer profile completed successfully.',
                'data'    => [
                    'user' => $user->fresh()->only(['id', 'name', 'email', 'role', 'profile_completed']), // ✅ أضف profile_completed
                    'general_profile' => [
                        ...$result['profile']->toArray(),
                        'city_name' => $result['profile']->city->name,
                    ],
                    'volunteer_details' => $result['volunteer']->load(['domains', 'days', 'categories', 'languages', 'skills', 'certificates']),
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'code'    => '500',
                'success' => false,
                'message' => 'An error occurred while saving profile data.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}