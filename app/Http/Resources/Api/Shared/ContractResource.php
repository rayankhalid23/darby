<?php

namespace App\Http\Resources\Api\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Shared\Clause;

class ContractResource extends JsonResource
{
    /**
     * تحويل الموديل إلى مصفوفة قابلة للإرسال كـ JSON
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'subscription_request_id' => $this->subscription_request_id,
            'price'                   => (float) ($this->total_price ?? 0),
            'pickup_time'             => $this->pickup_time,
            'dropoff_time'            => $this->dropoff_time,
            'max_waiting_time'        => $this->max_waiting_time,

            // نصوص الشروط مخزَّنة مباشرة كمصفوفة نصية على العقد نفسه (عمود clauses)
            'clauses'                 => $this->clauses ?? [],
            
            // جلب بيانات الأطفال ومدارسهم المرتبطة بالطلب
            'children'                => $this->relationLoaded('subscriptionRequest') ? 
                                         $this->subscriptionRequest->children->map(function ($child) {
                                             return [
                                                 'name'   => $child->full_name,
                                                 'school' => $child->school->name ?? 'غير محددة',
                                                 'grade'  => $child->grade,
                                             ];
                                         }) : null,
            
            'status'                  => $this->status,
            'status_text'             => $this->translateStatus($this->status),
            
            'created_at'              => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'              => $this->updated_at?->format('Y-m-d H:i:s'),

            'parent' => $this->relationLoaded('parentUser') && $this->parentUser ? [
                'id'    => $this->parentUser->id,
                'name'  => $this->parentUser->full_name,
                'phone' => $this->parentUser->phone_number,
            ] : null,

            'driver' => $this->relationLoaded('driverUser') && $this->driverUser ? [
                'id'    => $this->driverUser->id,
                'name'  => $this->driverUser->full_name,
                'phone' => $this->driverUser->phone_number,
            ] : null,
        ];
    }

    /**
     * دالة مساعدة لترجمة حالة العقد
     */
    private function translateStatus(string $status): string
    {
        return match ($status) {
            'draft'                   => 'مسودة',
            'pending_parent_approval' => 'بانتظار موافقة وتوقيع ولي الأمر',
            'active', 'activated'     => 'مفعّل وساري العمل به',
            'rejected'                => 'تم رفض العقد من قِبل ولي الأمر',
            'terminated'              => 'تم إنهاء العقد',
            default                   => 'حالة غير معروفة',
        };
    }
}