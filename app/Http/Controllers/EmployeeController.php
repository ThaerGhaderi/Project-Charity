<?php
namespace App\Http\Controllers;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class EmployeeController extends Controller
{
public function login(Request $request)
{
    // 1. تعديل التحقق ليكون بالشرطة السفلية لضمان قراءته كـ Key سليم
$request->validate([
    'fullName' => 'required|string',
    'password' => 'required|string',
]);

// البحث في عمود قاعدة البيانات المسمى full_name بناءً على الـ fullName القادم
$employee = Employee::where('full_name', $request->fullName)->first();


        // 3. التحقق من صحة الحساب وكلمة المرور
        if (!$employee || !Hash::check($request->password, $employee->password)) {
            return response()->json([
                'message' => 'الاسم الكامل أو كلمة المرور غير صحيحة.'
            ], 401); // 401 ليفهم الرياكت أن البيانات خاطئة
        }

        // 4. توليد توكن Sanctum مخصص للموظف (هذا السطر السحري البديل للـ Session)
        $token = $employee->createToken('dashboard_token')->plainTextToken;

        // 5. إرجاع الاستجابة بالهيكلية التي يتوقعها كود الـ onSuccess في رياكت
        return response()->json([
            'token' => $token,
            'user' => [
                'id'       => $employee->id,
                'fullName' => $employee->full_name,
                'role'     => $employee->role ?? 'موظف', // إرسال الصلاحية
            ]
        ], 200);
    }

    public function logout(Request $request)
    {
        // حذف التوكن الحالي الفعال الذي خرج منه الموظف لتأمين الجلسة
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح'
        ], 200);
    }

    // باقي دوال الـ CRUD الأساسية تترك كما هي تحت...
    public function index() { }
    public function create() { }
    public function store(Request $request) { }
    public function show(Employee $employee) { }
    public function edit(Employee $employee) { }
    public function update(Request $request, Employee $employee) { }
    public function destroy(Employee $employee) { }
}