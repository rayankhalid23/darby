<?php

namespace App\Http\Resources\Api\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'subscription_type' => $this->subscription_type,
            'direction'         => $this->direction,
            'timing'            => $this->timing,
            'start_date'        => $this->start_date,
            'end_date'          => $this->end_date,
            'total_price'       => (float) $this->total_price,
            'status'            => $this->status,
            'status_ar'         => $this->translateStatus($this->status), // دالة مساعدة للترجمة
            'pickup_time'       => $this->pickup_time,
            'dropoff_time'      => $this->dropoff_time,
            'created_at'        => $this->created_at?->format('Y-m-d H:i:s'),

            // العلاقات (يتم إرجاعها فقط إذا تم تحميلها من السيرفس لتجنب أخطاء N+1)
            'driver' => $this->whenLoaded('driver', function () {
                return [
                    'id'    => $this->driver->id,
                    'name'  => $this->driver->user->full_name ?? 'غير محدد',
                    'phone' => $this->driver->user->phone_number ?? '',
                ];
            }),

            'school' => $this->whenLoaded('school', function () {
                return [
                    'id'   => $this->school->id,
                    'name' => $this->school->name,
                ];
            }),

            'children' => $this->whenLoaded('children', function () {
                return $this->children->map(function ($child) {
                    return [
                        'id'              => $child->id,
                        'name'            => $child->full_name,
                        'school_name'     => $child->school->name ?? null,
                        'price_per_child' => (float) $child->pivot->price_per_child,
                    ];
                });
            }),

            'contract' => $this->whenLoaded('contract', function () {
                return [
                    'id'              => $this->contract->id,
                    'contract_number' => $this->contract->contract_number,
       
                ];
            }),

            
        ];
    }

    private function translateStatus(?string $status): string
    {
        return match ($status) {
            'pending'   => 'قيد الانتظار',
            'accepted'  => 'مقبول',
            'rejected'  => 'مرفوض',
            'cancelled' => 'ملغي',
            'completed' => 'مكتمل',
            default     => 'غير معروف',
        };
    }
}