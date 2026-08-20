<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\VolunteerProfileRequest;
use App\Models\VolunteerCertificate;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use App\Models\VolunterProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


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
public function getVolunteers(Request $request)
{
    $volunteers = User::with([
        'profile.city',
        'volunterProfile.skills'
    ])
        ->where('role', 'volunteer')
        ->latest()
        ->get();

    return response()->json([
        'success' => true,
        'data' => $volunteers->map(function ($user) {
            return [
                'id'          => $user->id,
                'name'        => $user->name,
                'city'        => $user->profile?->city?->name,
                'city_id'     => $user->profile?->city_id,
                'phone'       => $user->profile?->phone,
                'skills'      => $user->volunterProfile?->skills?->pluck('name')->values(),
                'total_hours' => $user->volunterProfile?->total_hours ?? 0,
                'status'      => $user->volunterProfile?->status,
                'photo'       => $user->profile?->Personal_photo
                    ? asset('storage/' . $user->profile->Personal_photo)
                    : null,
            ];
        })
    ]);
}
public function store(Request $request)
{
    $validated = $request->validate([
        'name'     => 'required|string|max:255',
        'phone'    => 'required|string|unique:profiles,phone',
        'city'     => 'required|exists:cities,id',
        'status'   => 'required|in:منشغل,متاح,غير متاح',
        'skills'   => 'nullable|array',
        'skills.*' => 'exists:skills,id',
        'total_hours' => 'nullable|integer|min:0',
    ]);

    $volunteerProfile = DB::transaction(function () use ($validated) {

        $tempPassword = Str::random(10);
        $email = 'vol_' . Str::random(6) . '@placeholder.local';

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $email,
            'password'  => Hash::make($tempPassword),
            'role'      => 'volunteer',
            'is_active' => true,
        ]);

        $user->profile()->create([
            'city_id' => $validated['city'],
            'phone'   => $validated['phone'],
        ]);

        $volunteerProfile = $user->volunterProfile()->create([
            'status'            => $validated['status'],
            'Favorite_period'   => 'صباحاً',
            'Commitment_type'   => 'مرة بمرة',
            'Educational_level' => 'بكالوريوس',
            'total_hours'       => $validated['total_hours']
        ]);

        if (!empty($validated['skills'])) {
            $volunteerProfile->skills()->sync($validated['skills']); // ✅ مباشرة
        }

        return $volunteerProfile;
    });

    return response()->json([
        'message' => 'تمت إضافة المتطوع بنجاح',
        'data' => [
        'id'     => $volunteerProfile->user_id, // ✅ عدّلها لتكون user_id وليس volunterProfile->id
            'name'   => $volunteerProfile->user->name,
            'phone'  => $volunteerProfile->user->profile->phone,
            'city'   => $volunteerProfile->user->profile->city->name,
            'status' => $volunteerProfile->status,
            'skills' => $volunteerProfile->skills->pluck('name'),
            'total_hours' => $volunteerProfile->total_hours
        ],
    ], 201);
}
public function destroy($id)
{
    $user = User::where('role', 'volunteer')->find($id);

    if (!$user) {
        return response()->json([
            'message' => 'المتطوع غير موجود',
        ], 404);
    }

    DB::transaction(function () use ($user) {

        $volunteer = $user->volunterProfile;

        if ($volunteer) {
            $volunteer->skills()->detach();
            $volunteer->delete();
        }

        optional($user->profile)->delete();

        $user->delete();
    });

    return response()->json([
        'message' => 'تم حذف المتطوع بنجاح',
    ]);
}
public function updateStatus(Request $request, $id)
{
    $validated = $request->validate([
        'status' => 'required|in:منشغل,متاح,غير متاح',
    ]);

    $user = User::where('role', 'volunteer')
        ->with('volunterProfile')
        ->find($id);

    if (!$user || !$user->volunterProfile) {
        return response()->json([
            'message' => 'المتطوع غير موجود',
        ], 404);
    }

    $user->volunterProfile->update([
        'status' => $validated['status']
    ]);

    return response()->json([
        'message' => 'تم تحديث حالة المتطوع بنجاح',
        'data' => [
            'id' => $user->id,
            'status' => $user->volunterProfile->status,
        ],
    ]);
}
public function show($id)
{
    $user = User::with([
        'profile.city',
        'volunterProfile.skills'
    ])
        ->where('role', 'volunteer')
        ->find($id);

    if (!$user) {
        return response()->json([
            'message' => 'المتطوع غير موجود',
        ], 404);
    }

    $profile = $user->profile;
    $volunteer = $user->volunterProfile;

    return response()->json([
        'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $profile?->phone,
            'city' => $profile?->city?->name,
            'city_id' => $profile?->city_id,
            'birth_date' => $profile?->birth_date,
            'gender' => $profile?->gender,
            'personal_photo' => $profile?->Personal_photo
                ? asset('storage/' . $profile->Personal_photo)
                : null,

            'bio' => $volunteer?->bio,
            'status' => $volunteer?->status,
            'favorite_period' => $volunteer?->Favorite_period,
            'commitment_type' => $volunteer?->Commitment_type,
            'educational_level' => $volunteer?->Educational_level,
            'total_hours' => $volunteer?->total_hours,
            'previous_voluntering' => (bool)$volunteer?->previous_voluntering,
            'previous_work_place' => $volunteer?->previous_work_place,
            'experience_years' => $volunteer?->experience_years,
            'car' => (bool)$volunteer?->car,
            'facebook' => $volunteer?->facebook,
            'linkedin' => $volunteer?->linkedin,
            'points' => $volunteer?->points,
            'rank' => $volunteer?->rank,
            'skills' => $volunteer?->skills->pluck('name'),
            'created_at' => optional($volunteer?->created_at)->format('Y-m-d'),
        ]
    ]);
}
public function update(Request $request, $id)
{
    $user = User::with([
        'profile',
        'volunterProfile.skills',
        'volunterProfile.domains',
        'volunterProfile.days',
        'volunterProfile.categories',
        'volunterProfile.languages'
    ])->where('role', 'volunteer')->find($id);

    if (!$user) {
        return response()->json([
            'message' => 'المتطوع غير موجود',
        ], 404);
    }

    $validated = $request->validate([
        'name' => 'sometimes|string|max:255',
        'phone' => 'sometimes|string|unique:profiles,phone,' . optional($user->profile)->id,
        'city_id' => 'sometimes|exists:cities,id',
        'birth_date' => 'sometimes|date|before:today',
        'gender' => 'sometimes|in:ذكر,أنثى',
        'bio' => 'nullable|string|max:1000',
       // 'status' => 'sometimes|in:منشغل,متاح,غير متاح',
        'Favorite_period' => 'sometimes|string|max:255',
        'Commitment_type' => 'sometimes|string|max:255',
        'Educational_level' => 'sometimes|string|max:255',
        'experience_years' => 'sometimes|integer|min:0',
        'car' => 'sometimes|boolean',
        'previous_voluntering' => 'sometimes|boolean',
        'previous_work_place' => 'nullable|string|max:255',
        'facebook' => 'nullable|url|max:255',
        'linkedin' => 'nullable|url|max:255',
        'skills' => 'nullable|array',
        'skills.*' => 'exists:skills,id',
        'domains' => 'nullable|array',
        'domains.*' => 'exists:domains,id',
        'days' => 'nullable|array',
        'days.*' => 'exists:days,id',
        'categories' => 'nullable|array',
        'categories.*' => 'exists:categories,id',
        'languages' => 'nullable|array',
        'languages.*' => 'exists:languages,id',
    ]);

    try {
        DB::transaction(function () use ($user, $validated) {

            // تحديث اسم المستخدم
            if (isset($validated['name'])) {
                $user->update(['name' => $validated['name']]);
            }

            // تحديث بيانات البروفايل
            if ($user->profile) {
                $profileData = [];
                if (isset($validated['city_id'])) $profileData['city_id'] = $validated['city_id'];
                if (isset($validated['phone'])) $profileData['phone'] = $validated['phone'];
                if (isset($validated['birth_date'])) $profileData['birth_date'] = $validated['birth_date'];
                if (isset($validated['gender'])) $profileData['gender'] = $validated['gender'];

                if (!empty($profileData)) {
                    $user->profile->update($profileData);
                }
            }

            // تحديث بيانات المتطوع
            if ($user->volunterProfile) {
                $volunteerData = [];

                $fields = [
                    'status', 'Favorite_period', 'Commitment_type', 'Educational_level',
                    'experience_years', 'car', 'previous_voluntering', 'previous_work_place',
                    'bio', 'facebook', 'linkedin'
                ];

                foreach ($fields as $field) {
                    if (isset($validated[$field])) {
                        $volunteerData[$field] = $validated[$field];
                    }
                }

                if (!empty($volunteerData)) {
                    $user->volunterProfile->update($volunteerData);
                }

                // تحديث العلاقات
                if (isset($validated['skills'])) {
                    $user->volunterProfile->skills()->sync($validated['skills']);
                }

                if (isset($validated['domains'])) {
                    $user->volunterProfile->domains()->sync($validated['domains']);
                }

                if (isset($validated['days'])) {
                    $user->volunterProfile->days()->sync($validated['days']);
                }

                if (isset($validated['categories'])) {
                    $user->volunterProfile->categories()->sync($validated['categories']);
                }

                if (isset($validated['languages'])) {
                    $user->volunterProfile->languages()->sync($validated['languages']);
                }
            }
        });

        // إعادة تحميل البيانات
        $user->refresh();
        $user->load([
            'profile.city',
            'volunterProfile.skills',
            'volunterProfile.domains',
            'volunterProfile.days',
            'volunterProfile.categories',
            'volunterProfile.languages'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات المتطوع بنجاح',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => optional($user->profile)->phone,
                'city' => optional($user->profile->city)->name,
                'city_id' => optional($user->profile)->city_id,
                'birth_date' => optional($user->profile)->birth_date,
                'gender' => optional($user->profile)->gender,
                'bio' => optional($user->volunterProfile)->bio,
                'status' => optional($user->volunterProfile)->status,
                'favorite_period' => optional($user->volunterProfile)->Favorite_period,
                'commitment_type' => optional($user->volunterProfile)->Commitment_type,
                'educational_level' => optional($user->volunterProfile)->Educational_level,
                'experience_years' => optional($user->volunterProfile)->experience_years,
                'car' => (bool) optional($user->volunterProfile)->car,
                'previous_voluntering' => (bool) optional($user->volunterProfile)->previous_voluntering,
                'previous_work_place' => optional($user->volunterProfile)->previous_work_place,
                'facebook' => optional($user->volunterProfile)->facebook,
                'linkedin' => optional($user->volunterProfile)->linkedin,
                'skills' => optional($user->volunterProfile)->skills->pluck('name'),
                'domains' => optional($user->volunterProfile)->domains->pluck('name'),
                'days' => optional($user->volunterProfile)->days->pluck('name'),
                'categories' => optional($user->volunterProfile)->categories->pluck('name'),
                'languages' => optional($user->volunterProfile)->languages->pluck('name'),
                'total_hours' => optional($user->volunterProfile)->total_hours,
                'points' => optional($user->volunterProfile)->points,
                'rank' => optional($user->volunterProfile)->rank,
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء تحديث البيانات: ' . $e->getMessage(),
        ], 500);
    }
}
public function getVolunteersList(Request $request)
    {
        $user = $request->user();

        $volunteers = User::where('role', 'volunteer')
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->with('volunterProfile')
            ->get(['id', 'name', 'email', 'role', 'is_active']);

        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => $volunteers
        ], 200);
    }

}
