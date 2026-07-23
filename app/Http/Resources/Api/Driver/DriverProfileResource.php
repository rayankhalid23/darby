<?php

namespace App\Http\Resources\Api\Driver;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class DriverProfileResource extends JsonResource
{
    /**
     * تحويل كائن السائق إلى مصفوفة JSON احترافية وموحدة
     */
    public function toArray(Request $request): array
    {
        $user = $this->user;

        // جلب آخر طلب تعديل معلق للسائق (إن وجد) لإعلام التطبيق بالبيانات التي تحت المراجعة
        $pendingChange = DB::table('driver_profile_changes')
            ->where('driver_id', $this->id)
            ->where('status', 'Pending')
            ->latest()
            ->first();

        return [
            'driver_id'         => $this->id,
            'user_id'           => $this->user_id,
            'role_id'           => $user->role_id ?? null,
            'full_name'         => $user->full_name ?? '',
            'email'             => $user->email ?? '',
            'phone_number'      => $user->phone_number ?? '',
            'alternative_phone' => $user->alternative_phone,
            'avatar_url'        => $user->avatar_url ? url($user->avatar_url) : null,
            'gender'            => $this->gender,
            'account_status'    => $this->status, // Pending, Active, Incomplete, Rejected
            'is_active'         => $user->is_active ?? 0,
            
            // البيانات القانونية الحالية في السيرفر
            'legal_data' => [
                'national_id'    => $this->national_id,
                'license_number' => $this->license_number,
                'license_expiry' => $this->license_expiry,
            ],

            // جلب المركبات المرتبطة بالسائق (محملة عبر الريسورس المخصص لها إن وجد أو كمصفوفة مباشرة)
            'vehicles' => $this->vehicles->map(function ($vehicle) {
                return [
                    'id'                => $vehicle->id,
                    'plate_number'      => $vehicle->plate_number,
                    'brand'             => $vehicle->brand,
                    'model'             => $vehicle->model,
                    'year'              => $vehicle->year,
                    'color'             => $vehicle->color,
                    'type'              => $vehicle->type,
                    'capacity_manual'   => $vehicle->capacity_manual,
                    'vehicle_image_url' => $vehicle->vehicle_image_url ? url($vehicle->vehicle_image_url) : null,
                    'has_ac'            => (bool) $vehicle->has_ac,
                    'status'            => $vehicle->status, // Pending, Active, Rejected
                    'is_verified'       => (bool) $vehicle->is_verified,
                ];
            }),

            // 🚀 هندسة ذكية لواجهة التطبيق: إعلام التطبيق بوجود تحديثات بانتظار موافقة الأدمن
            'meta_sync' => [
                'has_pending_changes' => !empty($pendingChange),
                'email_change_pending' => (bool) ($user->email_change_pending ?? false),
                'pending_data'        => $pendingChange ? json_decode($pendingChange->new_values, true) : null,
            ],
            
            'created_at'        => $this->created_at ? $this->created_at->toDateTimeString() : null,
        ];
    }
}