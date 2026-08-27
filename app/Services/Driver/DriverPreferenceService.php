<?php

namespace App\Services\Driver;

use App\Models\Driver\Driver;
use App\Models\Driver\DriverSeatSlot;
use App\Enums\driver\DriverShift;
use App\Enums\Shared\SchoolStage;
use App\Enums\Shared\SubscriptionDuration;
use App\Models\Shared\Zone;
use App\Models\Shared\Municipality;
use Illuminate\Support\Facades\DB;
use Exception;

class DriverPreferenceService
{
    /**
     * جلب التفضيلات مع العلاقات الهرمية (المنطقة -> البلدية الفرعية -> البلدية الكبرى)
     */
    public function getPreferences(Driver $driver): Driver
    {
        return $driver->load(['zones.subMunicipality.municipality', 'seatSlots']);
    }

    /**
     * تحديث التفضيلات الشاملة مع فحص شرط "البلدية الفرعية الموحدة"
     */
    public function updatePreferences(Driver $driver, array $data): Driver
    {
        return DB::transaction(function () use ($driver, $data) {
            $driverUpdate = [];
            foreach (['morning_go', 'morning_return', 'afternoon_go', 'afternoon_return'] as $slot) {
                if (array_key_exists($slot, $data)) {
                    $driverUpdate[$slot] = (bool) $data[$slot];
                }
            }
            if (array_key_exists('subscription_type', $data)) {
                $driverUpdate['subscription_type'] = $data['subscription_type'];
            }
            if (array_key_exists('school_stages', $data)) {
                $driverUpdate['school_stages'] = $data['school_stages'];
            }

            if (!empty($driverUpdate)) {
                $driver->update($driverUpdate);
            }

            if (array_key_exists('zones', $data)) {
                $zoneIds = $data['zones'] ?? [];

                if (!empty($zoneIds)) {
                    $subMunicipalityIds = Zone::whereIn('id', $zoneIds)
                        ->pluck('sub_municipality_id')
                        ->filter()
                        ->unique();

                    if ($subMunicipalityIds->count() > 1) {
                        throw new Exception('عذراً، يجب أن تكون جميع المناطق المختارة تابعة لنفس البلدية الفرعية.');
                    }
                }

                $driver->zones()->sync($zoneIds);
            }

            // مزامنة سجلات المقاعد مع الفترات المفعّلة
            $this->syncSeatSlots($driver);

            return $driver->fresh(['zones.subMunicipality.municipality', 'seatSlots']);
        });
    }

    /**
     * مزامنة جدول driver_seat_slots مع الفترات المفعّلة للسائق
     * - إنشاء سجل للفترات الجديدة بـ total_seats = capacity_manual
     * - رفض تعطيل فترة بها مقاعد محجوزة
     */
    private function syncSeatSlots(Driver $driver): void
    {
        $vehicle   = $driver->vehicle;
        $capacity  = $vehicle?->capacity_manual ?? 0;

        $activeSlots = $driver->getActiveSlots();

        foreach (DriverSeatSlot::ALL_SLOTS as $slot) {
            $isActive = in_array($slot, $activeSlots);
            $existing = DriverSeatSlot::where('driver_id', $driver->id)
                ->where('slot', $slot)
                ->first();

            if ($isActive) {
                if (!$existing) {
                    // إنشاء سجل جديد
                    DriverSeatSlot::create([
                        'driver_id'      => $driver->id,
                        'slot'           => $slot,
                        'total_seats'    => $capacity,
                        'reserved_seats' => 0,
                    ]);
                } else {
                    // تحديث الطاقة الكلية إذا تغيرت سعة المركبة
                    $existing->update(['total_seats' => $capacity]);
                }
            } else {
                // تعطيل فترة — رفض إذا بها محجوزات
                if ($existing && $existing->reserved_seats > 0) {
                    throw new Exception(
                        "لا يمكن تعطيل فترة [{$slot}] لأن بها {$existing->reserved_seats} مقاعد محجوزة حالياً."
                    );
                }
                // حذف السجل إذا لم يكن بها محجوزات
                if ($existing) {
                    $existing->delete();
                }
            }
        }
    }

    /**
     * إضافة منطقة واحدة مع التحقق من مطابقتها للبلدية الفرعية للمناطق الحالية
     */
    public function addZoneToDriver(Driver $driver, int $zoneId): Driver
    {
        $targetZone = Zone::findOrFail($zoneId);
        $currentZones = $driver->zones;

        if ($currentZones->isNotEmpty()) {
            $currentSubMunicipalityId = $currentZones->first()->sub_municipality_id;

            if ($targetZone->sub_municipality_id !== $currentSubMunicipalityId) {
                throw new Exception('لا يمكن إضافة هذه المنطقة؛ لأنها تتبع بلدية فرعية مختلفة.');
            }
        }

        $driver->zones()->syncWithoutDetaching([$zoneId]);

        return $driver->load(['zones.subMunicipality.municipality', 'seatSlots']);
    }

    public function removeZoneFromDriver(Driver $driver, int $zoneId): Driver
    {
        $driver->zones()->detach($zoneId);
        return $driver->load(['zones.subMunicipality.municipality', 'seatSlots']);
    }

    /**
     * جلب هيكل البيانات الجغرافي لبناء القوائم المنسدلة
     */
    public function getSystemDefaults(): array
    {
        return [
            'available_shift_slots' => DriverShift::detailedSlots(),
            'available_subscription_types' => SubscriptionDuration::driverOptions(),
            'available_school_stages'      => SchoolStage::gradeRanges(),
            'geography_tree' => Municipality::with('subMunicipalities.zones')->get()->map(fn($municipality) => [
                'id'   => $municipality->id,
                'name' => $municipality->name,
                'sub_municipalities' => $municipality->subMunicipalities->map(fn($sub) => [
                    'id'    => $sub->id,
                    'name'  => $sub->name,
                    'zones' => $sub->zones->map(fn($zone) => [
                        'id' => $zone->id,
                        'name' => $zone->name
                    ])
                ])
            ])
        ];
    }
}