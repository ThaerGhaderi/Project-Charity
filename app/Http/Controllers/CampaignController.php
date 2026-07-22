<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use App\Models\Notification;
use App\Models\User;
use App\Models\VolunteerTask;
use App\Models\VolunterProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        $perPage = $request->get('per_page', 15);
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
        $allCategories = [
            'تعليم', 'صحي', 'إغاثة', 'إيواء', 'غذاء',
            'مياه', 'كسوة', 'دعم نفسي', 'تمكين اقتصادي',
            'أطفال', 'بيئة', 'ثقافة', 'رياضة',
            'تكنولوجيا', 'تنمية مجتمعية'
        ];
        
        $categoriesFromCampaigns = Campaign::select('category')
            ->distinct()
            ->whereNotNull('category')
            ->pluck('category')
            ->toArray();
        
        $mergedCategories = array_unique(array_merge($allCategories, $categoriesFromCampaigns));
        sort($mergedCategories);
        
        return response()->json([
            'success' => true,
            'data' => array_values($mergedCategories)
        ], 200);
    }

    /**
     * Create a new campaign
     */
   public function store(Request $request)
{
    // ✅ حل مشكلة قراءة JSON
    if (empty($request->all())) {
        $jsonData = $request->json()->all();
        if (!empty($jsonData)) {
            $request->merge($jsonData);
        }
    }

    if (!Auth::check()) {
        return response()->json([
            'success' => false,
            'message' => 'يجب تسجيل الدخول لإنشاء حملة'
        ], 401);
    }

    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'goal_amount' => 'required|numeric|min:1000',
        'category' => 'nullable|string|max:100',
        'is_emergency' => 'boolean',
        'start_date' => 'nullable|date|after_or_equal:today',
        'end_date' => 'nullable|date|after:start_date',
        'location' => 'nullable|string|max:255',
        'volunteer_ids' => 'nullable|array',
        'volunteer_ids.*' => 'exists:volunter_profiles,id',
    ]);

    $validated['collected_amount'] = 0;
    $validated['status'] = 'draft';
    $validated['created_by'] = Auth::id();
    $validated['short_url'] = Str::random(8);
    
    try {
        $campaign = Campaign::create($validated);
        
        Notification::sendPushOnly(
            Auth::id(),
            '📢 تم إنشاء حملة جديدة',
            "تم إنشاء حملة '{$campaign->title}' بنجاح. وهي قيد المراجعة.",
            'campaign',
            ['campaign_id' => $campaign->id]
        );
        
        $volunteerIds = $request->volunteer_ids ?? [];
        $this->createVolunteerTasksForCampaign($campaign, $volunteerIds);
        
        // ✅ تحميل المهام المرتبطة
        $campaign->load('volunteerTasks');
        
        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الحملة بنجاح. وهي قيد المراجعة.',
            'data' => $campaign
        ], 201);
        
    } catch (\Exception $e) {
        Log::error('Campaign creation failed: ' . $e->getMessage());
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
     */
    private function sendStatusChangeNotifications($campaign, $oldStatus, $newStatus)
    {
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
                Notification::sendPushOnly(
                    $donor->id,
                    $title,
                    $body,
                    'campaign_status',
                    ['campaign_id' => $campaign->id]
                );
            }
        }
        
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

    /**
     * جلب قائمة المتطوعين المتاحين (API مساعد)
     */
    public function getAvailableVolunteers(Request $request)
    {
        $volunteers = VolunterProfile::with('user')
            ->where('status', 'متاح')
            ->get()
            ->map(function($volunteer) {
                return [
                    'id' => $volunteer->id,
                    'name' => $volunteer->user->name ?? 'غير معروف',
                    'email' => $volunteer->user->email ?? 'غير معروف',
                    'total_hours' => $volunteer->total_hours,
                    'status' => $volunteer->status,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $volunteers
        ]);
    }

    /**
     * إنشاء مهام تطوعية للحملة مع تحديد المتطوعين
     */
    private function createVolunteerTasksForCampaign($campaign, $volunteerIds = [])
    {
        $admin = User::whereIn('role', ['admin', 'Admin'])->first();
        $supervisorId = $admin ? $admin->id : 1;

        // ✅ إذا تم تحديد متطوعين، استخدمهم
        if (!empty($volunteerIds)) {
            $volunteers = VolunterProfile::whereIn('id', $volunteerIds)->get();
            
            // ✅ إذا لم يتم العثور على المتطوعين، استخدم أول متطوع
            if ($volunteers->isEmpty()) {
                $volunteers = VolunterProfile::limit(1)->get();
            }
        } else {
            // ✅ إذا لم يتم تحديد متطوعين، استخدم جميع المتطوعين المتاحين
            $volunteers = VolunterProfile::where('status', 'متاح')->get();
            
            // ✅ إذا لم يوجد متطوعين، أنشئ متطوع افتراضي
            if ($volunteers->isEmpty()) {
                $user = User::create([
                    'name' => 'متطوع افتراضي',
                    'email' => 'volunteer_default_' . time() . '@test.com',
                    'password' => bcrypt('password123'),
                    'role' => 'volunteer',
                    'profile_completed' => true,
                    'email_verified_at' => now(),
                ]);
                
                $volunteer = VolunterProfile::create([
                    'user_id' => $user->id,
                    'Favorite_period' => 'صباحاً',
                    'Commitment_type' => 'منتظم',
                    'Educational_level' => 'بكالوريوس',
                    'status' => 'متاح',
                    'total_hours' => 0,
                ]);
                
                $volunteers = collect([$volunteer]);
            }
        }

        // ✅ تعريف المهام
        $tasks = [
            [
                'title' => "توزيع المساعدات - {$campaign->title}",
                'description' => "توزيع المساعدات على المستفيدين ضمن حملة {$campaign->title}",
                'location' => $campaign->location ?? 'غير محدد',
            ],
            [
                'title' => "تنظيم الفعاليات - {$campaign->title}",
                'description' => "تنظيم فعاليات الحملة واستقبال المتبرعين",
                'location' => $campaign->location ?? 'غير محدد',
            ],
            [
                'title' => "تسجيل المستفيدين - {$campaign->title}",
                'description' => "تسجيل بيانات المستفيدين من الحملة",
                'location' => $campaign->location ?? 'غير محدد',
            ],
        ];

        // ✅ توزيع المهام على المتطوعين
        $volunteerIndex = 0;
        $volunteerCount = $volunteers->count();
        
        foreach ($tasks as $taskData) {
            $volunteer = $volunteers[$volunteerIndex % $volunteerCount];
            
            VolunteerTask::create([
                'campaign_id' => $campaign->id,
                'volunteer_id' => $volunteer->id,
                'title' => $taskData['title'],
                'description' => $taskData['description'],
                'location' => $taskData['location'],
                'status' => 'جديدة',
                'supervisor_id' => $supervisorId,
                'expected_end_time' => now()->addDays(7),
            ]);
            
            $volunteerIndex++;
        }
    }
}