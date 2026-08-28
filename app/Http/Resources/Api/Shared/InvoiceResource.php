<?php

namespace App\Http\Resources\Api\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => (int) $this->id,
            'invoice_number'   => $this->invoice_number,
            'amount'           => (float) $this->amount,
            'type'             => $this->type,
            'status'           => $this->status,
            'due_date'         => $this->due_date?->format('Y-m-d'),
            'subscription_type' => $this->subscription_type,
            'total_trips'      => (int) $this->total_trips,
            'completed_trips'  => (int) $this->completed_trips,
            'driver_absences'  => (int) $this->driver_absences,
            'student_absences' => (int) $this->student_absences,
            'calculated_amount' => $this->calculated_amount ? (float) $this->calculated_amount : null,
            'action_taken'     => $this->action_taken,
            'paid_at'          => $this->paid_at?->format('Y-m-d H:i:s'),
            'created_at'       => $this->created_at?->format('Y-m-d H:i:s'),

            $this->mergeWhen($this->relationLoaded('subscriptionRequest'), [
                'subscription_request' => [
                    'id'     => $this->subscriptionRequest?->id,
                    'status' => $this->subscriptionRequest?->status,
                ],
            ]),

            $this->mergeWhen($this->relationLoaded('driver'), [
                'driver' => [
                    'id'   => $this->driver?->id,
                    'name' => $this->driver?->user?->full_name ?? 'غير معروف',
                ],
            ]),

            $this->mergeWhen($this->relationLoaded('parent'), [
                'parent' => [
                    'id'   => $this->parent?->id,
                    'name' => $this->parent?->full_name ?? 'غير معروف',
                ],
            ]),
        ];
    }
}
