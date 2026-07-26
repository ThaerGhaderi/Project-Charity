<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BeneficiaryProfile;
use App\Http\Requests\BeneficiaryProfileRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
            
            // ✅ من الملف الثاني: تحديث profile_completed
            $user->update([
                'profile_completed' => true,
                'is_active' => true,
            ]);
            
            return ['profile' => $profile, 'beneficiary' => $beneficiaryProfile];
        });
        return response()->json([
            'code'    => '201',
            'success' => true,
            'message' => 'Beneficiary profile completed successfully.',
            'data'    => [
                // ✅ من الملف الثاني: use fresh() و profile_completed
                'user' => $user->fresh()->only(['id', 'name', 'email', 'role', 'profile_completed']),
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
        
        // ✅ من الملف الثاني: استخدام beneficiary->is_anonymized
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
                    'name' => $user->beneficiary->is_anonymized ? 'Anonymous' : $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ],
                'profile' => $profileData
            ]
        ]);
    }
    
    /**
     * ✅ من الملف الثاني: تحديث الملف الشخصي
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if (!$user->beneficiary) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'Beneficiary profile not found.',
            ], 404);
        }

        $validated = $request->validate([
            'marital_status' => 'sometimes|in:أعزب,متزوج,مطلق,أرمل,يتيم',
            'family_members_count' => 'sometimes|integer|min:1|max:20',
            'has_income' => 'sometimes|boolean',
            'income_range' => 'nullable|in:أقل من 100 الف,100-300 الف,300-500 الف,أكثر من 500 الف',
            'is_Anonymous' => 'sometimes|boolean',
            'phone' => 'sometimes|string|max:20',
            'birth_date' => 'sometimes|date|before:today',
            'gender' => 'sometimes|in:ذكر,انثى',
            'city_id' => 'sometimes|exists:cities,id',
        ]);

        try {
            DB::beginTransaction();

            // Update beneficiary profile
            $beneficiaryData = array_intersect_key($validated, [
                'marital_status' => true,
                'family_members_count' => true,
                'has_income' => true,
                'income_range' => true,
                'is_Anonymous' => true,
            ]);
            
            if (!empty($beneficiaryData)) {
                $user->beneficiary->update($beneficiaryData);
            }

            // Update general profile
            $profileData = array_intersect_key($validated, [
                'phone' => true,
                'birth_date' => true,
                'gender' => true,
                'city_id' => true,
            ]);

            if (!empty($profileData) && $user->profile) {
                $user->profile->update($profileData);
            }

            DB::commit();

            // Load fresh data
            $user->refresh();
            $user->load(['profile', 'beneficiary']);

            return response()->json([
                'code' => '200',
                'success' => true,
                'message' => 'Profile updated successfully.',
                'data' => [
                    'user' => $user->only(['id', 'name', 'email', 'role']),
                    'general_profile' => $user->profile ? [
                        'phone' => $user->profile->phone,
                        'city_id' => $user->profile->city_id,
                        'city_name' => $user->profile->city?->name,
                        'birth_date' => $user->profile->birth_date,
                        'gender' => $user->profile->gender,
                    ] : null,
                    'beneficiary_details' => $user->beneficiary,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => 'An error occurred while updating profile.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ من الملف الأول: قائمة المستفيدين
     */
    public function index(Request $request)
    {
        $query = User::where('role', 'Beneficiary')->with('beneficiary');

        if ($request->has('status')) {
            $query->whereHas('beneficiary', function ($q) use ($request) {
                $q->where('status', $request->status);
            });
        }

        $beneficiaries = $query->latest()->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->beneficiary ? $user->beneficiary->status : null,
                    'members' => $user->beneficiary ? $user->beneficiary->family_members_count : 0,
                    'need' => $user->beneficiary ? $user->beneficiary->need : 'غير محدد',
                    'city' => $user->beneficiary ? $user->beneficiary->city : 'غير محدد',
                    'income' => $user->beneficiary ? $user->beneficiary->income_range : 'أقل من 100 الف',
                    'marital_status' => $user->beneficiary ? $user->beneficiary->marital_status : 'غير محدد',
                    'priority' => $user->beneficiary ? $user->beneficiary->priority : 'عادي',
                    'date' => $user->created_at->format('Y-m-d'),
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $beneficiaries,
        ]);
    }
    
    /**
     * ✅ من الملف الأول: عرض مستفيد محدد
     */
    public function show($id)
    {
        $user = User::where('role', 'Beneficiary')
            ->with(['beneficiary.types'])
            ->find($id);

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'المستفيد غير موجود',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $user,
        ]);
    }
    
    /**
     * ✅ من الملف الأول: إنشاء مستفيد جديد (Admin)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'family_members_count' => 'nullable|integer|min:1',
            'Breadwinner' => 'boolean',
            'has_income' => 'boolean',
            'income_range' => 'nullable|in:أقل من 100 الف,100-300 الف,300-500 الف,أكثر من 500 الف',
            'photo_Family_notebook' => 'nullable|image|mimes:jpg,jpeg,png|max:5000',
            'photo_Supporting' => 'nullable|image|mimes:jpg,jpeg,png|max:5000',
            'marital_status' => 'required|in:أعزب,متزوج,مطلق,أرمل,يتيم',
            'is_Anonymous' => 'boolean',
            'notes' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'need' => 'nullable|string|max:255',
            'priority' => 'nullable|string|in:عاجل,متوسط,عادي',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'Beneficiary',
                'is_active' => true,
            ]);

            $familyNotebookPath = null;
            if ($request->hasFile('photo_Family_notebook')) {
                $familyNotebookPath = $request->file('photo_Family_notebook')->store('beneficiaries', 'public');
            }

            $supportingPath = null;
            if ($request->hasFile('photo_Supporting')) {
                $supportingPath = $request->file('photo_Supporting')->store('beneficiaries', 'public');
            }

            $user->beneficiary()->create([
                'family_members_count' => $request->filled('family_members_count') ? $request->family_members_count : 1,
                'Breadwinner' => $request->boolean('Breadwinner'),
                'has_income' => $request->boolean('has_income'),
                'income_range' => $request->filled('income_range') ? $request->income_range : null,
                'photo_Family_notebook' => $familyNotebookPath,
                'photo_Supporting' => $supportingPath,
                'marital_status' => $request->marital_status,
                'is_Anonymous' => $request->boolean('is_Anonymous'),
                'status' => 'قيد المراجعة',
                'notes' => $request->filled('notes') ? $request->notes : null,
                'city' => $request->filled('city') ? $request->city : null,
                'phone' => $request->filled('phone') ? $request->phone : null,
                'need' => $request->filled('need') ? $request->need : 'مسكن',
                'priority' => $request->filled('priority') ? $request->priority : 'عادي',
            ]);
            return $user;
        });

        return response()->json([
            'status' => true,
            'message' => 'تم انشاء المستفيد بنجاح',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => 'قيد المراجعة',
                'members' => $user->beneficiary->family_members_count,
                'need' => $user->beneficiary->need,
                'city' => $user->beneficiary->city,
                'income' => $user->beneficiary->income_range,
                'date' => $user->created_at->format('Y-m-d'),
            ],
        ], 201);
    }
    
    /**
     * ✅ من الملف الأول: تحديث حالة المستفيد
     */
    public function updateStatus(Request $request, $id)
    {
        $user = User::where('role', 'Beneficiary')->with('beneficiary')->find($id);

        if (!$user || !$user->beneficiary) {
            return response()->json([
                'status' => false,
                'message' => 'المستفيد غير موجود',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:قيد المراجعة,مقبول,مرفوض',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->beneficiary->update(['status' => $request->status]);

        return response()->json([
            'status' => true,
            'message' => 'تم تعديل حالة المستفيد بنجاح',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->beneficiary->status,
                'members' => $user->beneficiary->family_members_count,
                'need' => $user->beneficiary->need,
                'city' => $user->beneficiary->city,
                'income' => $user->beneficiary->income_range,
                'date' => $user->created_at->format('Y-m-d'),
            ],
        ]);
    }
    
    /**
     * ✅ من الملف الأول: حذف مستفيد
     */
    public function destroy($id)
    {
        $user = User::where('role', 'Beneficiary')->find($id);

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'المستفيد غير موجود',
            ], 404);
        }

        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف المستفيد بنجاح',
        ]);
    }
}
