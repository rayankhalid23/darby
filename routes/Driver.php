<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Driver\DriverRegisterController;
use App\Http\Controllers\Api\Driver\ProfileController;
use App\Http\Controllers\Api\Driver\DriverPreferenceController;
use App\Http\Controllers\Api\Driver\AddressController;
use App\Http\Controllers\Api\Driver\ZoneController; 
use App\Http\Controllers\API\Trip\DriverTripController;
use App\Http\Controllers\Api\DriverTrackingController;

// أو إذا كان داخل مجلد فرعي:
 use App\Http\Controllers\API\Driver\DriverProfileController;
/*
|--------------------------------------------------------------------------
| Driver Routes (تم إزالة الـ prefix التكراري ليطابق v1/driver مباشرة)
|--------------------------------------------------------------------------
*/

// --- 🔓 مسارات مفتوحة: لا تتطلب تسجيل دخول مسبق ---

    
    // 1. طلب إرسال كود الـ OTP إلى بريد السائق
    Route::post('register', [DriverRegisterController::class, 'registerAccount'])
        ->name('api.driver.register.account');

    // 2. التحقق من كود الـ OTP وإنشاء الحساب وتوليد التوكن فوراً
    Route::post('verify-otp', [DriverRegisterController::class, 'verifyOtp'])
        ->name('api.driver.verify-otp');

    // روابط تأكيد وإلغاء البريد الإلكتروني (تعتمد على التوقيع الرقمي المحمي Signed URL)
    Route::get('email/approve/{id}', [ProfileController::class, 'approveEmailChange'])
        ->name('api.driver.email.approve')
        ->middleware('signed');

    Route::get('email/reject/{id}', [ProfileController::class, 'rejectEmailChange'])
        ->name('api.driver.email.reject')
        ->middleware('signed');


// --- 🔒 مسارات محمية: تتطلب توكن Sanctum ---
Route::middleware('auth:sanctum')->group(function () {
    
    // 3. المرحلة الثانية من التسجيل: رفع الوثائق وبيانات المركبة لإكمال الملف الشخصي
    Route::post('complete-profile/{userId}', [DriverRegisterController::class, 'completeProfile'])
        ->name('api.driver.complete-profile');

    // 4. إلغاء وحذف الحساب غير المكتمل بعد التحقق من الـ OTP (DELETE أو POST)
    Route::delete('abandon-registration', [DriverRegisterController::class, 'abandonRegistration'])
        ->name('api.driver.abandon-registration');
    Route::post('abandon-registration', [DriverRegisterController::class, 'abandonRegistration']);
    Route::delete('cancel-registration', [DriverRegisterController::class, 'abandonRegistration']);
    Route::post('cancel-registration', [DriverRegisterController::class, 'abandonRegistration'])
        ->name('api.driver.cancel-registration');

    // موديول العناوين (Addresses Module)
    Route::prefix('addresses')->group(function () {
        
        Route::get('/', [AddressController::class, 'index']);          // عرض كل العناوين
        Route::post('/', [AddressController::class, 'store']);         // إضافة عنوان جديد
        Route::put('/{address}', [AddressController::class, 'update']); // تعديل عنوان (ملاحظة: استخدمنا PUT ومطابق للاسم هندسياً)
        Route::delete('/{address}', [AddressController::class, 'destroy']); // حذف العنوان ناعماً

    });   
    

    
    Route::prefix('trips')->group(function () {
        Route::get('today', [DriverTripController::class, 'todayTrips']);
        Route::get('history', [DriverTripController::class, 'history']);
        Route::get('history/{tripId}', [DriverTripController::class, 'historyDetails']);
        Route::get('{tripId}', [DriverTripController::class, 'show']);
        Route::post('{tripId}/start', [DriverTripController::class, 'start']);
        Route::post('start', [DriverTripController::class, 'start']);
        Route::get('{tripId}/live', [DriverTripController::class, 'live']);
        Route::get('{tripId}/stops', [DriverTripController::class, 'tripStops']);
        Route::post('{tripId}/location', [DriverTripController::class, 'updateLocation']);
        Route::post('{tripId}/pickup', [DriverTripController::class, 'pickup']);
        Route::post('{tripId}/absent', [DriverTripController::class, 'absent']);
        Route::post('{tripId}/dropoff', [DriverTripController::class, 'dropoff']);
        Route::post('{tripId}/skip/{childId}', [DriverTripController::class, 'skip']);
        Route::post('{tripId}/verify-qr/{childId}', [DriverTripController::class, 'verifyQr']);
        Route::post('{tripId}/children/{tripChildId}/status', [DriverTripController::class, 'updateChildTripStatus']);
        Route::post('register-absence', [DriverTripController::class, 'registerAbsence']);
        Route::post('{tripId}/report-breakdown', [DriverTripController::class, 'reportBreakdown']);
        Route::post('{tripId}/resume', [DriverTripController::class, 'resumeTrip']);
        Route::post('{tripId}/complete', [DriverTripController::class, 'complete']);
    });

    // عرض بيانات الملف الشخصي للسائق وعلاقاته
    Route::get('profile', [ProfileController::class, 'show'])
        ->name('api.driver.profile.show');

    // عرض حالة اعتماد الحساب فقط (Pending/Approved/Rejected) — لشاشة انتظار المراجعة
    Route::get('status', [ProfileController::class, 'status'])
        ->name('api.driver.status');
    
    // تحديث البيانات الشخصية والمظهر
    Route::match(['post', 'put'], 'preferences', [DriverPreferenceController::class, 'update'])
    ->name('api.driver.preferences.update');
    Route::match(['post', 'put'], 'profile', [ProfileController::class, 'update']);
    Route::match(['post', 'put'], 'profile/update', [ProfileController::class, 'update']);
    Route::get('profile/email-change/status', [ProfileController::class, 'checkEmailChangeStatus']);
    Route::post('profile/email-change/cancel', [ProfileController::class, 'cancelEmailChange']);
    Route::post('profile/email-change/resend', [ProfileController::class, 'resendEmailChange']);

    // تحديث وتجديد المستندات والوثائق الرسمية
    Route::post('profile/legal-data', [ProfileController::class, 'updateLegalData'])
        ->name('api.driver.profile.legal-data');

    // تحديث بيانات وتفاصيل المركبة
    Route::post('profile/vehicle/{vehicle}', [ProfileController::class, 'updateVehicle'])
        ->name('api.driver.profile.vehicle.update');

    // 🗺️ مسارات تفضيلات العمل ومناطق الجغرافيا للسائق
    Route::get('preferences', [DriverPreferenceController::class, 'show'])
        ->name('api.driver.preferences.show');
        
    Route::post('preferences', [DriverPreferenceController::class, 'update'])
        ->name('api.driver.preferences.update');
        
    Route::post('preferences/zones/add', [DriverPreferenceController::class, 'addZone'])
        ->name('api.driver.preferences.zones.add');
        
    Route::post('preferences/zones/remove', [DriverPreferenceController::class, 'removeZone'])
        ->name('api.driver.preferences.zones.remove');
        
    Route::get('preferences/defaults', [DriverPreferenceController::class, 'defaults'])
        ->name('api.driver.preferences.defaults');

    // --- أضف هذه المسارات داخل مجموعة الـ auth:sanctum ---

// 1. مسار لعرض المستندات والوثائق الرسمية الحالية للسائق
Route::get('profile/legal-data', [ProfileController::class, 'showLegalData'])
->name('api.driver.profile.legal-data.show');

// 2. مسار لعرض بيانات المركبة الحالية للسائق
Route::get('profile/vehicle', [ProfileController::class, 'showVehicle'])
->name('api.driver.profile.vehicle.show');    

    // مسار لعرض جميع المناطق المتاحة في النظام للسائق ليتمكن من اختيارها
Route::get('zones', [ZoneController::class, 'index'])
->name('api.driver.zones.index');    

    // مسارات المحفظة المالية والسحب
    Route::prefix('wallet')->group(function () {
        Route::get('/balance', [App\Http\Controllers\Api\Driver\WithdrawalController::class, 'balance']);
    });

    // مسارات السحب
    Route::prefix('withdrawals')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Driver\WithdrawalController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Driver\WithdrawalController::class, 'store']);
    });

    // مسارات الفواتير
    Route::prefix('invoices')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Shared\InvoiceController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\Api\Shared\InvoiceController::class, 'show']);
    });
    // --- مسارات إدارة مركبات السائق ---
    Route::prefix('vehicles')->group(function () {
        Route::get('/', [DriverProfileController::class, 'indexVehicles'])->name('api.driver.vehicles.index');
        Route::get('/{vehicle}', [DriverProfileController::class, 'showVehicle'])->name('api.driver.vehicles.show');
    });

    Route::post('/driver/update-location', [DriverTrackingController::class, 'updateLocation']);

    // -------------------------------------------------------------
    // 🗺️ موديول إدارة المسارات والتزام إسناد الاشتراكات (Routes & Assignments)
    // -------------------------------------------------------------
    Route::get('home-status', [App\Http\Controllers\Api\Driver\DriverRouteController::class, 'checkPendingAssignments']);

    Route::prefix('routes')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Driver\DriverRouteController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Driver\DriverRouteController::class, 'store']);
        Route::get('/{routeId}', [App\Http\Controllers\Api\Driver\DriverRouteController::class, 'show']);
        Route::put('/{routeId}', [App\Http\Controllers\Api\Driver\DriverRouteController::class, 'update']);
        Route::delete('/{routeId}', [App\Http\Controllers\Api\Driver\DriverRouteController::class, 'destroy']);
        Route::put('/{routeId}/reorder', [App\Http\Controllers\Api\Driver\DriverRouteController::class, 'reorderStops']);
        Route::delete('/{routeId}/subscriptions/{subscriptionId}', [App\Http\Controllers\Api\Driver\DriverRouteController::class, 'unassignSubscription']);
    });

    Route::prefix('subscriptions')->group(function () {
        Route::get('/{subscriptionId}/route-recommendations', [App\Http\Controllers\Api\Driver\DriverRouteController::class, 'recommendations']);
        Route::post('/{subscriptionId}/assign-route', [App\Http\Controllers\Api\Driver\DriverRouteController::class, 'assignSubscription']);
        Route::post('/{subscriptionId}/move-route', [App\Http\Controllers\Api\Driver\DriverRouteController::class, 'moveSubscription']);
    });

});