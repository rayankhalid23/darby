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
        
        // جدولة غياب طفل في تواريخ معينة
        Route::post('set-absence', [ParentChildController::class, 'setAbsence']);
        
        // إلغاء غياب مجدول مسبقاً للطفل
        Route::post('cancel-absence', [ParentChildController::class, 'cancelAbsence']);
        
        // التأكيد اليدوي لصعود الطفل (بديل الـ QR في حال تعطل هاتف السائق أو كاميرته)
        Route::post('confirm-pickup/{tripId}', [ParentChildController::class, 'confirmManualPickup']);
        
    });
    
    // إدارة الأبناء والطلبة المضافين
    Route::prefix('children')->group(function () {
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
        Route::post('/{id}', [DriverReviewController::class, 'update']);
        Route::delete('/{id}', [DriverReviewController::class, 'destroy']);
    });

    // مسارات الشكاوى لولي الأمر
    Route::prefix('complaints')->group(function () {
        Route::get('/', [ComplaintController::class, 'index']);
        Route::get('/{id}', [ComplaintController::class, 'show']);
        Route::post('/', [ComplaintController::class, 'store']);
        Route::post('/{id}', [ComplaintController::class, 'update']);
        Route::delete('/{id}', [ComplaintController::class, 'destroy']);
        Route::get('/driver/{driverId}/trips', [ComplaintController::class, 'driverTrips']);
    });

});