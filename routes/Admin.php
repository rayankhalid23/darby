<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\SchoolController;
use App\Http\Controllers\Api\Admin\AdminDriverController; 
use App\Http\Controllers\Api\Admin\ZoneController;
use App\Http\Controllers\Api\Admin\DriverReviewController as AdminDriverReviewController;
use App\Http\Controllers\Api\Admin\ComplaintController as AdminComplaintController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\AdminAvatarController;
use App\Http\Controllers\Api\Admin\AdminProfileController;
use App\Http\Controllers\Api\Admin\MunicipalityController;
use App\Http\Controllers\Api\Admin\SubMunicipalityController;
use App\Http\Controllers\Api\Admin\MunicipalityZoneController;

// =========================================================================
// 🖼️ صور المشرفين (عامة بلا توكن)
// =========================================================================
// المتصفح يجلب الصورة في وسم <img> أو عبر XHR بدون أي هيدر Authorization،
// لذلك يجب أن يكون المسار عاماً. ولأنه يمر عبر لارافيل فإنه يحصل على ترويسات
// CORS الخاصة بـ api/* تلقائياً، بعكس ملفات /storage الثابتة.
Route::get('/avatars/{filename}', [AdminAvatarController::class, 'show'])
    ->name('api.admin.avatars.show');

// =========================================================================
// 🔒 المسارات المحمية (تتطلب تسجيل الدخول وحمل توكن Sanctum)
// =========================================================================
Route::middleware(['auth:sanctum'])->group(function () {

    // =========================================================================
    // 📊 مسارات لوحة التحكم الرئيسية (الداشبورد)
    // =========================================================================
    Route::prefix('dashboard')->group(function () {
        // إحصائيات الداشبورد (المستخدمين، السائقين، الاشتراكات، الرحلات)
        Route::get('/stats', [DashboardController::class, 'stats'])->name('api.admin.dashboard.stats');
        // الرحلات النشطة الآن للرادار الحي
        Route::get('/active-trips', [DashboardController::class, 'activeTrips'])->name('api.admin.dashboard.active-trips');
    });
    
    // --- 👤 الملف الشخصي للمشرف / مدير النظام (الحساب الحالي) ---
    Route::prefix('profile')->group(function () {
        Route::get('/',  [AdminProfileController::class, 'show'])->name('api.admin.profile.show');
        Route::post('/', [AdminProfileController::class, 'update'])->name('api.admin.profile.update');

        Route::get('/email-change/status',  [AdminProfileController::class, 'emailChangeStatus']);
        Route::post('/email-change/cancel', [AdminProfileController::class, 'cancelEmailChange']);
        Route::post('/email-change/resend', [AdminProfileController::class, 'resendEmailChange']);
    });

    // --- مجموعة روابط إدارة المشرفين ---
    Route::prefix('admins')->group(function () {
        Route::get('/', [AdminController::class, 'index']);
        Route::post('/', [AdminController::class, 'store']);

        // --- 📧 متابعة طلب تغيير البريد الإلكتروني (توضع قبل مسارات {id} المفردة) ---
        Route::get('/{id}/email-change/status',  [AdminController::class, 'emailChangeStatus']);
        Route::post('/{id}/email-change/cancel', [AdminController::class, 'cancelEmailChange']);
        Route::post('/{id}/email-change/resend', [AdminController::class, 'resendEmailChange']);

        Route::get('/{id}', [AdminController::class, 'show']);
        Route::post('/{id}', [AdminController::class, 'update']);
        Route::delete('/{id}', [AdminController::class, 'destroy']);
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

    // =========================================================================
    // 🗺️ إدارة الجغرافيا: بلدية ← محلة ← منطقة
    // =========================================================================

    // --- 🏛️ البلديات (المستوى الأول) ---
    Route::prefix('municipalities')->group(function () {
        Route::get('/',        [MunicipalityController::class, 'index']);
        Route::post('/',       [MunicipalityController::class, 'store']);
        Route::get('/{id}',    [MunicipalityController::class, 'show']);
        Route::post('/{id}',   [MunicipalityController::class, 'update']);
        Route::delete('/{id}', [MunicipalityController::class, 'destroy']);

        // 🏘️ محلات البلدية
        Route::get('/{municipalityId}/sub-municipalities',  [SubMunicipalityController::class, 'index']);
        Route::post('/{municipalityId}/sub-municipalities', [SubMunicipalityController::class, 'store']);

        // 📍 كل مناطق البلدية مسطّحة عبر محلاتها (عرض مريح للواجهة)
        Route::get('/{municipalityId}/zones', [MunicipalityZoneController::class, 'indexByMunicipality']);
    });

    // --- 🏘️ المحلات (المستوى الثاني) ---
    Route::prefix('sub-municipalities')->group(function () {
        Route::get('/{id}',    [SubMunicipalityController::class, 'show']);
        Route::post('/{id}',   [SubMunicipalityController::class, 'update']);
        Route::delete('/{id}', [SubMunicipalityController::class, 'destroy']);

        // 📍 مناطق المحلة
        Route::get('/{subMunicipalityId}/zones',  [MunicipalityZoneController::class, 'index']);
        Route::post('/{subMunicipalityId}/zones', [MunicipalityZoneController::class, 'store']);
    });

    // --- 📍 المناطق (المستوى الثالث) ---
    Route::prefix('admin-zones')->group(function () {
        Route::get('/{id}',    [MunicipalityZoneController::class, 'show']);
        Route::post('/{id}',   [MunicipalityZoneController::class, 'update']);
        Route::delete('/{id}', [MunicipalityZoneController::class, 'destroy']);
    });

    // --- ⚠️ المسارات القديمة: يعتمد عليها تطبيقا السائق وولي الأمر، تُترك كما هي ---
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

    // --- 💰 مسارات الإدارة المالية للأدمن ---
    Route::prefix('financial')->group(function () {
        Route::get('/invoices', [\App\Http\Controllers\Api\Admin\FinancialController::class, 'invoices']);
        Route::get('/invoices/{id}', [\App\Http\Controllers\Api\Admin\FinancialController::class, 'invoiceDetail']);
        Route::get('/withdrawals', [\App\Http\Controllers\Api\Admin\FinancialController::class, 'withdrawals']);
        Route::post('/withdrawals/{id}/process', [\App\Http\Controllers\Api\Admin\FinancialController::class, 'processWithdrawal']);
        Route::get('/recharges', [\App\Http\Controllers\Api\Admin\FinancialController::class, 'rechargeRequests']);
        Route::post('/recharges/{id}/process', [\App\Http\Controllers\Api\Admin\FinancialController::class, 'processRecharge']);
    });

    // مسارات الفواتير للأدمن (قراءة فقط)
    Route::prefix('invoices')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Shared\InvoiceController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\Shared\InvoiceController::class, 'show']);
    });

});

// =========================================================================
// 🔓 المسارات العامة الموقعة (تُفتح مباشرة من المتصفح عبر رابط الإيميل السحري)
// =========================================================================
Route::prefix('admin/email')->group(function () {
    Route::get('/approve/{token}', [AdminController::class, 'approveEmailChange'])->name('admin.email.approve');
    Route::get('/reject/{token}', [AdminController::class, 'rejectEmailChange'])->name('admin.email.reject');
});

