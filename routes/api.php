<?php
use App\Http\Controllers\DonorProfileController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\DonationCartController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\GiftDonationController;
use App\Http\Controllers\LoyaltyPointsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RecurringDonationController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\BeneficiaryProfileController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DayController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\TypeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VolunterProfileController as ControllersVolunterProfileController;
use App\Models\Donation;

Route::prefix('auth')->group(function () {
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

// ==================== PROTECTED ROUTES ====================
Route::middleware('auth:sanctum')->group(function () {

    // ==================== BENEFICIARY ROUTES ====================
    Route::prefix('beneficiary')->group(function () {
        Route::post('/complete-profile', [BeneficiaryProfileController::class, 'completeProfile']);
        Route::get('/profile', [BeneficiaryProfileController::class, 'getProfile']);
        Route::put('/profile', [BeneficiaryProfileController::class, 'updateProfile']);
        
         //Aid Applications
        // Route::post('/aid-applications', [AidApplicationController::class, 'store']);
        // Route::get('/aid-applications', [AidApplicationController::class, 'index']);
        // Route::get('/aid-applications/{id}', [AidApplicationController::class, 'show']);
        // Route::put('/aid-applications/{id}', [AidApplicationController::class, 'update']);
        
        // Helpers
        Route::get('/cities', [CityController::class, 'index']);
        Route::get('/type-needs', [TypeController::class, 'index']);
    });

    // ==================== DONOR ROUTES ====================
    Route::prefix('donor')->group(function () {
        
        // Profile
        Route::post('/complete-profile', [DonorProfileController::class, 'completeProfile']);//!اكمال الملف الشخصي
        Route::get('/getprofile', [DonorProfileController::class, 'getProfile']);//!جلب الملف الشخصي
       Route::put('/updateprofile', [DonorProfileController::class, 'updateProfile']);//!تحديث الملف الشخصي
        
        // Campaigns
        Route::get('/campaigns', [CampaignController::class, 'index']);//!اظهار الحملات بعد التصفية الي بدي ياها
        Route::get('/campaigns/featured', [CampaignController::class, 'featured']);//!برجع الحملات الطارئة اولا ثم الغير طارئة
        Route::get('/campaigns/categories', [CampaignController::class, 'categories']);//!اطهار تصنيفات حملة 
        Route::get('/campaigns/{id}', [CampaignController::class, 'show']);//!اطهار حملة 
        Route::get('/campaigns/{id}/updates', [CampaignController::class, 'updates']);//!جلب جميع أخبار/تطورات حملة معينة
        Route::post('/storeCampaigns', [CampaignController::class, 'store']); //!انشاء حملة (هاد من عندي لاختبار الشغل مافي داعي ينربط)
        
        // Donations (Local)
        Route::post('/donations', [DonationController::class, 'store']);//!انشاء تبرع
        Route::get('/donationsHitory', [DonationController::class, 'history']);//!جلب جميع تبرعات المستخدم مع تفاصيل الحملة
        Route::get('/donations/statistics', [DonationController::class, 'statistics']);//!جلب إحصائيات التبرعات
        Route::get('/donations/{id}/receipt', [DonationController::class, 'receipt']);//!جلب إيصال التبرع
        Route::get('/donations/{id}', [DonationController::class, 'show']);//!جلب تفاصيل تبرع معين
         Route::get('/donations/{id}/pdf', [DonationController::class, 'downloadReceiptPdf']);//!تحميل إيصال التبرع بصيغة PDF
        
        // ✅ PayerURL Routes (جديدة)
     //   Route::post('/donations/payerurl', [DonationController::class, 'createPayerurlPayment']);
     //   Route::get('/donations/{id}/qr', [DonationController::class, 'getDonationQR']);
        
        // ✅ Check Payment Status (بدلاً من صفحات Redirect)
        Route::get('/payments/{donation}/status', [DonationController::class, 'checkPaymentStatus']);
        
        // Recurring Donations
        Route::post('/recurring', [RecurringDonationController::class, 'subscribe']);//!انشاء تبرع متكرر
        Route::get('/recurring', [RecurringDonationController::class, 'index']);//!جلب جميع التبرعات المتكررة للمستخدم
        Route::get('/recurring/{id}', [RecurringDonationController::class, 'show']);//!جلب تفاصيل تبرع متكرر معين
        Route::delete('/recurring/{id}', [RecurringDonationController::class, 'cancel']);//!إلغاء تبرع متكرر معين
        
        // Donation Cart
        Route::get('/cart', [DonationCartController::class, 'index']);//!جلب عناصر السلة
        Route::post('/cart', [DonationCartController::class, 'add']);//!اضافة عنصر للسلة
        Route::put('/cart/{id}', [DonationCartController::class, 'update']);//!تحديث عنصر في السلة
        Route::delete('/cart/{id}', [DonationCartController::class, 'remove']);//!حذف عنصر من السلة
        Route::delete('/cart', [DonationCartController::class, 'clear']);//!تفريغ السلة
        Route::post('/cart/checkout', [DonationCartController::class, 'checkout']);//!إتمام عملية الشراء (التحويل إلى تبرعات فعلية)
        
        // Loyalty Points
        Route::get('/loyalty', [LoyaltyPointsController::class, 'index']);//!جلب نقاط الولاء للمستخدم
        Route::get('/loyalty/leaderboard', [LoyaltyPointsController::class, 'leaderboard']);//!جلب قائمة المتبرعين الأعلى تبرعاً
        Route::get('/loyalty/rank', [LoyaltyPointsController::class, 'rank']);//!جلب ترتيب المستخدم بين المتبرعين
        
        // Gift Donations
        Route::post('/gift', [GiftDonationController::class, 'store']);//!تحويل تبرع إلى هدية
        Route::get('/gift', [GiftDonationController::class, 'index']);//!جلب جميع الهدايا للمستخدم
        Route::get('/gift/{id}', [GiftDonationController::class, 'show']);//!جلب تفاصيل هدية معينة
        
        // Refunds
        Route::post('/refunds', [RefundController::class, 'requestRefund']);//!طلب استرداد
        Route::get('/refunds', [RefundController::class, 'myRequests']);//!جلب طلبات الاسترداد الخاصة بي
        Route::get('/refunds/{id}', [RefundController::class, 'status']);//!جلب حالة طلب استرداد معين
        
        // Helpers
        Route::get('/cities', [CityController::class, 'index']);


         Route::post('/donations/stripe', [DonationController::class, 'createStripePayment']);
    Route::post('/donations/stripe/confirm', [DonationController::class, 'confirmStripePayment']);
    });

    // ==================== VOLUNTEER ROUTES ====================
    Route::prefix('volunteer')->group(function () {
        Route::post('/complete-profile', [ControllersVolunterProfileController::class, 'completeProfile']);
        Route::get('/profile', [ControllersVolunterProfileController::class, 'getProfile']);
        Route::put('/profile', [ControllersVolunterProfileController::class, 'updateProfile']);
        
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/cities', [CityController::class, 'index']);
        Route::get('/domains', [DomainController::class, 'index']);
        Route::get('/days', [DayController::class, 'index']);
        Route::get('/skills', [SkillController::class, 'index']);
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
});

// ==================== SHARED ROUTES (Public) ====================
Route::get('/cities', [CityController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);

// ==================== PAYERURL WEBHOOK (No Auth, No CSRF) ====================
/*Route::post('/payerurl/webhook', [DonationController::class, 'handlePayerurlWebhook'])
    ->name('payerurl.webhook')
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);*/


    