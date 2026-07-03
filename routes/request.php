<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Parent\ParentSubscriptionController;
use App\Http\Controllers\API\Driver\DriverSubscriptionController;
use App\Http\Controllers\API\Shared\ContractController;

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('contracts')->group(function () {

        Route::get('contracts/{id}/pdf', [ContractController::class, 'generatePdf']);
        Route::get('/clauses', [ContractController::class, 'clauses']);
        Route::post('/', [ContractController::class, 'store']);
        Route::get('/{id}', [ContractController::class, 'show']);
        Route::put('/{id}/accept', [ContractController::class, 'accept']);
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
});

    // مسارات السائقين لاستقبال والرد على طلبات الاشتراك
    Route::prefix('driver')->group(function () {
        Route::get('/', [DriverSubscriptionController::class, 'index']);           // هذا موجود لديك
        Route::get('/{id}', [DriverSubscriptionController::class, 'show']);         // **أضف هذا المسار**
        Route::put('{id}/status', [DriverSubscriptionController::class, 'updateStatus']); 
    });
});