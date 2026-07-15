<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Parent\ParentSubscriptionController;
use App\Http\Controllers\API\Driver\DriverSubscriptionController;
use App\Http\Controllers\API\Shared\ContractController;
use App\Http\Controllers\API\Driver\DriverRouteController;

Route::middleware('auth:sanctum')->group(function () {

    // ============================================================
// مسارات العقود (Shared) - تم الإصلاح هنا
// ============================================================
Route::prefix('contracts')->group(function () {
    // الآن المسار سيكون: /api/contracts/{id}/pdf
    Route::get('/{id}/pdf', [ContractController::class, 'generatePdf']);
    
    // المسار سيكون: /api/contracts/clauses
    Route::get('/clauses', [ContractController::class, 'clauses']);
    
    // المسار سيكون: /api/contracts
    Route::post('/', [ContractController::class, 'store']);
    
    // المسار سيكون: /api/contracts/{id}
    Route::get('/{id}', [ContractController::class, 'show']);
    
    // المسار سيكون: /api/contracts/{id}/accept
    Route::put('/{id}/accept', [ContractController::class, 'accept']);
    
    // المسار سيكون: /api/contracts/{id}/reject
    Route::put('/{id}/reject', [ContractController::class, 'reject']);
});
    // مسارات أولياء الأمور لإرسال واستعراض طلبات الاشتراك
// مسارات أولياء الأمور لإرسال واستعراض طلبات الاشتراك
Route::prefix('parent')->group(function () {
    // عرض جميع طلبات ولي الأمر (أو الصفحة الرئيسية للملف الشخصي)
    Route::get('/', [ParentSubscriptionController::class, 'index']); 
    
    // إرسال طلب اشتراك جديد
    Route::post('/', [ParentSubscriptionController::class, 'store']); 
    
    // عرض الطلبات المعلقة فقط (لتسهيل متابعة الحالة على ولي الأمر)
    Route::get('/requests/pending', [ParentSubscriptionController::class, 'indexPending']); 
    
    // عرض تفاصيل طلب اشتراك محدد
    Route::get('/requests/{id}', [ParentSubscriptionController::class, 'show']); 
   // راوت جلب جميع الاشتراكات
   Route::get('/subscriptions', [ParentSubscriptionController::class, 'index']);
    
   // راوت جلب تفاصيل اشتراك محدد
   Route::get('/subscriptions/{id}', [ParentSubscriptionController::class, 'show']);
});

Route::prefix('driver')->group(function () {
    // 1. مسارات ثابتة أولاً
    Route::get('/', [DriverSubscriptionController::class, 'index']);
    Route::get('/routes', [DriverRouteController::class, 'index']); // تأكد أن هذا المسار في الأعلى
    Route::post('/trips/start', [TripController::class, 'startTrip']);
    
    // 2. مسارات تحتوي على متغيرات (Parameters) أخيراً
    Route::put('/routes/{route}', [DriverRouteController::class, 'update']);
    Route::get('/{id}', [DriverSubscriptionController::class, 'show']); // هذا كان يسبب التعارض
    Route::put('{id}/status', [DriverSubscriptionController::class, 'updateStatus']); 
});
});