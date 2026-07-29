<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\DonorProfileRequest;
use App\Models\Profile;
use App\Models\DonorProfile;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        if ($user->donor) {
            return response()->json([
                'success' => false,
                'message' => 'لديك بالفعل ملف متبرع',
                'code' => 'PROFILE_ALREADY_EXISTS'
            ], 400);
        }

        try {
            $validated = $request->validated();
            $idPath = $request->file('photo_id')->store('photo_id', 'public');

            $personalPhotoPath = null;
            if ($request->hasFile('Personal_photo')) {
                $personalPhotoPath = $request->file('Personal_photo')->store('profiles', 'public');
            }

            $result = DB::transaction(function () use ($user, $validated, $idPath, $personalPhotoPath) {
                $profile = Profile::create([
                    'user_id' => $user->id,
                    'city_id' => $validated['city_id'],
                    'photo_id' => $idPath,
                    'phone' => $validated['phone'],
                    'birth_date' => $validated['birth_date'],
                    'gender' => $validated['gender'],
                    'Personal_photo' => $personalPhotoPath,
                ]);

                $donor = DonorProfile::create([
                    'user_id' => $user->id,
                    'bio' => $validated['bio'] ?? null,
                ]);

                $user->update([
                    'is_active' => true,
                    'profile_completed' => true
                ]);

                return ['profile' => $profile, 'donor' => $donor];
            });

            return response()->json([
                'code' => 201,
                'success' => true,
                'message' => 'تم إكمال ملف المتبرع بنجاح',
                'data' => [
                    'user' => $user->only(['id', 'name', 'email', 'role']),
                    'general_profile' => [
                        ...$result['profile']->toArray(),
                        'city_name' => $result['profile']->city->name,
                    ],
                    'donor_details' => $result['donor'],
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الملف الشخصي',
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
                'message' => 'الملف الشخصي غير موجود'
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
                    'loyalty_tier' => $donorProfile->loyalty_tier,
                    'bio' => $donorProfile->bio
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

            if ($user->profile) {
                $profileUpdates = [];

                if (isset($validated['phone'])) {
                    $profileUpdates['phone'] = $validated['phone'];
                }

                if (isset($validated['city_id'])) {
                    $profileUpdates['city_id'] = $validated['city_id'];
                }

                if (isset($validated['gender'])) {
                    $profileUpdates['gender'] = $validated['gender'];
                }

                if ($request->hasFile('personal_photo')) {
                    if ($user->profile->personal_photo) {
                        $oldPhotoPath = storage_path('app/public/' . $user->profile->personal_photo);
                        if (file_exists($oldPhotoPath)) {
                            unlink($oldPhotoPath);
                        }
                    }
                    $profileUpdates['personal_photo'] = $request->file('personal_photo')->store('profiles', 'public');
                }

                if (array_key_exists('bio', $validated)) {
                    $profileUpdates['bio'] = $validated['bio'];
                }

                if (!empty($profileUpdates)) {
                    $user->profile->update($profileUpdates);
                }
            }

            if ($user->donor) {
                $donorUpdates = [];

                if (isset($validated['donor_type'])) {
                    $donorUpdates['donor_type'] = $validated['donor_type'];
                }

                if (isset($validated['is_anonymous'])) {
                    $donorUpdates['is_anonymous'] = $validated['is_anonymous'];
                }

                if (array_key_exists('bio', $validated)) {
                    $donorUpdates['bio'] = $validated['bio'];
                }

                if (!empty($donorUpdates)) {
                    $user->donor->update($donorUpdates);
                }
            }

            DB::commit();

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
