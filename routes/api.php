<?php


use App\Http\Controllers\AidApplicationController;
use App\Http\Controllers\BeneficiaryProfileController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DayController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\DonationCartController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\DonorProfileController;
use App\Http\Controllers\DorationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GiftDonationController;
use App\Http\Controllers\LoyaltyPointsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RecurringDonationController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\SponsorshipController;
use App\Http\Controllers\SponsorshipManagementController;
use App\Http\Controllers\TypeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\VolunteerTaskController;
use App\Http\Controllers\VolunterProfileController as ControllersVolunterProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;




Route::prefix('auth')->group(function () {
    // PUBLIC ROUTES
    Route::post('/register', [UserController::class, 'register']);
    Route::post('/login', [UserController::class, 'login']);
    Route::post('/forgot-password', [UserController::class, 'forgotPassword']);
    Route::post('/reset-password', [UserController::class, 'resetPassword']);
    Route::post('/resend-otp', [UserController::class, 'resendOtp']);


    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/verify-otp', [UserController::class, 'verifyOtp']);
        Route::post('/select-role', [UserController::class, 'selectRole']);
        Route::put('/change-password', [UserController::class, 'changePassword']);
        Route::post('/logout', [UserController::class, 'logout']);
    });
});


  Route::post('/auth/dashboard/login', [EmployeeController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/dashboard/logout', [EmployeeController::class, 'logout']);
});


Route::middleware('auth:sanctum')->group(function () {

    // ==================== BENEFICIARY ROUTES ====================
    Route::prefix('beneficiary')->group(function () {
        Route::post('/complete-profile', [BeneficiaryProfileController::class, 'completeProfile']); //!اكمال الملف الشخصي
        Route::get('/profile', [BeneficiaryProfileController::class, 'getProfile']); //!جلب الملف الشخصي
        Route::put('/profile', [BeneficiaryProfileController::class, 'updateProfile']); //!تحديث الملف الشخصي

        // Aid Applications
        Route::get('/aid-applications/statistics', [AidApplicationController::class, 'statistics']); //!احصائيات طلبات المساعدة
        Route::get('/aid-applications/types', [AidApplicationController::class, 'types']); //!جلب أنواع المساعدة
        Route::get('/aid-applications/statuses', [AidApplicationController::class, 'statuses']); //!انواع حالات الطلبات
        Route::post('/aid-applications', [AidApplicationController::class, 'store']); //!انشاء طلب مساعدة
        Route::get('/aid-applications/{id}', [AidApplicationController::class, 'show']); //!اظهار تفاصيل طلب
        Route::put('/aid-applications/{id}', [AidApplicationController::class, 'update']); //!تعديل حالة طلب معين
        Route::delete('/aid-applications/{id}', [AidApplicationController::class, 'destroy']); //!حذف طلب معين
        Route::get('/aid-applications', [AidApplicationController::class, 'index']); //!ارجاع الطلبات مع تصفية حسب الطلب

        /* للادمن
        Route::get('/aid-applications-admin', [AidApplicationController::class, 'adminIndex']); //!ارجاع
        Route::put('/aid-applications/{id}/status', [AidApplicationController::class, 'updateStatus']); //! تغيير حالة الطلب
        */

        Route::prefix('visits')->group(function () {
            Route::get('/statistics', [VisitController::class, 'statistics']); //!جلب إحصائيات الزيارات
            Route::get('/', [VisitController::class, 'index']); //!جلب جميع الزيارات مع تصفية حسب الطلب
            Route::post('/', [VisitController::class, 'store']); //!انشاء زيارة جديدة
            Route::get('/{id}', [VisitController::class, 'show']); //!اظهار تفاصيل زيارة معينة
            Route::put('/{id}', [VisitController::class, 'update']); //!تحديث زيارة
            Route::delete('/{id}', [VisitController::class, 'destroy']); //!حذف زيارة
        });

        Route::get('/cities', [CityController::class, 'index']);
        Route::get('/type-needs', [TypeController::class, 'index']);
    });

    // ==================== DONOR ROUTES ====================
    Route::prefix('donor')->group(function () {

        // Profile
        Route::post('/complete-profile', [DonorProfileController::class, 'completeProfile']); //!اكمال الملف الشخصي
        Route::get('/getprofile', [DonorProfileController::class, 'getProfile']); //!جلب الملف الشخصي
        Route::put('/updateprofile', [DonorProfileController::class, 'updateProfile']); //!تحديث الملف الشخصي

        // Campaigns
        Route::get('/campaigns', [CampaignController::class, 'index']); //!اظهار الحملات بعد التصفية الي بدي ياها
        Route::get('/campaigns/featured', [CampaignController::class, 'featured']); //!برجع الحملات الطارئة اولا ثم الغير طارئة
        Route::get('/campaigns/categories', [CampaignController::class, 'categories']); //!اطهار تصنيفات حملة
        Route::get('/campaigns/{id}', [CampaignController::class, 'show']); //!اطهار حملة
        Route::get('/campaigns/{id}/updates', [CampaignController::class, 'updates']); //!جلب جميع أخبار/تطورات حملة معينة
        Route::post('/storeCampaigns', [CampaignController::class, 'store']); //!انشاء حملة (هاد من عندي لاختبار الشغل مافي داعي ينربط)

        // Donations (Local)
        Route::post('/donations', [DonationController::class, 'store']); //!انشاء تبرع
        Route::get('/donationsHitory', [DonationController::class, 'history']); //!جلب جميع تبرعات المستخدم مع تفاصيل الحملة
        Route::get('/donations/statistics', [DonationController::class, 'statistics']); //!جلب إحصائيات التبرعات
        Route::get('/donations/{id}/receipt', [DonationController::class, 'receipt']); //!جلب إيصال التبرع
        Route::get('/donations/{id}', [DonationController::class, 'show']); //!جلب تفاصيل تبرع معين
        Route::get('/donations/{id}/pdf', [DonationController::class, 'downloadReceiptPdf']); //!تحميل إيصال التبرع بصيغة PDF

        Route::post('/donations/stripe/free', [DonationController::class, 'createFreeStripePayment']);
        Route::post('/donations/stripe/gift', [DonationController::class, 'createGiftStripePayment']);
        Route::post('/donations/stripe/recurring', [DonationController::class, 'createRecurringStripePayment']);
        // ✅ PayerURL Routes (جديدة)
        // Route::post('/donations/payerurl', [DonationController::class, 'createPayerurlPayment']);
        // Route::get('/donations/{id}/qr', [DonationController::class, 'getDonationQR']);

        // ✅ Check Payment Status (بدلاً من صفحات Redirect)
        Route::get('/payments/{donation}/status', [DonationController::class, 'checkPaymentStatus']); //! ليس للربط

        // Recurring Donations
        Route::post('/recurring', [RecurringDonationController::class, 'subscribe']); //!انشاء تبرع متكرر
        Route::get('/recurring', [RecurringDonationController::class, 'index']); //!جلب جميع التبرعات المتكررة للمستخدم
        Route::get('/recurring/{id}', [RecurringDonationController::class, 'show']); //!جلب تفاصيل تبرع متكرر معين
        Route::delete('/recurring/{id}', [RecurringDonationController::class, 'cancel']); //!إلغاء تبرع متكرر معين

        // Donation Cart
        Route::get('/cart', [DonationCartController::class, 'index']); //!جلب عناصر السلة
        Route::post('/cart', [DonationCartController::class, 'add']); //!اضافة عنصر للسلة
        Route::put('/cart/{id}', [DonationCartController::class, 'update']); //!تحديث عنصر في السلة
        Route::delete('/cart/{id}', [DonationCartController::class, 'remove']); //!حذف عنصر من السلة
        Route::delete('/cart', [DonationCartController::class, 'clear']); //!تفريغ السلة
        Route::post('/cart/checkout', [DonationCartController::class, 'checkout']); //!إتمام عملية الشراء (التحويل إلى تبرعات فعلية)

        // Loyalty Points
        Route::get('/loyalty', [LoyaltyPointsController::class, 'index']); //!جلب نقاط الولاء للمستخدم
        Route::get('/loyalty/leaderboard', [LoyaltyPointsController::class, 'leaderboard']); //!جلب قائمة المتبرعين الأعلى تبرعاً
        Route::get('/loyalty/rank', [LoyaltyPointsController::class, 'rank']); //!جلب ترتيب المستخدم بين المتبرعين

        // Gift Donations
        Route::post('/gift', [GiftDonationController::class, 'store']); //!تحويل تبرع إلى هدية
        Route::get('/gift', [GiftDonationController::class, 'index']); //!جلب جميع الهدايا للمستخدم
        Route::get('/gift/{id}', [GiftDonationController::class, 'show']); //!جلب تفاصيل هدية معينة

        // Refunds
        Route::post('/refunds', [RefundController::class, 'requestRefund']); //!طلب استرداد
        Route::get('/refunds', [RefundController::class, 'myRequests']); //!جلب طلبات الاسترداد الخاصة بي
        Route::get('/refunds/{id}', [RefundController::class, 'status']); //!جلب حالة طلب استرداد معين

        // Helpers
        Route::get('/cities', [CityController::class, 'index']);

        // Stripe
        Route::post('/donations/stripe', [DonationController::class, 'createStripePayment']); //!دفع عبر stripe
        // Route::post('/donations/stripe/confirm', [DonationController::class, 'confirmStripePayment']);
    });
     // ==================== VOLUNTEER ROUTES ====================
      Route::prefix('volunteer')->group(function () {
        Route::post('/complete-profile', [ControllersVolunterProfileController::class, 'completeProfile']); //!اكمال الملف الشخصي
        Route::get('/profile', [ControllersVolunterProfileController::class, 'getVolunteers']);
         Route::put('/profile/{id}', [ControllersVolunterProfileController::class, 'update']); //!جلب الملف الشخصي
        Route::get('/statistics', [VolunteerTaskController::class, 'statistics']); //!جلب إحصائيات المتطوع

Route::prefix('tasks')->group(function () {
            Route::get('/available', [VolunteerTaskController::class, 'availableTasks']);//!جلب جميع المهام المتاحة للمتطوع
            Route::get('/', [VolunteerTaskController::class, 'index']); //!جلب جميع المهام مع تصفية حسب الطلب
            Route::get('/current', [VolunteerTaskController::class, 'currentTask']); //!جلب المهمة الحالية للمتطوع
            Route::get('/pending-requests', [VolunteerTaskController::class, 'pendingRequests']);//!جلب جميع الطلبات المعلقة للمتطوع
            Route::get('/{id}', [VolunteerTaskController::class, 'show']);         //!جلب تفاصيل مهمة معينة
            Route::post('/{id}/request-start', [VolunteerTaskController::class, 'requestStartTask']); //!بدء مهمة معينة
            Route::post('/{id}/request-end', [VolunteerTaskController::class, 'requestEndTask']); //!إنهاء مهمة معينة

        });
        // ✅ التقييمات (جديد)
        Route::get('/evaluations', [VolunteerTaskController::class, 'evaluations']); //!جلب جميع التقييمات للمتطوع

        // ✅ الشهادات (جديد)
        Route::get('/certificates', [CertificateController::class, 'index']);  //!جلب جميع الشهادات للمتطوع
        Route::get('/points', [VolunteerTaskController::class, 'points']); //!
        Route::get('/volunteers-list', [ControllersVolunterProfileController::class, 'getVolunteersList']);
        Route::get('/leaderboard', [VolunteerTaskController::class, 'leaderboard']);

        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/cities', [CityController::class, 'index']);
        Route::get('/domains', [DomainController::class, 'index']);
        Route::get('/days', [DayController::class, 'index']);
        Route::get('/skills', [SkillController::class, 'index']);
    });

    // ==================== SPONSORSHIPS ROUTES ====================
    Route::prefix('sponsorships')->middleware('auth:sanctum')->group(function () {
        // إحصائيات الكفالات
        Route::get('/statistics', [SponsorshipController::class, 'statistics']); //!احصائيات الكفالات

        // المستفيدين المتاحين (للمتبرعين)
        Route::get('/available-beneficiaries', [SponsorshipController::class, 'availableBeneficiaries']); //!جلب المستفيدين المتاحين للكفالة

        Route::get('/', [SponsorshipController::class, 'index']); //!جلب الكفالات
        Route::post('/', [SponsorshipController::class, 'store']); //!انشاء كفالة
        Route::get('/{id}', [SponsorshipController::class, 'show']); //!عرض الكفالات
        Route::put('/{id}', [SponsorshipController::class, 'update']); //!تعديل الكفالة والادمن يمكنه تغيير حالة الكفالة
        Route::delete('/{id}', [SponsorshipController::class, 'destroy']); //!حذف كفالة

        Route::get('/{id}/payments', [SponsorshipController::class, 'payments']); //!جلب دفعات الكفالة
        Route::post('/{id}/payments', [SponsorshipController::class, 'addPayment']); //!إضافة دفعة للكفالة

        Route::get('/{id}/messages', [SponsorshipController::class, 'messages']); //!ارجاع الرسائل بين المستفيد والكافل
        Route::post('/{id}/messages', [SponsorshipController::class, 'sendMessage']); //!تبادل الرسائل بين الكافل والمستفيد
    });

    // ==================== NOTIFICATIONS ROUTES ====================
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/', [NotificationController::class, 'deleteAll']);
        Route::get('/preferences', [NotificationController::class, 'preferences']);
        Route::put('/preferences', [NotificationController::class, 'updatePreferences']);

        // ✅ Firebase Routes
        Route::post('/register-token', [NotificationController::class, 'registerToken']);
        Route::post('/remove-token', [NotificationController::class, 'removeToken']);
        Route::post('/test-push', [NotificationController::class, 'testPush']);
    });

    // ==================== CAMPAIGN ROUTES ====================
    //! للويب
Route::controller(CampaignController::class)->group(function () {
    Route::get('/campaigns/getAll', 'getAll');
    Route::post('/campaigns/store', 'store');
    Route::get('/campaigns/show/{id}', 'showCampaign');
    Route::put('/campaigns/update/{id}', 'update');
    Route::delete('/campaigns/delete/{id}', 'destroy');
});
});
Route::prefix('reports')->group(function () {
Route::get('/general', [ReportController::class, 'general']);
Route::get('/donations', [ReportController::class, 'donations']);
Route::get('/beneficiaries', [ReportController::class, 'beneficiaries']);
Route::get('/volunteers', [ReportController::class, 'volunteers']);
});







// ==================== CHAT ROUTES ====================
Route::prefix('chat')->middleware('auth:sanctum')->group(function () {
    Route::get('/conversations', [ChatController::class, 'conversations']); //!ارجاع المحادثات الموجودة
    Route::post('/conversations', [ChatController::class, 'createConversation']); //!انشاء دردشة فردية او غروب
    Route::post('/conversations/{id}/leave', [ChatController::class, 'leaveConversation']); //! مغادرة المحادثة
    Route::post('/conversations/{id}/read', [ChatController::class, 'markAsRead']); //! جعل المحادثة مقروءة
    Route::post('/conversations/{id}/typing', [ChatController::class, 'typing']); //! اظهار اشارة يكتب
    Route::get('/conversations/{id}/messages', [ChatController::class, 'messages']); //!ارجاع الرسائل في المحادثة
    Route::post('/conversations/{id}/messages', [ChatController::class, 'sendMessage']); //!ارسال رسالة في المحادثة
});

Route::prefix('admin')->group(function () {
Route::get('/notifications', [NotificationController::class, 'getAll']);
Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
Route::delete('/notifications/{id}', [NotificationController::class, 'delete']);
});



// ==================== BENEFICIARIES ROUTES (Admin) ====================
Route::get('/beneficiaries', [BeneficiaryProfileController::class, 'index']);
Route::get('/beneficiaries/{id}', [BeneficiaryProfileController::class, 'show']);
Route::post('/beneficiaries', [BeneficiaryProfileController::class, 'store']);
Route::patch('/beneficiaries/{id}/status', [BeneficiaryProfileController::class, 'updateStatus']);
Route::delete('/beneficiaries/{id}', [BeneficiaryProfileController::class, 'destroy']);

// ==================== DONATIONS ROUTES (Admin/Public) ====================
Route::post('/donations', [DorationController::class, 'store']);
Route::get('/donations', [DorationController::class, 'index']);
Route::get('/donations/{id}', [DorationController::class, 'show']);
Route::delete('/donations/{id}', [DorationController::class, 'destroy']);
Route::put('/donations/{id}', [DorationController::class, 'update']);


Route::get('/volunteers', [ControllersVolunterProfileController::class,'getVolunteers']);
Route::post('/volunteers', [ControllersVolunterProfileController::class, 'store']);
Route::delete('/volunteers/{id}', [ControllersVolunterProfileController::class, 'destroy']);
Route::patch('/volunteers/{id}/status', [ControllersVolunterProfileController::class, 'updateStatus']);
Route::get('/volunteers/{id}', [ControllersVolunterProfileController::class, 'show']);



Route::post('/volunteer-tasks', [VolunteerTaskController::class, 'store']);
Route::post('/volunteer-tasks/{task}/assign', [VolunteerTaskController::class, 'assign']);
Route::get('volunteer-tasks/pending-evaluation', [VolunteerTaskController::class, 'pendingEvaluation']);
Route::post('volunteer-tasks/{id}/evaluate', [VolunteerTaskController::class, 'evaluate']);


Route::post('/admin/volunteer-tasks/{id}/review-start', [VolunteerTaskController::class, 'reviewStartRequest']);
Route::post('/admin/volunteer-tasks/{id}/review-end', [VolunteerTaskController::class, 'reviewEndRequest']);
Route::get('/admin/volunteer-tasks/pending-approvals', [VolunteerTaskController::class, 'pendingApprovals']);
Route::post('/{id}/start', [VolunteerTaskController::class, 'startTask']); //!بدء مهمة معينة
 Route::post('/{id}/end', [VolunteerTaskController::class, 'endTask']);
Route::post('/volunteer-tasks/{id}/complete', [VolunteerTaskController::class, 'completeTask']);
     // المهام العامة
    Route::get('/volunteer-tasks/all',[VolunteerTaskController::class, 'getAllTasks']); // 👈 جلب كل المهام

    // مهام متطوع محدد
    Route::get('/volunteers/{id}/tasks', [VolunteerTaskController::class,'getVolunteerTasks']); // 👈 جلب مهام متطوع بالـ ID

Route::prefix('aid-applications')->group(function () {
    Route::get('/', [AidApplicationController::class, 'getAll']);
    Route::get('/{id}', [AidApplicationController::class, 'display']);
    Route::patch('/{id}/status', [AidApplicationController::class, 'updateStory']);
});
// راوت تحديث الحالة (للأدمن)
Route::put('/all-visits/{id}/status', [VisitController::class, 'updateVisitStatusAdmin']);
Route::get('/all-visits', [VisitController::class, 'getAllVisits']);
// راوتات الحذف (للأدمن)
Route::delete('/all-visits/delete-all', [VisitController::class, 'deleteAllVisits']); // حذف الكل
Route::delete('/all-visits/{id}', [VisitController::class, 'destroy2']); // حذف زيارة واحدة
// راوت إضافة زيارة من لوحة التحكم
Route::post('/all-visits', [VisitController::class, 'storeAdminVisit']);
Route::post('/volunteer-tasks/{id}/complete', [VolunteerTaskController::class, 'completeTaskForAdmin']);

// ==================== EXPORT DONATIONS ====================
Route::get('/users/exportDonations', [DorationController::class, 'export']);

// ==================== DOWNLOAD DONATION FILE ====================
Route::get('/download-donation', function () {
    $disk = Storage::disk('public');
    $filename = 'Donation.xlsx';

    if ($disk->exists($filename)) {
        $path = $disk->path($filename);
        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
    }

    return response()->json(['message' => 'الملف غير موجود في السيرفر'], 404);
});

Route::prefix('admin/sponsorships')->group(function () {
    Route::get('', [SponsorshipManagementController::class, 'index']);
    Route::post('store', [SponsorshipManagementController::class, 'store']); // تم تغييرها إلى POST
    Route::get('dashboard', [SponsorshipManagementController::class, 'dashboard']);

    // راوتات الحذف (مهم: delete-all يجب أن تسبق {id})
    Route::delete('delete-all', [SponsorshipManagementController::class, 'deleteAll']);
    Route::delete('{id}', [SponsorshipManagementController::class, 'destroy']);

    Route::get('{id}/show', [SponsorshipManagementController::class, 'show']);
    Route::post('{id}/approve', [SponsorshipManagementController::class, 'approve']);
    Route::post('{id}/reject', [SponsorshipManagementController::class, 'reject']);
    Route::post('{id}/suspend', [SponsorshipManagementController::class, 'suspend']);
    Route::post('{id}/resume', [SponsorshipManagementController::class, 'resume']);
    Route::patch('{id}/notes', [SponsorshipManagementController::class, 'updateNotes']);
});

Route::get('donationsForAdmin',[DonationController::class,'indexForAdmin']);
// ==================== SHARED ROUTES (Public) ====================
Route::get('/cities', [CityController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);

// ==================== STRIPE WEBHOOK ====================
Route::post('/stripe/webhook', [DonationController::class, 'handleStripeWebhook']) //!ليس للربط
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

// ==================== PAYERURL WEBHOOK (No Auth, No CSRF) ====================
/*Route::post('/payerurl/webhook', [DonationController::class, 'handlePayerurlWebhook'])
    ->name('payerurl.webhook')
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);*/

Route::get('reports/dashboard-stats',[ReportController::class,'dasgboardStats']);
Route::get('/campaigns/show/{id}', [CampaignController::class, 'showCampaignForWebsite']);




    // ✅ حل سحري لـ FrankenPHP: توجيه طلبات الـ Rewrite الإجبارية إلى دالة التسجيل مباشرة


    Route::get('/campaigns/getAllForWebsite', [CampaignController::class,'getAllForWebsite']);



Route::post('/index.php', [App\Http\Controllers\UserController::class, 'register']);
