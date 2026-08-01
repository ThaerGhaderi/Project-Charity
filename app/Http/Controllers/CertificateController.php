<?php

namespace App\Http\Controllers;

use App\Models\VolunteerCertificate;
use App\Models\VolunterProfile;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    /**
     * عرض جميع شهادات المتطوع
     *
     * @api {get} /api/volunteer/certificates Get Certificates
     * @apiHeader Authorization Bearer {token}
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunterProfile;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }

        $certificates = VolunteerCertificate::where('volunteer_id', $volunteer->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // ✅ حساب المستوى التالي
        $nextLevel = $this->getNextLevel($volunteer);

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب الشهادات بنجاح',
            'data' => [
                'certificates' => $certificates,
                'next_level' => $nextLevel,
                'total_hours' => $volunteer->total_hours ?? 0,
                'total_certificates' => $certificates->count(),
            ]
        ], 200);
    }

    /**
     * عرض تفاصيل شهادة معينة
     *
     * @api {get} /api/volunteer/certificates/{id} Get Certificate Details
     * @apiHeader Authorization Bearer {token}
     */
    public function show($id, Request $request)
    {
        $user = $request->user();
        $volunteer = $user->volunterProfile;

        if (!$volunteer) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'لم يتم العثور على ملف المتطوع',
            ], 404);
        }

        $certificate = VolunteerCertificate::where('volunteer_id', $volunteer->id)
            ->where('id', $id)
            ->first();

        if (!$certificate) {
            return response()->json([
                'code' => '404',
                'success' => false,
                'message' => 'الشهادة غير موجودة أو ليست لك',
            ], 404);
        }

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم جلب تفاصيل الشهادة بنجاح',
            'data' => $certificate
        ], 200);
    }

    /**
     * إنشاء شهادة جديدة (للأدمن فقط)
     *
     * @api {post} /api/admin/certificates Create Certificate
     * @apiHeader Authorization Bearer {admin_token}
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // ✅ التحقق من أن المستخدم أدمن
        if (!in_array($user->role, ['admin', 'Admin'])) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'غير مصرح لك. هذه العملية للأدمن فقط.',
            ], 403);
        }

        $validated = $request->validate([
            'volunteer_id' => 'required|exists:volunter_profiles,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'level' => 'required|in:برونزية,فضية,ذهبية,ماسية',
            'hours_required' => 'required|integer|min:1',
            'hours_completed' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['hours_completed'] = $validated['hours_completed'] ?? 0;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $certificate = VolunteerCertificate::create($validated);

        return response()->json([
            'code' => '201',
            'success' => true,
            'message' => 'تم إنشاء الشهادة بنجاح',
            'data' => $certificate
        ], 201);
    }

    /**
     * تحديث شهادة (للأدمن فقط)
     *
     * @api {put} /api/admin/certificates/{id} Update Certificate
     * @apiHeader Authorization Bearer {admin_token}
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'Admin'])) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'غير مصرح لك. هذه العملية للأدمن فقط.',
            ], 403);
        }

        $certificate = VolunteerCertificate::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'level' => 'sometimes|in:برونزية,فضية,ذهبية,ماسية',
            'hours_required' => 'sometimes|integer|min:1',
            'hours_completed' => 'sometimes|integer|min:0',
            'is_active' => 'boolean',
            'issued_at' => 'nullable|date',
            'certificate_number' => 'nullable|string|max:255',
        ]);

        $certificate->update($validated);

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم تحديث الشهادة بنجاح',
            'data' => $certificate
        ], 200);
    }

    /**
     * حذف شهادة (للأدمن فقط)
     *
     * @api {delete} /api/admin/certificates/{id} Delete Certificate
     * @apiHeader Authorization Bearer {admin_token}
     */
    public function destroy($id, Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'Admin'])) {
            return response()->json([
                'code' => '403',
                'success' => false,
                'message' => 'غير مصرح لك. هذه العملية للأدمن فقط.',
            ], 403);
        }

        $certificate = VolunteerCertificate::findOrFail($id);
        $certificate->delete();

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم حذف الشهادة بنجاح',
        ], 200);
    }

    // ==================== PRIVATE METHODS ====================

    /**
     * حساب المستوى التالي للمتطوع
     */
    private function getNextLevel($volunteer)
    {
        $levels = [
            'برونزية' => 30,
            'فضية' => 60,
            'ذهبية' => 100,
            'ماسية' => 200,
        ];

        $currentHours = $volunteer->total_hours ?? 0;
        $currentLevel = null;
        $nextLevel = null;
        $hoursNeeded = 0;

        foreach ($levels as $level => $hours) {
            if ($currentHours >= $hours) {
                $currentLevel = $level;
            } elseif ($nextLevel === null) {
                $nextLevel = $level;
                $hoursNeeded = $hours - $currentHours;
                break;
            }
        }

        if ($nextLevel === null) {
            $nextLevel = 'المستوى الأقصى (ماسية)';
            $hoursNeeded = 0;
        }

        $progress = 0;
        if ($nextLevel !== 'المستوى الأقصى (ماسية)' && $hoursNeeded > 0) {
            $totalNeeded = $this->getHoursForLevel($nextLevel);
            $progress = $totalNeeded > 0 ? round(($currentHours / $totalNeeded) * 100) : 0;
        }

        return [
            'current_level' => $currentLevel ?? 'بداية',
            'next_level' => $nextLevel,
            'hours_needed' => max(0, $hoursNeeded),
            'progress_percentage' => min($progress, 100),
            'current_hours' => $currentHours,
        ];
    }

    /**
     * الحصول على الساعات المطلوبة لمستوى معين
     */
    private function getHoursForLevel($level)
    {
        $levels = [
            'برونزية' => 30,
            'فضية' => 60,
            'ذهبية' => 100,
            'ماسية' => 200,
        ];
        return $levels[$level] ?? 0;
    }
}
