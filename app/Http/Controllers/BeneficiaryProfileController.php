<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BeneficiaryProfile;
use App\Http\Requests\BeneficiaryProfileRequest;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'Please verify your email first using OTP.',
            ], 403);
        }
        if ($user->role !== 'Beneficiary') {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'Your role is not Beneficiary. You cannot complete beneficiary profile.',
            ], 403);
        }
        if ($user->beneficiary) {
            return response()->json([
                'code' => '400',
                'success' => false,
                'message' => 'You already have a beneficiary profile.',
            ], 400);
        }
         try {
        $validated = $request->validated();
        $personalPhotoPath = $request->file('Personal_photo')->store('profiles/personal', 'public');

        $photoIdPath         = $request->hasFile('photo_id')
            ? $request->file('photo_id')->store('profiles/id', 'public')
            : null;

        $photoFamilyPath     = $request->hasFile('photo_Family_notebook')
            ? $request->file('photo_Family_notebook')->store('profiles/family', 'public')
            : null;

        $photoSupportingPath = $request->hasFile('photo_Supporting')
            ? $request->file('photo_Supporting')->store('profiles/supporting', 'public')
            : null;

        $result = DB::transaction(function () use ($user, $validated, $personalPhotoPath, $photoIdPath, $photoFamilyPath, $photoSupportingPath) {

            $profile = Profile::create([
                'user_id'               => $user->id,
                'city_id'               => $validated['city_id'],
                'phone'                 => $validated['phone'],
                'birth_date'            => $validated['birth_date'],
                'gender'                => $validated['gender'],
                'Personal_photo'        => $personalPhotoPath,
                'photo_id'              => $photoIdPath,
            ]);
            $beneficiaryProfile = BeneficiaryProfile::create([
                'user_id'               => $user->id,
                'marital_status'        => $validated['marital_status'],
                'Breadwinner'           => $validated['Breadwinner'],
                'has_income'            => $validated['has_income'],
                'family_members_count'  => $validated['family_members_count'],
                'income_range'          => $validated['has_income'] ? ($validated['income_range'] ?? null) : null,
                'photo_Family_notebook' => $photoFamilyPath,
                'is_Anonymous'          => $validated['is_Anonymous'],
                'photo_Supporting'      => $photoSupportingPath,
            ]);
            $beneficiaryProfile->types()->sync($validated['types']);
            return ['profile' => $profile, 'beneficiary' => $beneficiaryProfile];
        });
        return response()->json([
            'code'    => '201',
            'success' => true,
            'message' => 'Beneficiary profile completed successfully.',
            'data'    => [
                'user' => $user->only(['id', 'name', 'email', 'role']),
                'general_profile' => [
                    ...$result['profile']->toArray(),
                    'city_name' => $result['profile']->city->name,
                ],
                'beneficiary_details' => $result['beneficiary']->load('types'),
            ],
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
