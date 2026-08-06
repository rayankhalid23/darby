<?php

namespace App\Http\Resources\API\Trip; // 👈 المسار الجديد الصحيح

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_id' => $this->driver_id,
            'trip_type'        => $this->trip_type === 'Morning' ? 'صباحية' : 'مسائية',
            'status' => $this->translateStatus($this->status),
'scheduled_start'  => $this->scheduled_start_time, 
        'actual_start'     => $this->actual_start_time,   
        'actual_end'       => $this->completed_at, // أو الحقل المعتمد للنهاية
            
            'driver' => $this->whenLoaded('driver', function() {
                return [
                    'id' => $this->driver->id,
                    'name' => $this->driver->user->name ?? 'غير معروف',
                    'phone' => $this->driver->user->phone ?? '',
                    'current_location' => [
                        'lat' => (float) $this->driver->current_lat,
                        'lng' => (float) $this->driver->current_lng,
                    ]
                ];
            }),

            'tracking_points' => $this->whenLoaded('trackingLogs', function() {
                return $this->trackingLogs->map(function($point) {
                    return [
                        'lat' => (float) $point->latitude,
                        'lng' => (float) $point->longitude,
                        'speed' => (float) $point->speed,
                        'time' => Carbon::parse($point->recorded_at)->format('H:i:s'),
                    ];
                });
            }),
        ];
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            'in_progress'          => 'جاري التشغيل حالياً',
            'completed'            => 'مكتملة ومنتهية',
            'suspended_breakdown'  => 'متوقفة مؤقتاً (عطل)',
            'pending'              => 'معلقة',
            default                => 'معلقة',
        };
    }
}