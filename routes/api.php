<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\PasswordController;
use App\Http\Controllers\Api\Shared\NotificationController;
use App\Http\Controllers\Api\NotificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// مسار تسجيل الدخول
Route::post('/auth/login', [LoginController::class, 'login']);

// مسارات استعادة كلمة المرور (عامة - خارج الميدلوير)
// مسارات استعادة كلمة المرور (عامة)
Route::prefix('auth/password')->group(function () {
    Route::post('/send-otp', [PasswordController::class, 'sendResetOtp']); // 1. إرسال الكود
    Route::post('/verify-otp', [PasswordController::class, 'verifyOtp']);  // 2. التحقق من الكود (الجديدة)
    Route::post('/reset', [PasswordController::class, 'resetPassword']);   // 3. تغيير كلمة المرور
});

// المسارات المحمية بالتوكن
Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('/auth/logout', [LoginController::class, 'logout']);
    
    Route::get('/user/profile', function (Request $request) {
        return response()->json([
            'status' => true,
            'user' => $request->user()
        ]);
    });

    Route::post('/send-notification', [NotificationController::class, 'sendTestNotification']);

    // مسارات الإشعارات الفورية وإدارة التوكنات (Notifications & Device Tokens)
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    Route::post('/user/device-token', [NotificationController::class, 'storeDeviceToken']);
    Route::delete('/user/device-token', [NotificationController::class, 'removeDeviceToken']);
});