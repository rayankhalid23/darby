<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;

echo "========================================================\n";
echo "🧪 TESTING ALL ADMIN CONTROLLER METHODS AND ENDPOINTS\n";
echo "========================================================\n\n";

$adminUser = User::where('role_id', 1)->first() ?? User::where('email', 'admin@darby.com')->first();
if (!$adminUser) {
    $adminUser = User::first();
    if ($adminUser) {
        $adminUser->role_id = 1;
        $adminUser->save();
    }
}

Auth::login($adminUser);
echo "✅ Authenticated as Admin User ID: {$adminUser->id} ({$adminUser->full_name})\n\n";

$results = [];

function testEndpoint($name, callable $callback) {
    global $results;
    try {
        $res = $callback();
        $results[$name] = ['status' => 'SUCCESS', 'data' => $res];
        echo "✅ {$name}: PASSED\n";
    } catch (Throwable $e) {
        $results[$name] = ['status' => 'FAILED', 'error' => $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine()];
        echo "❌ {$name}: FAILED - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

// 1. Dashboard
testEndpoint("DashboardController::stats", function() {
    $c = app(\App\Http\Controllers\Api\Admin\DashboardController::class);
    return $c->stats()->getData(true);
});

testEndpoint("DashboardController::activeTrips", function() {
    $c = app(\App\Http\Controllers\Api\Admin\DashboardController::class);
    return $c->activeTrips()->getData(true);
});

// 2. Admin Management
testEndpoint("AdminController::index", function() {
    $c = app(\App\Http\Controllers\Api\Admin\AdminController::class);
    return $c->index()->getData(true);
});

// 3. Admin Driver Controller
testEndpoint("AdminDriverController::index", function() {
    $c = app(\App\Http\Controllers\Api\Admin\AdminDriverController::class);
    $req = new \Illuminate\Http\Request();
    return $c->index($req)->getData(true);
});

testEndpoint("AdminDriverController::pendingChanges", function() {
    $c = app(\App\Http\Controllers\Api\Admin\AdminDriverController::class);
    return $c->pendingChanges()->getData(true);
});

// 4. School Controller
testEndpoint("SchoolController::index", function() {
    $c = app(\App\Http\Controllers\Api\Admin\SchoolController::class);
    return $c->index()->getData(true);
});

// 5. Zone Controller
testEndpoint("ZoneController::index", function() {
    $c = app(\App\Http\Controllers\Api\Admin\ZoneController::class);
    return $c->index()->getData(true);
});

// 6. Driver Review Controller
testEndpoint("DriverReviewController::allReviews", function() {
    $c = app(\App\Http\Controllers\Api\Admin\DriverReviewController::class);
    return $c->allReviews()->getData(true);
});

// 7. Complaint Controller
testEndpoint("ComplaintController::index", function() {
    $c = app(\App\Http\Controllers\Api\Admin\ComplaintController::class);
    $req = new \Illuminate\Http\Request();
    return $c->index($req)->getData(true);
});

// 8. Financial Controller
testEndpoint("FinancialController::invoices", function() {
    $c = app(\App\Http\Controllers\Api\Admin\FinancialController::class);
    $req = new \Illuminate\Http\Request();
    return $c->invoices($req)->getData(true);
});

testEndpoint("FinancialController::withdrawals", function() {
    $c = app(\App\Http\Controllers\Api\Admin\FinancialController::class);
    $req = new \Illuminate\Http\Request();
    return $c->withdrawals($req)->getData(true);
});

testEndpoint("FinancialController::rechargeRequests", function() {
    $c = app(\App\Http\Controllers\Api\Admin\FinancialController::class);
    $req = new \Illuminate\Http\Request();
    return $c->rechargeRequests($req)->getData(true);
});

echo "\n========================================================\n";
echo "📊 TEST RESULTS SUMMARY:\n";
echo "========================================================\n";
foreach ($results as $name => $res) {
    echo "- {$name}: {$res['status']}\n";
    if ($res['status'] === 'FAILED') {
        echo "  Detail: {$res['error']}\n";
    }
}
