<?php

namespace App\Http\Resources\Api\Parent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // حماية إضافية في حال كان الكائن فارغاً
        if (!$this->resource) {
            return [];
        }

        return [
            'id'             => (int) $this->id,
            'description'    => $this->description,
            'status'         => $this->status,
            'action_taken'   => $this->action_taken,
            'action_details' => $this->action_details,
            'created_at'     => $this->created_at?->format('Y-m-d H:i:s'),
            'resolved_at'    => $this->resolved_at?->format('Y-m-d H:i:s'),
            
            'driver'         => $this->whenLoaded('driver', function () {
                return [
                    'id'   => (int) $this->driver?->id,
                    'name' => $this->driver?->user?->full_name ?? 'غير معروف',
                ];
            }),

            'submitted_by'   => $this->whenLoaded('submittedBy', function () {
                if (!$this->submittedBy) return null;
                return [
                    'id'   => (int) $this->submittedBy->id,
                    'name' => $this->submittedBy->user?->full_name ?? 'غير معروف',
                ];
            }),

            // ✅ تم التصحيح هنا باستخدام ?-> لحماية التطبيق
            'trip'           => $this->whenLoaded('trip', function () {
                if (!$this->trip) return null;
                return [
                    'id'        => (int) $this->trip?->id,
                    'trip_date' => $this->trip?->trip_date,
                    'trip_type' => $this->trip?->trip_type,
                    'status'    => $this->trip?->status,
                ];
            }),
            
            // ✅ تم التصحيح هنا: إذا كانت الشكوى معلقة pending ستعود كـ null بدون انهيار الكود
            'resolved_by'    => $this->whenLoaded('resolvedBy', function () {
                if (!$this->resolvedBy) return null;
                return [
                    'id'   => (int) $this->resolvedBy?->id,
                    'name' => $this->resolvedBy?->name ?? 'غير معروف',
                ];
            }),
        ];
    }
}