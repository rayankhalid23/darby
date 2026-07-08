<?php

namespace App\Observers;

use App\Models\Shared\Contract;
use App\Models\Shared\Route;
use App\Models\Shared\Trip;
use App\Models\Driver\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContractObserver
{
    public function created(Contract $contract): void
    {
        try {
            $this->generateRoutesAndTrips($contract);
        } catch (\Exception $e) {
            Log::error("فشل إنشاء المسار تلقائياً للعقد {$contract->contract_number}: " . $e->getMessage());
        }
    }

    protected function generateRoutesAndTrips(Contract $contract): void
    {
        $contract->load(['activeSubscriptions.child', 'driver.vehicle']);

        if ($contract->activeSubscriptions->isEmpty()) {
            return;
        }

        $timing = $contract->timing;
        $direction = $contract->direction;
        $driverId = $contract->driver_id;
        $vehicle = $contract->driver?->vehicle;
        $vehicleId = $vehicle?->id;

        $startDate = $contract->start_date ?? now()->toDateString();
        $startTime = $contract->pickup_time ?? '07:00:00';
        $endTime = $contract->dropoff_time ?? '14:00:00';

        $routeTypes = [];
        if (in_array($timing, ['MORNING', 'BOTH'])) {
            $routeTypes[] = ['type' => 'Morning', 'time' => $startTime, 'label' => 'صباحي - توصيل للمدرسة'];
        }
        if (in_array($timing, ['EVENING', 'BOTH'])) {
            $routeTypes[] = ['type' => 'Afternoon', 'time' => $endTime, 'label' => 'مسائي - توصيل للمنزل'];
        }

        foreach ($routeTypes as $rt) {
            $route = Route::create([
                'driver_id'   => $driverId,
                'vehicle_id'  => $vehicleId,
                'contract_id' => $contract->id,
                'route_name'  => "{$rt['label']} - {$contract->contract_number}",
                'route_type'  => $rt['type'],
                'start_time'  => $rt['time'],
                'status'      => 'Active',
            ]);

            $tripDate = $startDate instanceof \Carbon\Carbon ? $startDate->toDateString() : $startDate;

            Trip::create([
                'driver_id'           => $driverId,
                'route_id'            => $route->id,
                'trip_type'           => $rt['type'],
                'status'              => 'planned',
                'scheduled_at'        => $tripDate . ' ' . $rt['time'],
                'scheduled_start_time'=> $tripDate . ' ' . $rt['time'],
                'trip_date'           => $tripDate,
            ]);
        }
    }
}
