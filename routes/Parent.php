<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Parent\ParentAuthController;
use App\Http\Controllers\Api\Parent\ChildrenController;
use App\Http\Controllers\Api\Parent\AddressController;
use App\Http\Controllers\Api\Parent\ParentSchoolController;
use App\Http\Controllers\Api\Admin\ZoneController;
use App\Http\Controllers\Api\Parent\DriverSearchController;
use App\Http\Controllers\Api\Admin\SchoolController;
use App\Http\Controllers\API\Trip\ParentChildController;
use App\Http\Controllers\Api\Parent\DriverReviewController;
use App\Http\Controllers\Api\Parent\ComplaintController;
use App\Http\Controllers\Api\Parent\WalletController;
use App\Http\Controllers\Api\Shared\InvoiceController;
use App\Http\Controllers\API\Parent\ParentSubscriptionController;

use App\Http\Controllers\Api\Trip\ParentTripController;


/*
|--------------------------------------------------------------------------
| Parent Module Routes
|--------------------------------------------------------------------------
| ملاحظة هندسية: هذا الملف ممرر عبر الـ Prefix التلقائي (api/parent) 
| من خلال ملف الإعدادات الأساسي bootstrap/app.php. لا تكرر الكلمة هنا.
*/

// 1. مسارات المصادقة العامة والتحقق (بدون توكن)
Route::post('/send-otp', [ParentAuthController::class, 'sendOtp']);
Route::post('/register', [ParentAuthController::class, 'register']);

/*
 * روابط موقّعة (Signed URLs) لتأكيد أو رفض تغيير البريد الإلكتروني لولي الأمر.
 * تفتح مباشرة من المتصفح محمية بالـ Middleware 'signed'
 */
Route::middleware('signed')->group(function () {
    Route::get('/profile/email/approve/{id}', [ParentAuthController::class, 'approveEmailChange'])
        ->name('parent.profile.email.approve');
        
    Route::get('/profile/email/reject/{id}', [ParentAuthController::class, 'rejectEmailChange'])
        ->name('parent.profile.email.reject');
});


// 2. مسارات ولي الأمر المحمية (تتطلب تسجيل دخول وتوكن Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    
    // إدارة الحساب الشخصي لولي الأمر
    Route::get('/profile', [ParentAuthController::class, 'getProfile']);
    Route::post('/profile/update', [ParentAuthController::class, 'updateProfile']); 
    
    // مسار جلب المدارس المعتمدة (الآن أصبح: api/parent/schools فوراً وبشكل صحيح)
    Route::get('schools', [SchoolController::class, 'index']);

    Route::prefix('children/{childId}')->group(function () {
        
        // تسجيل غياب طفل مع تحديد نوع الغياب (pickup/dropoff/both)
        Route::post('set-absence', [ParentChildController::class, 'setAbsence']);
        
        // إلغاء غياب مجدول مسبقاً للطفل (يمكن إلغاء نوع معين أو كل الغياب في الأيام المحددة)
        Route::post('cancel-absence', [ParentChildController::class, 'cancelAbsence']);
        
        // عرض قائمة الغيابات المسجلة للطفل (المستقبلية)
        Route::get('absences', [ParentChildController::class, 'getAbsences']);
        
        // جلب الأيام المتاحة لتسجيل الغياب بناءً على الاشتراك الفعال
        Route::get('available-absence-dates', [ParentChildController::class, 'getAvailableAbsenceDates']);
        
        // التأكيد اليدوي لصعود الطفل (بديل الـ QR في حال تعطل هاتف السائق أو كاميرته)
        Route::post('confirm-pickup/{tripId}', [ParentChildController::class, 'confirmManualPickup']);
        
    });
    
    // إدارة الأبناء والطلبة المضافين
    Route::prefix('children')->group(function () {
        Route::get('/has-children', [ChildrenController::class, 'checkHasChildren']);
        Route::get('/active-subscribed', [ChildrenController::class, 'getActiveSubscribedChildren']);
        Route::get('/', [ChildrenController::class, 'index']);
        Route::post('/', [ChildrenController::class, 'store']);
        Route::get('/{id}', [ChildrenController::class, 'show']);
        Route::post('/{id}', [ChildrenController::class, 'update']);
        Route::delete('/{id}', [ChildrenController::class, 'destroy']);
        Route::get('/{id}/subscription', [ChildrenController::class, 'getSubscription']);
    });

    // مسارات إدارة العناوين والمواقع الجغرافية لولي الأمر
    Route::prefix('addresses')->group(function () {
        Route::get('/', [AddressController::class, 'index']);
        Route::post('/', [AddressController::class, 'store']);
        Route::post('/{address}', [AddressController::class, 'update'])->withTrashed(); 
        Route::delete('/{address}', [AddressController::class, 'destroy'])->withTrashed(); 
    });
    
    // اقتراح مدرسة جديدة من قبل ولي الأمر
    Route::post('/suggest-school', [ParentSchoolController::class, 'store']);
    
    // جلب المناطق المتاحة بالنظام لولي الأمر
    Route::get('zones', [ZoneController::class, 'index']);

    // مسار بحث وفلترة السائقين المتقدم لولي الأمر
    Route::post('/drivers/search', [DriverSearchController::class, 'search']);

    // مسارات تقييم السائقين لولي الأمر
    Route::prefix('driver-reviews')->group(function () {
        Route::get('/driver/{driverId}', [DriverReviewController::class, 'index']);
        Route::post('/', [DriverReviewController::class, 'store']);
        Route::put('/{id}', [DriverReviewController::class, 'update']);
        Route::delete('/{id}', [DriverReviewController::class, 'destroy']);
    });

    // مسارات الشكاوى لولي الأمر
    Route::prefix('complaints')->group(function () {
        Route::get('/', [ComplaintController::class, 'index']);
        Route::get('/{id}', [ComplaintController::class, 'show']);
        Route::post('/', [ComplaintController::class, 'store']);
        Route::post('/{id}', [ComplaintController::class, 'update']);
        Route::delete('/{id}', [ComplaintController::class, 'destroy']);
        
    });
    Route::get('/driver/{driverId}/trips', [ComplaintController::class, 'driverTrips']);

    // مسارات المحفظة المالية والشحن
    Route::prefix('wallet')->group(function () {
        Route::get('/balance', [WalletController::class, 'balance']);
        Route::get('/payment-methods', [WalletController::class, 'paymentMethods']);
        Route::post('/recharge', [WalletController::class, 'recharge']);
    });

    // مسارات الفواتير
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/{id}', [InvoiceController::class, 'show']);
    });

    // التحقق من وجود اشتراك لولي الأمر مع سائق معين ?driver_id=
    Route::get('/subscriptions/check', [ParentSubscriptionController::class, 'checkSubscription']);

    // 1. شاشات العرض والتتبع (الجديدة)
    Route::get('/trips/active', [ParentTripController::class, 'getActiveTrips']);
    Route::get('/trips/{tripId}/track', [ParentTripController::class, 'getLiveTracking']);
    Route::get('/trips/upcoming', [ParentTripController::class, 'getUpcomingTrips']);
    Route::get('/trips/history', [ParentTripController::class, 'getTripHistory']);

    // 2. العمليات والإجراءات (الموجودة سابقاً مع الإضافات الجديدة)
    Route::post('/children/{childId}/absence', [ParentChildController::class, 'setAbsence']);
    Route::delete('/children/{childId}/absence', [ParentChildController::class, 'cancelAbsence']);
    Route::get('/children/{childId}/absences', [ParentChildController::class, 'getAbsences']);
    Route::get('/children/{childId}/available-absence-dates', [ParentChildController::class, 'getAvailableAbsenceDates']);
    Route::post('/trips/{tripId}/children/{childId}/manual-pickup', [ParentChildController::class, 'confirmManualPickup']);

});