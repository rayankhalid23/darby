<?php

namespace App\Console\Commands;

use App\Jobs\EvaluateDriverPolicyJob;
use App\Models\Driver\Driver;
use Illuminate\Console\Command;

class EvaluateDriverPolicyCommand extends Command
{
    /**
     * اسم ووصف أمر الـ Artisan
     */
    protected $signature = 'driver:evaluate-ai {driverId? : المعرف الرقمي للسائق لاختبار تقييمه فردياً}';

    protected $description = 'تشغيل محرك تقييم سياسات السائقين عبر خدمة الذكاء الاصطناعي (FastAPI) وتطبيق القرارات والتنبيهات';

    public function handle(): int
    {
        $driverId = $this->argument('driverId');

        if ($driverId) {
            $this->info("جاري تشغيل تقييم الذكاء الاصطناعي للسائق رقم [{$driverId}]...");
            EvaluateDriverPolicyJob::dispatchSync((int) $driverId);
            $this->info("تم الانتهاء من تقييم السائق رقم [{$driverId}] بنجاح.");
            return Command::SUCCESS;
        }

        $this->info("جاري فحص وتقييم كافة السائقين النشطين والمعتمدين عبر محرك الذكاء الاصطناعي...");

        $driverIds = Driver::whereIn('status', ['Active', 'Approved'])->pluck('id');
        $count = 0;

        foreach ($driverIds as $id) {
            EvaluateDriverPolicyJob::dispatch((int) $id);
            $count++;
        }

        $this->info("تم جدولة وإرسال [{$count}] مهمة تقييم سائق إلى طابور المعالجة.");
        return Command::SUCCESS;
    }
}
