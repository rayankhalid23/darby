<?php

namespace App\Console\Commands;

use App\Services\Trip\DailyTripGenerationService;
use Illuminate\Console\Command;

class GenerateDailyTrips extends Command
{
    protected $signature = 'trips:generate-daily';
    protected $description = 'يولّد الرحلات اليومية (Daily Trips) للمسارات التي دخلت نافذة T-30 دقيقة قبل وقت انطلاقها';

    public function handle(DailyTripGenerationService $service): int
    {
        $result = $service->generateDueTrips();

        $this->info("تم فحص {$result['checked']} مسار — تم توليد {$result['generated']} رحلة، وتخطي {$result['skipped']}.");

        return Command::SUCCESS;
    }
}
