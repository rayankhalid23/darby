<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\SchoolController;
use App\Http\Controllers\Api\Admin\AdminDriverController; 
use App\Http\Controllers\Api\Admin\ZoneController;
use App\Http\Controllers\Api\Admin\DriverReviewController as AdminDriverReviewController;
use App\Http\Controllers\Api\Admin\ComplaintController as AdminComplaintController;
// =========================================================================
// 🔒 المسارات المحمية (تتطلب تسجيل الدخول وحمل توكن Sanctum)
// =========================================================================
Route::middleware(['auth:sanctum'])->group(function () {
    
    // --- مجموعة روابط إدارة المشرفين ---
    Route::prefix('admins')->group(function () {
        Route::get('/', [AdminController::class, 'index']);
        Route::post('/', [AdminController::class, 'store']);
        Route::get('/{id}', [AdminController::class, 'show']);
        Route::post('/{id}', [AdminController::class, 'update']); 
    });

    // --- 👥 مجموعة روابط التحكم في السائقين المحدثة والمطورة بالكامل لقابلية البيع ---
    Route::prefix('drivers')->group(function () {
        
        // 1. مسار جلب قائمة طلبات السائقين مع الفلترة والبحث الذكي
        Route::get('/', [AdminDriverController::class, 'index'])->name('api.admin.drivers.index');
        
        /*
        |--------------------------------------------------------------------------
        | 🚀 مسارات إدارة التعديلات اللاحقة (تم وضعها هنا حمايةً من تعارض الـ ID)
        |--------------------------------------------------------------------------
        */
        // 4. مسار جلب كافة طلبات تعديل البيانات والمركبات المعلقة للآدمن
        Route::get('/pending-changes', [AdminDriverController::class, 'pendingChanges'])->name('api.admin.drivers.pending.list');
        
        // 5. مسار عرض تفصيلي مقارن لطلب تعديل محدد
        Route::get('/pending-changes/{id}', [AdminDriverController::class, 'showPendingChange'])->name('api.admin.drivers.pending.show');
        
        // 6. مسار معالجة قرار الموافقة والتطبيق الفوري أو الرفض المسبب لتعديلات السائق
        Route::post('/pending-changes/{id}/review', [AdminDriverController::class, 'reviewProfileChange'])->name('api.admin.drivers.pending.review');


        /*
        |--------------------------------------------------------------------------
        | 📋 مسارات مراجعة الحسابات الأساسية والإنشاء الأولي
        |--------------------------------------------------------------------------
        */
        // 2. مسار جلب تفاصيل ووثائق وإحصائيات سائق معين بعمق لمراجعته
        Route::get('/{id}', [AdminDriverController::class, 'show'])->name('api.admin.drivers.show');
        
        // 3. مسار اتخاذ قرار المراجعة لإنشاء الحساب (قبول مفعل باحتفالية أو رفض مسبب)
        Route::post('/{id}/review', [AdminDriverController::class, 'review'])->name('api.admin.drivers.review');
    });

    // --- مجموعة روابط إدارة المدارس ---
    Route::prefix('schools')->group(function () {
        Route::get('/', [SchoolController::class, 'index']);
        Route::post('/', [SchoolController::class, 'store']);
        Route::get('/{id}', [SchoolController::class, 'show']);
        Route::post('/{id}', [SchoolController::class, 'update']); 
        Route::delete('/{id}', [SchoolController::class, 'destroy']);
    });

    // مسارات إدارة الجغرافيا والمناطق (صلاحيات كاملة للآدمن)
Route::prefix('zones')->group(function () {
    Route::get('/', [ZoneController::class, 'index']);       // عرض المناطق
    Route::post('/', [ZoneController::class, 'store']);      // إضافة منطقة جديدة
    Route::put('/{id}', [ZoneController::class, 'update']);  // تعديل اسم منطقة
    Route::delete('/{id}', [ZoneController::class, 'destroy']); // حذف منطقة
});
Route::get('/zones-tree', [ZoneController::class, 'index']);

  // --- 📊 مسارات إدارة تقييمات السائقين للأدمن (بالاسم الصريح الحاسم) ---
Route::prefix('driver-reviews')->group(function () {
    // استدعاء مباشر للكلاس بالكامل لمنع أي تداخل مع الكاش
    Route::get('/all', [\App\Http\Controllers\Api\Admin\DriverReviewController::class, 'allReviews']); 
    Route::get('/driver/{driverId}', [\App\Http\Controllers\Api\Admin\DriverReviewController::class, 'index']); 
    Route::delete('/{id}', [\App\Http\Controllers\Api\Admin\DriverReviewController::class, 'destroy']); 
});

    // --- 📋 مسارات إدارة الشكاوى للأدمن ---
    Route::prefix('complaints')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Admin\ComplaintController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\Admin\ComplaintController::class, 'show']);
        Route::get('/driver/{driverId}', [\App\Http\Controllers\Api\Admin\ComplaintController::class, 'driverComplaints']);
        Route::post('/{id}/review', [\App\Http\Controllers\Api\Admin\ComplaintController::class, 'review']);
    });

});

// =========================================================================
// 🔓 المسارات العامة الموقعة (تُفتح مباشرة من المتصفح عبر رابط الإيميل السحري)
// =========================================================================
Route::prefix('admin/email')->group(function () {
    Route::get('/approve/{token}', [AdminController::class, 'approveEmailChange'])->name('admin.email.approve');
    Route::get('/reject/{token}', [AdminController::class, 'rejectEmailChange'])->name('admin.email.reject');
});

