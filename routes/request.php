<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Parent\ParentSubscriptionController;
use App\Http\Controllers\API\Driver\DriverSubscriptionController;
use App\Http\Controllers\API\Shared\ContractController;
use App\Http\Controllers\API\Driver\DriverRouteController;

Route::middleware('auth:sanctum')->group(function () {

    // ============================================================
    // مسارات العقود (Shared)
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

    // ============================================================
    // مسارات أولياء الأمور لإرسال واستعراض طلبات الاشتراك
    // ============================================================
    Route::prefix('parent')->group(function () {
        // المسار الموحد لجلب طلبات الاشتراك (الطلبات الأولية المعلقة والمرفوضة)
        Route::get('/requests', [ParentSubscriptionController::class, 'index']); 
        
        // المسار الموحد والوحيد الجديد لجلب الاشتراكات المفعّلة والموافَق عليها بالفلاتر
        Route::get('/active-subscriptions', [ParentSubscriptionController::class, 'activeSubscriptions']); 
        
        Route::post('/requests', [ParentSubscriptionController::class, 'store']); 
        Route::get('/requests/{id}', [ParentSubscriptionController::class, 'show']); 
        Route::post('subscriptions/{id}/cancel', [ParentSubscriptionController::class, 'cancel']);
    });

    // ============================================================
    // مسارات السائقين المحدثة
    // ============================================================
    Route::prefix('driver')->group(function () {
        // 1. مسارات ثابتة (Static Routes) - توضع أولاً
        
        // المسار الموحد الجديد لجلب طلبات الاشتراك المبدئية بالفلاتر
        Route::get('/requests', [DriverSubscriptionController::class, 'index']); 
        
        // المسار الموحد الجديد لجلب الاشتراكات الفعلية والمثبتة بالفلاتر
        Route::get('/active-subscriptions', [DriverSubscriptionController::class, 'activeSubscriptions']); 
        
        Route::get('/routes', [DriverRouteController::class, 'index']); 
        Route::post('/trips/start', [TripController::class, 'startTrip']);
        
        // 2. مسارات تحتوي على متغيرات (Dynamic Parameters) - توضع أخيراً
        Route::put('/routes/{route}', [DriverRouteController::class, 'update']);
        Route::get('/{id}', [DriverSubscriptionController::class, 'show']); 
        Route::put('{id}/status', [DriverSubscriptionController::class, 'updateStatus']); 
    });
    
});