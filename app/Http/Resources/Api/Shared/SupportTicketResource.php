<?php

namespace App\Http\Resources\Api\Shared;

use App\Http\Controllers\Api\Shared\MediaController;
use App\Models\Shared\Invoice;
use App\Models\Shared\Trip;
use Bavix\Wallet\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (!$this->resource) {
            return [];
        }

        return [
            'id'              => (int) $this->id,
            'category'        => $this->category,
            'status'          => $this->status,
            'scope'           => $this->scope,
            'description'     => $this->description,
            'attachments'     => collect($this->attachments ?? [])
                ->map(fn ($path) => MediaController::urlFor($path))
                ->values(),
            'penalty_action'  => $this->penalty_action,
            'transfer_note'   => $this->transfer_note,
            'resolution_note' => $this->resolution_note,
            'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
            'closed_at'       => $this->closed_at?->format('Y-m-d H:i:s'),

            'creator' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id'   => (int) $this->user->id,
                    'name' => $this->user->full_name,
                    'role' => $this->creator_role,
                ] : null;
            }),

            'target' => $this->whenLoaded('targetUser', function () {
                return $this->targetUser ? [
                    'id'   => (int) $this->targetUser->id,
                    'name' => $this->targetUser->full_name,
                    'role' => $this->target_role,
                ] : null;
            }),

            'reference' => $this->whenLoaded('referenceable', function () {
                return $this->formatReferenceable();
            }),

            'assigned_admin' => $this->whenLoaded('assignedAdmin', function () {
                return $this->assignedAdmin ? [
                    'id'   => (int) $this->assignedAdmin->id,
                    'name' => $this->assignedAdmin->user?->full_name,
                ] : null;
            }),

            'messages' => $this->whenLoaded('messages', function () {
                return $this->messages->map(fn ($m) => [
                    'id'         => (int) $m->id,
                    'is_admin'   => (bool) $m->is_admin,
                    'admin_name' => $m->is_admin ? $m->admin?->user?->full_name : null,
                    'message'    => $m->message,
                    'created_at' => $m->created_at?->format('Y-m-d H:i:s'),
                ]);
            }),
        ];
    }

    protected function formatReferenceable(): ?array
    {
        $ref = $this->referenceable;
        if (!$ref) {
            return null;
        }

        return match (get_class($ref)) {
            Invoice::class => [
                'type'           => 'invoice',
                'id'             => (int) $ref->id,
                'invoice_number' => $ref->invoice_number,
                'amount'         => $ref->amount,
                'status'         => $ref->status,
            ],
            Transaction::class => [
                'type'   => 'transaction',
                'id'     => (int) $ref->id,
                'kind'   => $ref->type,
                'amount' => (float) $ref->amount / 100,
            ],
            Trip::class => [
                'type'       => 'trip',
                'id'         => (int) $ref->id,
                'trip_date'  => $ref->trip_date,
                'trip_type'  => $ref->trip_type,
                'status'     => $ref->status,
            ],
            default => null,
        };
    }
}
