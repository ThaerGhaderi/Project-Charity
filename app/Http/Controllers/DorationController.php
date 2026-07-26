<?php

namespace App\Http\Controllers;
use App\Exports\DonationExport;

use App\Http\Requests\StoreDorationRequest;
use App\Models\DonorProfile;
use App\Models\Doration;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;

class DorationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
public function export()
{
    // 1. توليد وحفظ ملف الإكسل داخل مجلد التخزين العام
    Excel::store(new DonationExport, 'Donation.xlsx', 'public');

    // 2. إرجاع رسالة النجاح مع رابط التحميل البرمجي الآمن الجديد
    return response()->json([
        'message' => 'تم حفظ الملف بنجاح في السيرفر',
        'file_url' => url('/download-donation') // التعديل هنا
    ]);
}


    public function index()
    {
        $dorations = Doration::with('donorProfile.user')->latest()->get();
        return response()->json($dorations);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }
    public function store(StoreDorationRequest $request)
    {
       if ($request->has('donor_email')) {
        $user = User::firstOrCreate(
            ['email' => $request->donor_email],
            [
                'name'      => $request->name,
                'password'  => bcrypt(str()->random(16)),
                'role'      => 'Donor',
                'is_active' => false,
            ]
        );
    } else {
        $user = User::firstOrCreate(
            ['name' => $request->name],
            [
                'email'     => 'guest_' . str()->random(8) . '@anonymous.local',
                'password'  => bcrypt(str()->random(16)),
                'role'      => 'Donor',
                'is_active' => false,
            ]
        );
    }
        $donorProfile = DonorProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'donor_type'   => $request->donor_type ?? 'فردي',
                'is_anonymous' => $request->is_anonymous ?? false,
                'bio'          => null,
            ]
        );
        $doration = $donorProfile->dorations()->create([
            'name'   => $request->name,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'cat'    => $request->cat,
            'date'   => $request->date,
            'notes'  => $request->notes ?? '',
        ]);
        return response()->json([
            'message'  => 'تم إضافة التبرع بنجاح',
            'doration' => $doration,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
       $doration = Doration::with('donorProfile.user')->find($id);

    if (!$doration) {
        return response()->json([
            'status' => false,
            'message' => 'التبرع غير موجود'
        ], 404);
    }

    return response()->json([
        'status' => true,
        'message' => 'تم ارجاع البيانات بنجاح',
        'data' => $doration
    ], 200);
    }
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Doration $doration)
    {
        //
    }


public function update(Request $request, $id)
{
    $doration = Doration::find($id);

    if (!$doration) {
        return response()->json([
            'status' => false,
            'message' => 'التبرع غير موجود'
        ], 404);
    }

    // نتأكد إن الحالة المرسلة موجودة ضمن الخيارات المسموح بها فقط
    $request->validate([
        'status' => 'sometimes|in:مكتمل,ملغي,قيد المراجعة',
    ]);

    // نحدث الحالة فقط إذا تم إرسالها
    if ($request->has('status')) {
        $doration->status = $request->status;
        $doration->save();
        
    }

    // نرجع البيانات للرياكت
    return response()->json([
        'status' => true,
        'message' => 'تم تحديث حالة التبرع بنجاح',
        'data' => $doration
    ]);
}
    /**
     * Remove the specified resource from storage.
     */
     public function destroy($id)
    {
        $doration = Doration::find($id);

    if (!$doration) {
        return response()->json([
            'status' => false,
            'message' => 'التبرع غير موجود'
        ], 404);
    }
    $doration->delete();
    return response()->json([
        'status' => true,
        'message' => 'تم حذف التبرع بنجاح'
    ], 200);
    }
}
