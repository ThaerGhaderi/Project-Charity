<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CampaignController extends Controller
{
    /**
     * Get all campaigns with filters
     */
    public function index(Request $request)
    {
        $query = Campaign::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ['active', 'completed']);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter emergency
        if ($request->has('is_emergency')) {
            $query->where('is_emergency', $request->boolean('is_emergency'));
        }

        // Search by title
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 0);
        $campaigns = $query->paginate($perPage);

        // Add computed attributes
       $campaigns->getCollection()->transform(function ($campaign) {
            $campaign->progress_percentage = $campaign->progress_percentage;
            $campaign->remaining_amount = $campaign->remaining_amount;
            $campaign->donors_count = $campaign->donors_count;
            return $campaign;
        });

        return response()->json([
            'success' => true,
            'data' => $campaigns
        ], 200);
    }

    /**
     * Get featured campaigns (for homepage)
     */
    public function featured(Request $request)
    {
        $campaigns = Campaign::where('status', 'active')
            ->orderBy('is_emergency', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $campaigns->transform(function ($campaign) {
            $campaign->progress_percentage = $campaign->progress_percentage;
            $campaign->remaining_amount = $campaign->remaining_amount;
            $campaign->donors_count = $campaign->donors_count;
            return $campaign;
        });

        return response()->json([
            'success' => true,
            'data' => $campaigns
        ], 200);
    }

    /**
     * Get single campaign details
     */
    public function show($id)
    {
        $campaign = Campaign::with(['media', 'updates' => function($q) {
            $q->orderBy('created_at', 'desc')->limit(5);
        }])->findOrFail($id);

        $campaign->progress_percentage = $campaign->progress_percentage;
        $campaign->remaining_amount = $campaign->remaining_amount;
        $campaign->donors_count = $campaign->donors_count;

        return response()->json([
            'success' => true,
            'data' => $campaign
        ], 200);
    }

    /**
     * Get campaign updates
     */
    public function updates($id)
    {
        $campaign = Campaign::findOrFail($id);
        
        $updates = $campaign->updates()
            ->with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $updates
        ], 200);
    }

    /**
     * Get campaign categories list
     */
   public function categories()
{
    // التصنيفات الأساسية الثابتة (جميع التصنيفات المتاحة في النظام)
    $allCategories = [
        'تعليم',
        'صحي', 
        'إغاثة',
        'إيواء',
        'غذاء',
        'مياه',
        'كسوة',
        'دعم نفسي',
        'تمكين اقتصادي',
        'أطفال',
        'بيئة',
        'ثقافة',
        'رياضة',
        'تكنولوجيا',
        'تنمية مجتمعية'
    ];
    
    // جلب التصنيفات الموجودة في الحملات (التي استخدمت فعلاً)
    $categoriesFromCampaigns = Campaign::select('category')
        ->distinct()
        ->whereNotNull('category')
        ->pluck('category')
        ->toArray();
    
    // دمج التصنيفات الثابتة مع الموجودة في الحملات
    // مع إزالة المكرر وترتيب تصاعدي
    $mergedCategories = array_unique(array_merge($allCategories, $categoriesFromCampaigns));
    sort($mergedCategories);
    
    return response()->json([
        'success' => true,
        'data' => array_values($mergedCategories) // إعادة ترقيم المصفوفة
    ]);
}
    /**
     * Create a new campaign
     */
    public function store(Request $request)
    {
        // 1. التحقق من أن المستخدم مسجل دخول
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول لإنشاء حملة'
            ], 401);
        }

        // 2. التحقق من صحة البيانات
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'goal_amount' => 'required|numeric|min:1000',
            'category' => 'nullable|string|max:100',
            'is_emergency' => 'boolean',
            'start_date' => 'nullable|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        // 3. إضافة البيانات الإضافية
        $validated['collected_amount'] = 0;
        $validated['status'] = 'draft';
        $validated['created_by'] = Auth::id();
        $validated['short_url'] = Str::random(8);
        
        // 4. إنشاء الحملة
        try {
            $campaign = Campaign::create($validated);
            
            // ✅ إرسال إشعار Firebase لمنشئ الحملة
            Notification::sendPushOnly(
                Auth::id(),
                '📢 تم إنشاء حملة جديدة',
                "تم إنشاء حملة '{$campaign->title}' بنجاح. وهي قيد المراجعة.",
                'campaign',
                ['campaign_id' => $campaign->id]
            );
            
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الحملة بنجاح. وهي قيد المراجعة.',
                'data' => $campaign
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الحملة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update campaign status
     */
    public function updateStatus(Request $request, $id)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول'
            ], 401);
        }

        $campaign = Campaign::findOrFail($id);
        $user = Auth::user();
        $isAdmin = $user->role === 'admin' || $user->role === 'Admin';
        $isCreator = $campaign->created_by == $user->id;
        
        if (!$isAdmin && !$isCreator) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بتعديل حالة هذه الحملة'
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:draft,review,active,closed,completed,cancelled',
            'reason' => 'required_if:status,cancelled|nullable|string|max:500'
        ]);

        $oldStatus = $campaign->status;
        $newStatus = $request->status;

        $campaign->status = $newStatus;
        
        if ($newStatus === 'cancelled') {
            $campaign->cancelled_reason = $request->reason;
        }
        
        if ($newStatus === 'completed') {
            if ($campaign->collected_amount < $campaign->goal_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن إكمال الحملة قبل الوصول إلى الهدف المالي'
                ], 400);
            }
        }
        
        if ($newStatus === 'active') {
            if (!$campaign->start_date || $campaign->start_date > now()) {
                $campaign->start_date = now();
            }
        }
        
        $campaign->save();

        // ✅ إرسال إشعارات Firebase للمتبرعين
        $this->sendStatusChangeNotifications($campaign, $oldStatus, $newStatus);

        return response()->json([
            'success' => true,
            'message' => $this->getStatusChangeMessage($newStatus),
            'data' => [
                'campaign' => $campaign,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]
        ], 200);
    }

    /**
     * Send notifications to donors when campaign status changes
     * ✅ Firebase فقط (بدون حفظ في قاعدة البيانات)
     */
    private function sendStatusChangeNotifications($campaign, $oldStatus, $newStatus)
    {
        // جلب جميع المتبرعين لهذه الحملة
        $donors = $campaign->donations()
            ->where('status', 'completed')
            ->with('user')
            ->get()
            ->pluck('user')
            ->unique('id');
        
        $title = $this->getNotificationTitle($campaign->title, $newStatus);
        $body = $this->getNotificationBody($campaign, $oldStatus, $newStatus);
        
        foreach ($donors as $donor) {
            if ($donor && $donor->id) {
                // ✅ إرسال إشعار Firebase فقط
                Notification::sendPushOnly(
                    $donor->id,
                    $title,
                    $body,
                    'campaign_status',
                    ['campaign_id' => $campaign->id]
                );
            }
        }
        
        // إرسال إشعار لمنشئ الحملة
        $creator = User::find($campaign->created_by);
        if ($creator) {
            Notification::sendPushOnly(
                $campaign->created_by,
                $title,
                $body,
                'campaign_status',
                ['campaign_id' => $campaign->id]
            );
        }
    }

    /**
     * Get notification title based on status
     */
    private function getNotificationTitle($campaignTitle, $status)
    {
        return match ($status) {
            'active' => "🚀 حملة {$campaignTitle} أصبحت نشطة",
            'completed' => "🎉 حملة {$campaignTitle} حققت هدفها!",
            'cancelled' => "⛔ حملة {$campaignTitle} تم إلغاؤها",
            'closed' => "🔒 حملة {$campaignTitle} تم إغلاقها",
            'review' => "📝 حملة {$campaignTitle} قيد المراجعة",
            default => "📢 تحديث حالة حملة {$campaignTitle}"
        };
    }

    /**
     * Get notification body based on status change
     */
    private function getNotificationBody($campaign, $oldStatus, $newStatus)
    {
        $progress = $campaign->progress_percentage;
        $collected = number_format($campaign->collected_amount, 2);
        $goal = number_format($campaign->goal_amount, 2);
        
        return match ($newStatus) {
            'active' => "تم تفعيل حملة {$campaign->title}. يمكنك الآن التبرع لدعم هذا المشروع.",
            'completed' => "الحمد لله! حملة {$campaign->title} حققت هدفها البالغ {$goal} \$ بنسبة {$progress}% من {$collected} \$ متبرع. شكراً لدعمكم!",
            'cancelled' => "تم إلغاء حملة {$campaign->title}. سيتم استرداد التبرعات خلال 14 يوماً.",
            'closed' => "تم إغلاق حملة {$campaign->title} بعد تحقيق {$progress}% من الهدف ({$collected} \$ من {$goal} \$).",
            default => "تم تحديث حالة حملة {$campaign->title} إلى {$newStatus}"
        };
    }

    /**
     * Get success message for status change
     */
    private function getStatusChangeMessage($status)
    {
        return match ($status) {
            'active' => 'تم تفعيل الحملة بنجاح وهي الآن متاحة للتبرع',
            'completed' => 'تم إكمال الحملة بنجاح، شكراً لجميع المتبرعين',
            'cancelled' => 'تم إلغاء الحملة، سيتم إشعار المتبرعين',
            'closed' => 'تم إغلاق الحملة',
            'review' => 'تم إرسال الحملة للمراجعة',
            'draft' => 'تم حفظ الحملة كمسودة',
            default => 'تم تحديث حالة الحملة بنجاح'
        };
    }

    /**
     * Check and update campaign status automatically
     */
    public function checkAndUpdateStatus($id)
    {
        $campaign = Campaign::findOrFail($id);
        
        $oldStatus = $campaign->status;
        $autoUpdated = false;
        
        if ($campaign->status === 'active' && $campaign->collected_amount >= $campaign->goal_amount) {
            $campaign->status = 'completed';
            $campaign->save();
            $autoUpdated = true;
            $this->sendStatusChangeNotifications($campaign, $oldStatus, 'completed');
        }
        
        if ($campaign->status === 'active' && $campaign->end_date && $campaign->end_date < now()) {
            $campaign->status = 'closed';
            $campaign->save();
            $autoUpdated = true;
            $this->sendStatusChangeNotifications($campaign, $oldStatus, 'closed');
        }
        
        return response()->json([
            'success' => true,
            'message' => $autoUpdated ? 'تم تحديث حالة الحملة تلقائياً' : 'الحالة كما هي',
            'data' => [
                'campaign' => $campaign,
                'was_updated' => $autoUpdated,
                'old_status' => $oldStatus,
                'current_status' => $campaign->status
            ]
        ], 200);
    }
}