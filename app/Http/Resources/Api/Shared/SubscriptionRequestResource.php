<?php

namespace App\Http\Resources\Api\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'status'           => $this->status,
            'status_ar'        => $this->translateStatus($this->status),

            // معلومات ولي الأمر (نأخذها من علاقة المستخدم المرتبط بطلب الاشتراكات أو الأب إن وجد)
            'parent' => $this->whenLoaded('parent', function () {
                return [
                    'id'    => $this->parent->id,
                    'name'  => $this->parent->full_name ?? 'غير محدد',
                    'phone' => $this->parent->phone_number ?? '',
                ];
            }),

            'children_count'   => $this->whenLoaded('children', function () {
                return $this->children->count();
            }, 0),

            'total_price'      => (float) $this->total_price,
            
            // الاسم الفلاني: هل حقل سبب الرفض موجود في جدول طلبات الاشتراكات؟ 
            // إذا كان اسمه في قاعدة البيانات 'rejection_reason' تركناه هكذا، وإذا كان اسماً آخر أخبرني به.
            'rejection_reason' => $this->rejection_reason ?? null,

            'created_at'       => $this->created_at?->format('Y-m-d H:i:s'),

            // العلاقات الإضافية (مثل السائق والمدرسة والعقود والأطفال بتفاصيلهم إن احتياج الأمر)
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
                        'full_name'       => $child->full_name,
                        'school'          => [
                            'id'   => $child->school->id ?? null,
                            'name' => $child->school->name ?? null,
                        ],
                        'subscription'    => [
                            'type'       => $this->subscription_type,
                            'direction'  => $this->direction,
                            'timing'     => $this->timing,
                            'start_date' => $this->start_date,
                            'end_date'   => $this->end_date,
                            // الاسم الفلاني: هل حقل عدد الأيام 'days_count' موجود في الجدول؟ إذا لم يكن موجوداً أخبرني باسم العمود أو كيف تحسبه.
                            'days_count' => $this->days_count ?? null, 
                        ],
                        'pickup_address'  => [
                            // الاسم الفلاني: تأكد من أسماء أعمدة الإحداثيات والعناوين في جدولك (هل هي label, lat, lng أم أسماء أخرى؟)
                            'label' => $this->pickup_label ?? 'منزل الطفل',
                            'lat'   => (float) ($this->pickup_lat ?? 0),
                            'lng'   => (float) ($this->pickup_lng ?? 0),
                        ],
                        'dropoff_address' => [
                            'label' => $this->dropoff_label ?? 'المدرسة',
                            'lat'   => (float) ($this->dropoff_lat ?? 0),
                            'lng'   => (float) ($this->dropoff_lng ?? 0),
                        ],
                        'price_per_child' => (float) $child->pivot->price_per_child,
                        // الاسم الفلاني: هل حقل ملاحظات الطفل موجود في الجدول باسم 'notes' أو 'child_notes'؟ أخبرني بالاسم الصحيح إن لم يكن موجوداً.
                        'child_notes'     => $child->pivot->notes ?? 'لا توجد ملاحظات',
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