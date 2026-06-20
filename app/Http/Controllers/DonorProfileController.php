<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Donor\DonorProfileRequest;
use App\Http\Requests\DonorProfileRequest as RequestsDonorProfileRequest;
use App\Models\Profile;
use App\Models\DonorProfile;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class DonorProfileController extends Controller
{
   
    public function completeProfile(RequestsDonorProfileRequest $request)
    {
        $user = $request->user();

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'يرجى التحقق من بريدك الإلكتروني أولاً',
                'code' => 'EMAIL_NOT_VERIFIED'
            ], 403);
        }

        if ($user->role !== 'Donor') {
            return response()->json([
                'success' => false,
                'message' => 'دور المستخدم ليس متبرعاً',
                'code' => 'INVALID_ROLE'
            ], 403);
        }

        if ($user->profile_completed) {
            return response()->json([
                'success' => false,
                'message' => 'لقد قمت بإكمال ملفك الشخصي بالفعل',
                'code' => 'PROFILE_ALREADY_COMPLETED'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Upload ID photo
            $idPath = $request->file('photo_id')->store('identifications', 'public');
            
            // Upload personal photo if provided
            $personalPhotoPath = null;
            if ($request->hasFile('personal_photo')) {
                $personalPhotoPath = $request->file('personal_photo')->store('profiles', 'public');
            }

            // Create profile
            $profile = Profile::create([
                'user_id' => $user->id,
                'city_id' => $request->city_id,
                'photo_id' => $idPath,
                'phone' => $request->phone,
                'birth_date' => $request->birth_date,
                'gender' => $request->gender,
                'personal_photo' => $personalPhotoPath,
                'bio' => $request->bio
            ]);

            // Create donor profile
            $donorProfile = DonorProfile::create([
                'user_id' => $user->id,
                'donor_type' => $request->donor_type ?? 'فردي',
                'is_anonymous' => $request->is_anonymous ?? false,
                'total_donated' => 0,
                'loyalty_points' => 0,
                'bio' => $request->bio
            ]);

            // Mark profile as completed
            $user->update([
                'is_active' => true,
                'profile_completed' => true
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إكمال ملف المتبرع بنجاح',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'profile_completed' => true
                    ],
                    'profile' => [
                        'phone' => $profile->phone,
                        'city_id' => $profile->city_id,
                        'city_name' => $profile->city?->name,
                        'birth_date' => $profile->birth_date,
                        'gender' => $profile->gender,
                        'bio' => $profile->bio,
                        'photo_url' => $personalPhotoPath ? asset('storage/' . $personalPhotoPath) : null
                    ],
                    'donor_profile' => [
                        'donor_type' => $donorProfile->donor_type,
                        'is_anonymous' => $donorProfile->is_anonymous,
                        'total_donated' => 0,
                        'loyalty_points' => 0,
                        'loyalty_tier' => null
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الملف الشخصي',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get donor profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();

        if (!$user->profile_completed) {
            return response()->json([
                'success' => false,
                'message' => 'الملف الشخصي غير مكتمل'
            ], 404);
        }

        $profile = $user->profile;
        $donorProfile = $user->donor;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'created_at' => $user->created_at
                ],
                'profile' => $profile ? [
                    'phone' => $profile->phone,
                    'city_id' => $profile->city_id,
                    'city_name' => $profile->city?->name,
                    'birth_date' => $profile->birth_date,
                    'gender' => $profile->gender,
                    'bio' => $profile->bio,
                    'photo_url' => $profile->personal_photo ? asset('storage/' . $profile->personal_photo) : null
                ] : null,
                'donor_profile' => $donorProfile ? [
                    'donor_type' => $donorProfile->donor_type,
                    'is_anonymous' => $donorProfile->is_anonymous,
                    'total_donated' => $donorProfile->total_donated,
                    'loyalty_points' => $donorProfile->loyalty_points,
                    'loyalty_tier' => $donorProfile->loyalty_tier
                ] : null
            ]
        ], 200);
    }

    /**
     * Update donor profile
     */
   public function updateProfile(Request $request)
{
    $user = $request->user();

    // ✅ تحميل العلاقات قبل التحديث
    $user->load(['profile', 'donor']);

    $validated = $request->validate([
        'donor_type' => 'sometimes|in:فردي,منظمة',
    'is_anonymous' => 'sometimes|boolean',
    'bio' => 'nullable|string|max:255',
    'phone' => 'sometimes|string|max:20',
    'city_id' => 'sometimes|exists:cities,id',
    'gender' => 'sometimes|in:ذكر,انثى',
    'birth_date' => 'sometimes|date|before:today', 
    'personal_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:5000'
    ]);

    try {
        DB::beginTransaction();

        // ✅ تحديث الملف الشخصي العام
        if ($user->profile) {
            $profileUpdates = [];
            
            if (isset($validated['phone'])) {
                $profileUpdates['phone'] = $validated['phone'];
            }
            
            if (isset($validated['city_id'])) {
                $profileUpdates['city_id'] = $validated['city_id'];
            }
            
            // ✅ أضف هذا الجزء لتحديث gender
            if (isset($validated['gender'])) {
                $profileUpdates['gender'] = $validated['gender'];
            }
            
            if ($request->hasFile('personal_photo')) {
                // حذف الصورة القديمة إذا وجدت
                if ($user->profile->personal_photo) {
                    $oldPhotoPath = storage_path('app/public/' . $user->profile->personal_photo);
                    if (file_exists($oldPhotoPath)) {
                        unlink($oldPhotoPath);
                    }
                }
                $profileUpdates['personal_photo'] = $request->file('personal_photo')->store('profiles', 'public');
            }

            // ✅ تحديث البايو في كلا الجدولين إذا تغير
            if (isset($validated['bio'])) {
                $profileUpdates['bio'] = $validated['bio'];
            }

            if (!empty($profileUpdates)) {
                $user->profile->update($profileUpdates);
            }
        }

        // ✅ تحديث ملف المتبرع
        if ($user->donor) {
            $donorUpdates = [];
            
            if (isset($validated['donor_type'])) {
                $donorUpdates['donor_type'] = $validated['donor_type'];
            }
            
            if (isset($validated['is_anonymous'])) {
                $donorUpdates['is_anonymous'] = $validated['is_anonymous'];
            }
            
            if (isset($validated['bio'])) {
                $donorUpdates['bio'] = $validated['bio'];
            }

            if (!empty($donorUpdates)) {
                $user->donor->update($donorUpdates);
            }
        }

        DB::commit();

        // ✅ إعادة تحميل العلاقات بعد التحديث
        $user->refresh();
        $user->load(['profile', 'donor']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الملف الشخصي بنجاح',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'profile_completed' => $user->profile_completed
                ],
                'profile' => $user->profile ? [
                    'phone' => $user->profile->phone,
                    'city_id' => $user->profile->city_id,
                    'city_name' => $user->profile->city?->name,
                    'birth_date' => $user->profile->birth_date,
                    'gender' => $user->profile->gender, 
                    'bio' => $user->profile->bio,
                    'photo_url' => $user->profile->personal_photo ? asset('storage/' . $user->profile->personal_photo) : null
                ] : null,
                'donor_profile' => $user->donor ? [
                    'donor_type' => $user->donor->donor_type,
                    'is_anonymous' => $user->donor->is_anonymous,
                    'total_donated' => $user->donor->total_donated,
                    'loyalty_points' => $user->donor->loyalty_points,
                    'loyalty_tier' => $user->donor->loyalty_tier,
                    'bio' => $user->donor->bio
                ] : null
            ]
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء تحديث الملف الشخصي',
            'error' => $e->getMessage()
        ], 500);
    }
}
}
