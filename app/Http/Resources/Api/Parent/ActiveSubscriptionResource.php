<?php


namespace App\Http\Resources\Api\Parent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActiveSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status ?? 'active',
            'statusLabel' => $this->status == 'active' ? 'نشط' : 'غير نشط',

            'child' => [
                'id' => $this->child->id ?? null,
                'name' => $this->child->name ?? null,
                // إضافة رابط أو مسار صورة الطفل إذا وجدت في قاعدة البيانات
                'avatar' => $this->child->avatar ?? null, 
                'avatarInitials' => mb_substr($this->child->name ?? '', 0, 2),
                // مدرسة كل طفل كما طلبت
                'schoolName' => $this->child->school_name ?? $this->school_name ?? 'مدرسة الفلاح',
            ],

            'driver' => [
                'id' => $this->driver->id ?? null,
                // اسم السائق كما طلبت
                'name' => $this->driver->name ?? null,
                'phone' => $this->driver->phone ?? null,
                'rating' => (float) ($this->driver->rating ?? 5.0),
                'vehicle' => [
                    'model' => $this->driver->vehicle_model ?? 'تويوتا هايس',
                    'color' => $this->driver->vehicle_color ?? 'أبيض',
                    'plateNumber' => $this->driver->plate_number ?? '12345 طرابلس',
                ]
            ],

            'schedule' => [
                'shift' => (int) ($this->shift ?? 1),
                'shiftLabel' => ($this->shift == 1) ? 'صباحي' : 'مسائي',
                'pickupZoneName' => $this->pickup_zone_name ?? 'حي الأندلس',
                'schoolName' => $this->school_name ?? 'مدرسة الفلاح',
            ],

            'billing' => [
                'subscriptionType' => $this->subscription_type ?? 'monthly',
                // السعر الإجمالي
                'totalPrice' => (float) ($this->price ?? 89),
                // سعر كل طفل (في حال كان الطلب يحتوي على سعر أساسي للطفل أو مخصص)
                'childPrice' => (float) ($this->child_price ?? $this->price ?? 89),
                'currency' => 'SAR',
                // تاريخ البدء كما طلبت
                'startsAt' => $this->starts_at ? optional($this->starts_at)->toIso8601String() : null,
                'endsAt' => $this->ends_at ? optional($this->ends_at)->toIso8601String() : null,
                'remainingDays' => (int) ($this->remaining_days ?? 14),
                'autoRenew' => (bool) ($this->auto_renew ?? true),
                'paymentMethod' => $this->payment_method ?? 'card',
            ],

            'requestId' => $this->request_id ?? $this->id,
            'cancelReason' => $this->cancel_reason ?? null,
            'cancelledAt' => $this->cancelled_at ? optional($this->cancelled_at)->toIso8601String() : null,
            'createdAt' => $this->created_at ? optional($this->created_at)->toIso8601String() : null,
        ];
    }
}