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
use App\Http\Controllers\Api\Admin\AdminAuditLogController;
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

    // تشغيل يدوي لتوليد الرحلات اليومية (Daily Trips) دون انتظار الـ Cron — للتشغيل والاختبار
    Route::post('/trips/generate-daily', [\App\Http\Controllers\Api\Admin\TripOpsController::class, 'generateDaily'])
        ->name('api.admin.trips.generate-daily');

    // --- 🔔 إشعارات لوحة تحكم الأدمن ---
    Route::prefix('notifications')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'index']);
        Route::get('/unread-count', [\App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'unreadCount']);
        Route::patch('/{id}/read', [\App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'markAsRead']);
        Route::post('/read-all', [\App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'destroy']);
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
        Route::put('/{id}', [AdminController::class, 'update']);
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
        
        // 2-ب. مسار تعديل بيانات السائق مباشرة من قبل المشرف / الأدمن
        Route::put('/{id}', [AdminDriverController::class, 'update'])->name('api.admin.drivers.update');
        Route::post('/{id}', [AdminDriverController::class, 'update']);

        // 3. مسار اتخاذ قرار المراجعة لإنشاء الحساب (قبول مفعل باحتفالية أو رفض مسبب)
        Route::post('/{id}/review', [AdminDriverController::class, 'review'])->name('api.admin.drivers.review');
    });

    // =========================================================================
    // 📜 مسارات سجل تدقيق إجراءات المشرفين والمدراء (Audit Logs - الأدمن فقط)
    // =========================================================================
    Route::get('/admin-audit-logs', [AdminAuditLogController::class, 'index'])->name('api.admin.audit-logs.index');
    Route::get('/admin-audit-logs/{id}', [AdminAuditLogController::class, 'show'])->name('api.admin.audit-logs.show');

    // --- مجموعة روابط إدارة المدارس ---
    Route::prefix('schools')->group(function () {
        Route::get('/', [SchoolController::class, 'index']);
        Route::post('/', [SchoolController::class, 'store']);
        Route::get('/{id}', [SchoolController::class, 'show']);
        Route::post('/{id}', [SchoolController::class, 'update']); 
        Route::delete('/{id}', [SchoolController::class, 'destroy']);
    });

    // =========================================================================
    // 🗺️ مسارات إدارة الجغرافيا والمناطق والبلديات (Geographic Management)
    // =========================================================================
    Route::prefix('zones')->group(function () {
        Route::get('/', [ZoneController::class, 'index']);          // عرض المناطق
        Route::post('/', [ZoneController::class, 'store']);         // إضافة منطقة جديدة
        Route::get('/{id}', [ZoneController::class, 'show']);       // عرض تفاصيل منطقة
        Route::put('/{id}', [ZoneController::class, 'update']);     // تعديل منطقة
        Route::post('/{id}', [ZoneController::class, 'update']);    // للتوافق مع POST multipart
        Route::delete('/{id}', [ZoneController::class, 'destroy']);// حذف منطقة
    });
    Route::get('/zones-tree', [ZoneController::class, 'index']);    // الشجرة الجغرافية الكاملة

    // مسارات البلديات الكبرى (Municipalities) — لوحة الأدمن الجديدة (بلدية ← محلة ← منطقة)
    Route::prefix('municipalities')->group(function () {
        Route::get('/', [MunicipalityController::class, 'index']);
        Route::post('/', [MunicipalityController::class, 'store']);

        // متداخلة تحت بلدية محددة — توضع قبل مسار {id} المفرد لتفادي أي تعارض
        Route::get('/{id}/sub-municipalities', [SubMunicipalityController::class, 'index']);
        Route::post('/{id}/sub-municipalities', [SubMunicipalityController::class, 'store']);
        Route::get('/{id}/zones', [MunicipalityZoneController::class, 'indexByMunicipality']);

        Route::get('/{id}', [MunicipalityController::class, 'show']);
        Route::put('/{id}', [MunicipalityController::class, 'update']);
        Route::post('/{id}', [MunicipalityController::class, 'update']);
        Route::delete('/{id}', [MunicipalityController::class, 'destroy']);
    });

    // مسارات البلديات الفرعية / المحلات (Sub-Municipalities)
    Route::prefix('sub-municipalities')->group(function () {
        Route::get('/{id}/zones', [MunicipalityZoneController::class, 'index']);
        Route::post('/{id}/zones', [MunicipalityZoneController::class, 'store']);

        Route::get('/{id}', [SubMunicipalityController::class, 'show']);
        Route::put('/{id}', [SubMunicipalityController::class, 'update']);
        Route::post('/{id}', [SubMunicipalityController::class, 'update']);
        Route::delete('/{id}', [SubMunicipalityController::class, 'destroy']);
    });

    // مسارات المناطق الدقيقة للوحة الأدمن الجديدة (منفصلة تماماً عن /zones القديم
    // الذي يعتمد عليه تطبيقا السائق وولي الأمر ولم يُمس بأي تعديل)
    Route::prefix('admin-zones')->group(function () {
        Route::get('/{id}', [MunicipalityZoneController::class, 'show']);
        Route::put('/{id}', [MunicipalityZoneController::class, 'update']);
        Route::post('/{id}', [MunicipalityZoneController::class, 'update']);
        Route::delete('/{id}', [MunicipalityZoneController::class, 'destroy']);
    });

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

    // --- 💰 مسارات الإدارة المالية الشاملة للأدمن ---
    Route::prefix('financial')->group(function () {
        // 1. الداشبورد والملخص والسلامة المالية والسجلات
        Route::get('/summary', [\App\Http\Controllers\Api\Admin\FinancialLedgerController::class, 'summary']);
        Route::get('/solvency-check', [\App\Http\Controllers\Api\Admin\FinancialLedgerController::class, 'solvencyCheck']);
        Route::get('/ledger', [\App\Http\Controllers\Api\Admin\FinancialLedgerController::class, 'ledgerLogs']);
        Route::get('/audit-logs', [\App\Http\Controllers\Api\Admin\FinancialLedgerController::class, 'auditLogs']);

        // 2. الفواتير
        Route::get('/invoices', [\App\Http\Controllers\Api\Admin\FinancialController::class, 'invoices']);
        Route::get('/invoices/{id}', [\App\Http\Controllers\Api\Admin\FinancialController::class, 'invoiceDetail']);

        // 3. طلبات السحب
        Route::get('/withdrawals', [\App\Http\Controllers\Api\Admin\FinancialController::class, 'withdrawals']);
        Route::get('/withdrawals/{id}', [\App\Http\Controllers\Api\Admin\FinancialController::class, 'withdrawalDetail']);
        Route::post('/withdrawals/{id}/process', [\App\Http\Controllers\Api\Admin\FinancialController::class, 'processWithdrawal']);

        // 4. طلبات الشحن
        Route::get('/recharges', [\App\Http\Controllers\Api\Admin\FinancialController::class, 'rechargeRequests']);
        Route::get('/recharges/{id}', [\App\Http\Controllers\Api\Admin\FinancialController::class, 'rechargeDetail']);
        Route::post('/recharges/{id}/process', [\App\Http\Controllers\Api\Admin\FinancialController::class, 'processRecharge']);

        // 5. الأمانات المعلقة وتفاصيل التحرير
        Route::get('/escrows', [\App\Http\Controllers\Api\Admin\FinancialLedgerController::class, 'escrowOverview']);
        Route::post('/release-escrows', [\App\Http\Controllers\Api\Admin\FinancialLedgerController::class, 'releaseEscrows']);

        // 6. النزاعات المالية
        Route::get('/disputes', [\App\Http\Controllers\Api\Admin\FinancialLedgerController::class, 'disputesList']);
        Route::get('/disputes/{id}', [\App\Http\Controllers\Api\Admin\FinancialLedgerController::class, 'disputeDetail']);
        Route::post('/disputes/{disputeId}/resolve', [\App\Http\Controllers\Api\Admin\FinancialLedgerController::class, 'resolveDispute']);

        // 7. تسويات العقود الإغلاقية والإلغاء المبكر والمعاينة
        Route::get('/contracts/pending-settlements', [\App\Http\Controllers\Api\Admin\FinancialLedgerController::class, 'pendingSettlements']);
        Route::post('/contracts/{contractId}/settle-monthly', [\App\Http\Controllers\Api\Admin\FinancialLedgerController::class, 'settleMonthly']);
        Route::get('/contracts/{contractId}/termination-preview', [\App\Http\Controllers\Api\Admin\FinancialLedgerController::class, 'terminationPreview']);
        Route::post('/contracts/{contractId}/terminate-mid-month', [\App\Http\Controllers\Api\Admin\FinancialLedgerController::class, 'terminateMidMonth']);

        // 8. إلغاء الرحلات بمصفوفة الغرامات والمعاينة
        Route::get('/trips/{tripId}/cancel-preview', [\App\Http\Controllers\Api\Admin\FinancialLedgerController::class, 'cancellationPreview']);
        Route::post('/trips/{tripId}/cancel-with-matrix', [\App\Http\Controllers\Api\Admin\FinancialLedgerController::class, 'cancelTripWithMatrix']);
    });

    // مسارات الفواتير للأدمن (قراءة فقط)
    Route::prefix('invoices')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Shared\InvoiceController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\Shared\InvoiceController::class, 'show']);
    });

    // =========================================================================
    // 📈 مسارات تقارير لوحة التحكم والإحصائيات التحليلية (Reports & Analytics)
    // =========================================================================
    Route::prefix('reports')->group(function () {
        // 1. الإحصائيات السريعة ومؤشرات KPI
        Route::get('/kpi-summary', [\App\Http\Controllers\Api\Admin\ReportController::class, 'kpiSummary'])->name('api.admin.reports.kpi');
        // 2. التقارير المالية والإيرادات
        Route::get('/financial', [\App\Http\Controllers\Api\Admin\ReportController::class, 'financialReport'])->name('api.admin.reports.financial');
        // 3. تقارير التشغيل والرحلات والخريطة الحرارية للطلب
        Route::get('/trips', [\App\Http\Controllers\Api\Admin\ReportController::class, 'tripsReport'])->name('api.admin.reports.trips');
        // 4. تقارير توزيع أنواع الاشتراكات والاشتراكات المنتهية قريباً
        Route::get('/subscriptions', [\App\Http\Controllers\Api\Admin\ReportController::class, 'subscriptionsReport'])->name('api.admin.reports.subscriptions');
        // 5. تقارير أداء السائقين وحالة رخص القيادة ووثائق المركبات
        Route::get('/drivers-performance', [\App\Http\Controllers\Api\Admin\ReportController::class, 'driversPerformanceReport'])->name('api.admin.reports.drivers-performance');
        // 6. تصدير البيانات والتقارير (CSV / JSON)
        Route::get('/export', [\App\Http\Controllers\Api\Admin\ReportController::class, 'exportReport'])->name('api.admin.reports.export');
    });

});

// =========================================================================
// 🔓 المسارات العامة الموقعة (تُفتح مباشرة من المتصفح عبر رابط الإيميل السحري)
// =========================================================================
Route::prefix('admin/email')->group(function () {
    Route::get('/approve/{token}', [AdminController::class, 'approveEmailChange'])->name('admin.email.approve');
    Route::get('/reject/{token}', [AdminController::class, 'rejectEmailChange'])->name('admin.email.reject');
});

