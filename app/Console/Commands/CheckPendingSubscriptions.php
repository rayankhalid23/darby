<?php

namespace App\Console\Commands;

use App\Services\Shared\SubscriptionRequestService;
use Illuminate\Console\Command;

class CheckPendingSubscriptions extends Command
{
    protected $signature   = 'subscriptions:check-pending';
    protected $description = 'إلغاء الطلبات المعلقة التي انتهى تاريخها أو لا تتوفر مقاعد كافية للسائق';

    public function handle(SubscriptionRequestService $service): int
    {
        $this->info('جاري فحص الطلبات المعلقة...');

        $stats = $service->cancelStaleAndOvercapacityRequests();

        $this->info("مُلغى (منتهي التاريخ): {$stats['cancelled_expired']}");
        $this->info("مُلغى (لا مقاعد):       {$stats['cancelled_no_seats']}");
        $this->info("سليمة:                   {$stats['healthy']}");

        return Command::SUCCESS;
    }
}
