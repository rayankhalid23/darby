<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// توليد الرحلات اليومية (Daily Trips) لأي مسار دخل نافذة T-30 دقيقة قبل وقت انطلاقه
Schedule::command('trips:generate-daily')->everyMinute();

// فحص الطلبات المعلقة كل 6 ساعات وإلغاء غير القابلة للتنفيذ
Schedule::command('subscriptions:check-pending')->everySixHours();
