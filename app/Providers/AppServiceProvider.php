<?php

namespace App\Providers;

use App\Models\Shared\Complaint;
use App\Models\Shared\DriverReview;
use App\Observers\ComplaintObserver;
use App\Observers\DriverReviewObserver;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->allowFileUploadsOnArtisanServe();
    }

    /**
     * 🩹 إصلاح رفع الملفات عند استخدام `php artisan serve` على ويندوز.
     *
     * الأمر serve يمرّر قائمة محددة فقط من متغيرات البيئة للعملية الفرعية،
     * وليس من ضمنها TMP و TEMP. وبدونهما لا يجد PHP على ويندوز مجلداً مؤقتاً
     * فيفشل رفع أي ملف بالخطأ:
     *   "PHP Request Startup: File upload error - unable to create a temporary file"
     * والأسوأ أن هذا التحذير يُطبع كـ HTML قبل الـ JSON فيُفسد الرد على الواجهة.
     */
    private function allowFileUploadsOnArtisanServe(): void
    {
        if (! class_exists(ServeCommand::class) || ! property_exists(ServeCommand::class, 'passthroughVariables')) {
            return;
        }

        foreach (['TMP', 'TEMP', 'TMPDIR'] as $variable) {
            if (! in_array($variable, ServeCommand::$passthroughVariables, true)) {
                ServeCommand::$passthroughVariables[] = $variable;
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Complaint::observe(ComplaintObserver::class);
        DriverReview::observe(DriverReviewObserver::class);
    }
}