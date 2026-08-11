<?php

/**
 * اختبار شامل لإدارة المشرفين والشكاوى (Admins & Complaints Suite)
 * تشغيل من مجلد المشروع: php test_admins_and_complaints.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Admin\Admin;
use App\Models\User;
use App\Models\Shared\Complaint;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\ComplaintController;
use App\Http\Requests\Api\Admin\StoreAdminRequest;
use App\Http\Requests\Api\Admin\UpdateAdminRequest;
use App\Http\Requests\Api\Admin\ReviewComplaintRequest;

$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void {
    global $passed, $failed;
    try {
        $result = $fn();
        if ($result === true) {
            echo "  ✅ PASS: {$name}\n";
            $passed++;
        } else {
            echo "  ❌ FAIL: {$name} — returned: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
            $failed++;
        }
    } catch (\Throwable $e) {
        echo "  💥 ERROR: {$name} — " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
        $failed++;
    }
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║       اختبار وحدة المشرفين والشكاوى (Admins & Complaints)   ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$adminController = app(AdminController::class);
$complaintController = app(ComplaintController::class);

$randNum = rand(1000, 9999);
$testAdminName = "مشرف اختبار ثلاثي " . $randNum;
$testAdminEmail = "admin_test_" . $randNum . "@darbi.ly";
$testAdminPhone = "091" . rand(1000007, 9999999);

$createdAdminId = null;

echo "══════════════════════════════════════════════════════\n";
echo "  🧪 1. اختبار إدارة المشرفين (Admins / Supervisors CRUD)\n";
echo "══════════════════════════════════════════════════════\n";

test('1.1 index() - جلب قائمة المشرفين', function () use ($adminController) {
    $res = $adminController->index();
    $data = $res->getData(true);
    return $res->getStatusCode() === 200 && $data['status'] === true && is_array($data['data']);
});

test('1.2 store() - إضافة مشرف جديد', function () use ($adminController, $testAdminName, $testAdminEmail, $testAdminPhone, &$createdAdminId) {
    $req = StoreAdminRequest::create('/api/admin/admins', 'POST', [
        'full_name'    => $testAdminName,
        'email'        => $testAdminEmail,
        'phone_number' => $testAdminPhone,
        'password'     => '12345678',
    ]);

    $res = $adminController->store($req);
    $data = $res->getData(true);
    if ($res->getStatusCode() === 201 && $data['status'] === true) {
        $createdAdminId = $data['data']['id'];
        return true;
    }
    return $data;
});

test('1.3 show() - عرض تفاصيل المشرف المضاف', function () use ($adminController, &$createdAdminId) {
    if (!$createdAdminId) return 'No admin created';
    $res = $adminController->show($createdAdminId);
    $data = $res->getData(true);
    return $res->getStatusCode() === 200 && $data['status'] === true && $data['data']['id'] === $createdAdminId;
});

test('1.4 update() - تعديل بيانات المشرف', function () use ($adminController, &$createdAdminId, $randNum) {
    if (!$createdAdminId) return 'No admin created';
    $updatedName = "مشرف معدل ثلاثي " . $randNum;
    $req = UpdateAdminRequest::create("/api/admin/admins/{$createdAdminId}", 'PUT', [
        'full_name' => $updatedName,
        'is_active' => 1,
    ]);
    $res = $adminController->update($req, $createdAdminId);
    $data = $res->getData(true);
    return $res->getStatusCode() === 200 && $data['status'] === true && $data['data']['full_name'] === $updatedName;
});

test('1.5 destroy() - حذف المشرف التجريبي المضاف', function () use ($adminController, &$createdAdminId) {
    if (!$createdAdminId) return 'No admin created';
    $res = $adminController->destroy($createdAdminId);
    $data = $res->getData(true);
    $exists = Admin::where('id', $createdAdminId)->exists();
    return $res->getStatusCode() === 200 && $data['status'] === true && !$exists;
});

echo "\n══════════════════════════════════════════════════════\n";
echo "  🧪 2. اختبار وحدة الشكاوى (Complaints Management)\n";
echo "══════════════════════════════════════════════════════\n";

// إنشاء شكوى مؤقتة للاختبار في حال عدم وجود شكاوى
$parentUser = ParentModel::first() ?? ParentModel::create(['user_id' => User::first()->id ?? 1]);
$driverObj   = Driver::first();

$testComplaint = Complaint::create([
    'submitted_by'   => $parentUser->id,
    'driver_id'      => $driverObj?->id ?? 1,
    'title'          => 'شكوى اختبارية مؤقتة',
    'description'    => 'تفاصيل الشكوى الاختبارية للتأكد من المعالجة والإجراءات',
    'status'         => 'pending',
    'action_taken'   => 'none',
    'created_at'     => now(),
]);

test('2.1 index() - جلب جميع الشكاوى', function () use ($complaintController) {
    $res = $complaintController->index();
    $data = $res->getData(true);
    return $res->getStatusCode() === 200 && $data['status'] === true && is_array($data['data']);
});

test('2.2 show() - عرض تفاصيل شكوى محددة', function () use ($complaintController, $testComplaint) {
    $res = $complaintController->show($testComplaint->id);
    $data = $res->getData(true);
    return $res->getStatusCode() === 200 && $data['status'] === true && $data['data']['id'] === $testComplaint->id;
});

test('2.3 driverComplaints() - عرض شكاوى سائق محدد', function () use ($complaintController, $testComplaint) {
    $res = $complaintController->driverComplaints($testComplaint->driver_id);
    $data = $res->getData(true);
    return $res->getStatusCode() === 200 && $data['status'] === true && is_array($data['data']);
});

test('2.4 review() warning - إرسال إنذار/تنبيه للسائق', function () use ($complaintController, $testComplaint) {
    $admin = Admin::first();
    if ($admin) {
        auth()->setUser($admin->user);
    }
    
    $req = ReviewComplaintRequest::create("/api/admin/complaints/{$testComplaint->id}/review", 'POST', [
        'action'         => 'warning',
        'action_details' => 'تم توجيه إنذار رسمي للكابتن بالالتزام بالموعد.',
    ]);

    $res = $complaintController->review($req, $testComplaint->id);
    $data = $res->getData(true);
    return $res->getStatusCode() === 200 && $data['status'] === true && $data['data']['status'] === 'completed';
});

// إنشاء شكوى ثانية لاختبار الإيقاف
$testComplaintSuspension = Complaint::create([
    'submitted_by'   => $parentUser->id,
    'driver_id'      => $driverObj?->id ?? 1,
    'title'          => 'شكوى اختبارية لإيقاف السائق',
    'description'    => 'تفاصيل التوقف',
    'status'         => 'pending',
    'action_taken'   => 'none',
    'created_at'     => now(),
]);

test('2.5 review() suspension - إيقاف حساب السائق مع تحويل حالته لـ Suspended', function () use ($complaintController, $testComplaintSuspension) {
    $req = ReviewComplaintRequest::create("/api/admin/complaints/{$testComplaintSuspension->id}/review", 'POST', [
        'action'         => 'suspension',
        'action_details' => 'تم إيقاف حساب السائق مؤقتاً لمراجعة المخالفة.',
    ]);

    $res = $complaintController->review($req, $testComplaintSuspension->id);
    $data = $res->getData(true);
    return $res->getStatusCode() === 200 && $data['status'] === true && $data['data']['status'] === 'completed';
});

// إنشاء شكوى ثالثة لاختبار التجاهل (Dismiss)
$testComplaintDismiss = Complaint::create([
    'submitted_by'   => $parentUser->id,
    'driver_id'      => $driverObj?->id ?? 1,
    'title'          => 'شكوى غير مبررة للتجاهل',
    'description'    => 'تفاصيل',
    'status'         => 'pending',
    'action_taken'   => 'none',
    'created_at'     => now(),
]);

test('2.6 review() dismiss - حفظ وتجاهل الشكوى', function () use ($complaintController, $testComplaintDismiss) {
    $req = ReviewComplaintRequest::create("/api/admin/complaints/{$testComplaintDismiss->id}/review", 'POST', [
        'action'         => 'dismiss',
        'action_details' => 'تم حفظ وتجاهل الشكوى لعدم وجود دلايل كافية.',
    ]);

    $res = $complaintController->review($req, $testComplaintDismiss->id);
    $data = $res->getData(true);
    return $res->getStatusCode() === 200 && $data['status'] === true && $data['data']['status'] === 'dismissed';
});

// تنظيف الشكاوى الاختبارية
$testComplaint->delete();
$testComplaintSuspension->delete();
$testComplaintDismiss->delete();

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
$total = $passed + $failed;
echo "║  النتيجة النهائية: {$passed}/{$total} اختبارات نجحت" . str_repeat(' ', max(0, 28 - strlen("{$passed}/{$total}"))) . "║\n";
if ($failed > 0) {
    echo "║  ❌ فشل: {$failed} اختبار" . str_repeat(' ', max(0, 40 - strlen("{$failed}") - 4)) . "║\n";
} else {
    echo "║  🎉 جميع اختبارات المشرفين والشكاوى نجحت 100%!               ║\n";
}
echo "╚══════════════════════════════════════════════════════════════╝\n\n";
