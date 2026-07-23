<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// 1. إعادة توجيه الصفحة الرئيسية ورابط /dashboard إلى لوحة تحكم Filament
Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/dashboard', function () {
    return redirect('/admin');
})->name('dashboard');

// 2. واجهة الرادار (الملف موجود باسم live-radar.blade.php وسيعمل فوراً)
Route::get('/live-radar', function () {
    return view('live-radar');
})->middleware(['auth', 'verified'])->name('live-radar');

// 3. مسارات الملف الشخصي
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});