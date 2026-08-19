<?php

namespace App\Console\Commands;

use App\Services\Driver\DriverExpiryNotificationService;
use Illuminate\Console\Command;

class CheckDriverDocumentExpiry extends Command
{
    protected $signature   = 'drivers:check-document-expiry';
    protected $description = 'فحص يومي لتواريخ انتهاء رخصة القيادة ووثيقة التأمين للسائقين، وإرسال تذكيرات/تنبيهات انتهاء';

    public function handle(DriverExpiryNotificationService $service): int
    {
        $this->info('جاري فحص تواريخ انتهاء الرخص والتأمين...');

        $stats = $service->run();

        $this->info("تذكيرات الرخصة المُرسلة: {$stats['license_reminders']}");
        $this->info("رخص أُعلن انتهاؤها الآن:  {$stats['license_expired']}");
        $this->info("تذكيرات التأمين المُرسلة: {$stats['insurance_reminders']}");
        $this->info("تأمينات أُعلن انتهاؤها الآن: {$stats['insurance_expired']}");

        return Command::SUCCESS;
    }
}
