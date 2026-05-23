<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DonorProfile;
use App\Http\Requests\DonorProfileRequest;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    if (!$user->hasVerifiedEmail()) {
        return response()->json([
            'success' => false,
            'message' => 'Please verify your email first using OTP.',
            'code'    => 'EMAIL_NOT_VERIFIED'
        ], 403);
    }

    if ($user->role !== 'Donor') {
        return response()->json([
            'success' => false,
            'message' => 'Your role is not Donor.',
            'code'    => 'INVALID_ROLE'
        ], 403);
    }

    if ($user->donor) {
        return response()->json([
            'success' => false,
            'message' => 'You already have a donor profile.',
            'code'    => 'PROFILE_ALREADY_EXISTS'
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
                'user_id'        => $user->id,
                'city_id'        => $validated['city_id'],
                'photo_id'       => $idPath,
                'phone'          => $validated['phone'],
                'birth_date'     => $validated['birth_date'],
                'gender'         => $validated['gender'],
                'Personal_photo' => $personalPhotoPath,
            ]);
            $donor = DonorProfile::create([
                'user_id' => $user->id,
                'bio'     => $validated['bio'] ?? null,
            ]);
            $user->update(['is_active' => true]);
            return ['profile' => $profile, 'donor' => $donor];
        });
        return response()->json([
            'code'    => 201,
            'success' => true,
            'message' => 'Donor profile completed successfully.',
            'data'    => [
                'user'            => $user->only(['id', 'name', 'email', 'role']),
                'general_profile' => [...$result['profile']->toArray(),
                    'city_name' => $result['profile']->city->name,
                ],
                'donor_details'   => $result['donor'],
            ]
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'code'    => 500,
            'success' => false,
            'message' => 'An error occurred while creating donor profile.',
            'error'   => $e->getMessage()
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
