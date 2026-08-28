<?php

namespace App\Services\Shared;

use App\Models\Driver\Driver;
use App\Models\Parent\Address;
use App\Models\Parent\ParentModel;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\LocationChangeRequest;
use App\Models\User;
use App\Services\Notification\NotificationService;
use App\Services\Trip\MasterRouteStopSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Throwable;

/**
 * يدير طلبات ولي الأمر لتغيير موقع استلام (pickup) أو تسليم (dropoff) طفله
 * ضمن اشتراك نشط، مع دورة موافقة/رفض من السائق قبل تفعيل التغيير فعلياً.
 */
class LocationChangeService
{
    protected MasterRouteStopSyncService $routeStopSyncService;
    protected NotificationService $notificationService;

    public function __construct(
        MasterRouteStopSyncService $routeStopSyncService,
        NotificationService $notificationService
    ) {
        $this->routeStopSyncService = $routeStopSyncService;
        $this->notificationService = $notificationService;
    }

    /**
     * يجهّز خيارات الاختيار لولي الأمر: مواقعه المحفوظة + اشتراكاته النشطة (الرحلة/الطفل/السائق)
     * التي يمكنه طلب تغيير نقطة الاستلام أو التسليم فيها.
     */
    public function getChangeableOptions(int $userId): array
    {
        $parent = ParentModel::where('user_id', $userId)->first();
        if (!$parent) {
            throw new Exception('هذا الحساب غير مسجل كولي أمر في النظام.');
        }

        $parentIds = array_values(array_unique(array_filter([$userId, $parent->id])));
        $addresses = Address::whereIn('parent_id', $parentIds)->orderByDesc('id')->get();

        $activeSubscriptions = ActiveSubscription::where(function ($q) use ($userId, $parent) {
                $q->where('parent_id', $parent->id)->orWhere('parent_id', $userId);
            })
            ->where('status', 'active')
            ->with(['child:id,full_name,photo_url', 'driver.user:id,full_name', 'route:id,route_name,route_type,shift_slot,start_time'])
            ->get()
            ->map(function (ActiveSubscription $sub) {
                return [
                    'active_subscription_id' => $sub->id,
                    'child'                  => [
                        'id'   => $sub->child?->id,
                        'name' => $sub->child?->full_name,
                        'photo_url' => $sub->child?->photo_url,
                    ],
                    'driver' => [
                        'id'   => $sub->driver_id,
                        'name' => $sub->driver?->user?->full_name,
                    ],
                    'trip' => [
                        'timing'         => $sub->route?->route_type ?? $sub->route?->shift_slot,
                        'direction'      => $sub->route?->shift_slot ?? $sub->route?->route_type,
                        'timing_text'    => $sub->route?->route_type ?? null,
                        'direction_text' => $sub->route?->shift_slot ?? null,
                    ],
                    'current_pickup' => [
                        'lat'   => $sub->pickup_lat,
                        'lng'   => $sub->pickup_lng,
                        'label' => $sub->pickup_label,
                    ],
                    'current_dropoff' => [
                        'lat'   => $sub->dropoff_lat,
                        'lng'   => $sub->dropoff_lng,
                        'label' => $sub->dropoff_label,
                    ],
                ];
            });

        return [
            'addresses'            => $addresses,
            'active_subscriptions' => $activeSubscriptions,
        ];
    }

    /**
     * إنشاء طلب تغيير موقع جديد من ولي الأمر، بانتظار موافقة السائق.
     */
    public function requestChange(
        int $userId,
        int $activeSubscriptionId,
        string $pointType,
        ?int $addressId,
        ?float $lat,
        ?float $lng,
        ?string $label
    ): LocationChangeRequest {
        $parent = ParentModel::where('user_id', $userId)->first();
        if (!$parent) {
            throw new Exception('هذا الحساب غير مسجل كولي أمر في النظام.');
        }

        $activeSub = ActiveSubscription::where('id', $activeSubscriptionId)
            ->where(function ($q) use ($userId, $parent) {
                $q->where('parent_id', $parent->id)->orWhere('parent_id', $userId);
            })
            ->first();

        if (!$activeSub) {
            throw new Exception('الاشتراك النشط غير موجود أو لا تملك صلاحية الوصول إليه.');
        }

        if ($activeSub->status !== 'active') {
            throw new Exception('لا يمكن طلب تغيير الموقع لاشتراك غير نشط.');
        }

        $parentIds = array_values(array_unique(array_filter([$userId, $parent->id])));
        if ($addressId) {
            $address = Address::where('id', $addressId)->whereIn('parent_id', $parentIds)->first();
            if (!$address) {
                throw new Exception('العنوان المحدد غير موجود ضمن مواقعك المحفوظة.');
            }
            $newLat   = (float) $address->lat;
            $newLng   = (float) $address->lng;
            $newLabel = $address->label;
        } else {
            if ($lat === null || $lng === null) {
                throw new Exception('يجب تحديد عنوان محفوظ أو إحداثيات الموقع الجديد.');
            }
            $newLat     = $lat;
            $newLng     = $lng;
            $newLabel   = $label;
            $addressId  = null;
        }

        return DB::transaction(function () use ($activeSub, $parent, $pointType, $addressId, $newLat, $newLng, $newLabel, $userId) {
            $existingPending = LocationChangeRequest::where('active_subscription_id', $activeSub->id)
                ->where('point_type', $pointType)
                ->where('status', LocationChangeRequest::STATUS_PENDING)
                ->first();

            if ($existingPending) {
                throw new Exception('يوجد بالفعل طلب تغيير موقع معلّق لنفس النقطة بانتظار رد السائق.');
            }

            $changeRequest = LocationChangeRequest::create([
                'active_subscription_id' => $activeSub->id,
                'child_id'               => $activeSub->child_id,
                'parent_id'              => $userId,
                'driver_id'              => $activeSub->driver_id,
                'point_type'             => $pointType,
                'new_address_id'         => $addressId,
                'new_lat'                => $newLat,
                'new_lng'                => $newLng,
                'new_label'              => $newLabel,
                'status'                 => LocationChangeRequest::STATUS_PENDING,
            ]);

            $changeRequest->loadMissing(['child', 'driver.user']);

            try {
                $driverUser = $changeRequest->driver?->user;
                if ($driverUser) {
                    $childName = $changeRequest->child?->full_name ?? 'الطفل';
                    $pointLabel = $pointType === LocationChangeRequest::POINT_TYPE_PICKUP ? 'الاستلام' : 'التسليم';
                    $this->notifyUser(
                        $driverUser,
                        'طلب تغيير موقع 📍',
                        "طلب ولي أمر الطفل ({$childName}) تغيير موقع {$pointLabel} إلى: " . ($newLabel ?? 'موقع جديد') . '. يرجى المراجعة والموافقة أو الرفض.',
                        'location_change_requested',
                        (string) $changeRequest->id,
                        ['child_name' => $childName]
                    );
                }
            } catch (Throwable $e) {
                Log::warning("فشل إرسال إشعار طلب تغيير الموقع ID {$changeRequest->id}: " . $e->getMessage());
            }

            return $changeRequest;
        });
    }

    /**
     * موافقة/رفض السائق على طلب تغيير الموقع.
     */
    public function respondToChange(int $driverUserId, int $requestId, bool $approve, ?string $rejectionReason = null): LocationChangeRequest
    {
        $driver = Driver::where('user_id', $driverUserId)->first();
        if (!$driver) {
            throw new Exception('هذا الحساب غير مسجل كسائق في النظام.');
        }

        $changeRequest = LocationChangeRequest::where('id', $requestId)
            ->where('driver_id', $driver->id)
            ->first();

        if (!$changeRequest) {
            throw new Exception('طلب تغيير الموقع غير موجود أو لا يخصك.');
        }

        if ($changeRequest->status !== LocationChangeRequest::STATUS_PENDING) {
            throw new Exception('تم الرد على هذا الطلب مسبقاً.');
        }

        return DB::transaction(function () use ($changeRequest, $approve, $rejectionReason) {
            $activeSub = ActiveSubscription::lockForUpdate()->find($changeRequest->active_subscription_id);
            if (!$activeSub) {
                throw new Exception('الاشتراك النشط المرتبط بهذا الطلب لم يعد موجوداً.');
            }

            if ($approve) {
                if ($changeRequest->point_type === LocationChangeRequest::POINT_TYPE_PICKUP) {
                    $activeSub->update([
                        'pickup_lat'   => $changeRequest->new_lat,
                        'pickup_lng'   => $changeRequest->new_lng,
                        'pickup_label' => $changeRequest->new_label,
                    ]);

                    try {
                        $this->routeStopSyncService->updateChildHomeStop(
                            $activeSub->child_id,
                            $activeSub->driver_id,
                            $changeRequest->new_lat,
                            $changeRequest->new_lng,
                            $changeRequest->new_label
                        );
                    } catch (Throwable $e) {
                        Log::warning("فشل مزامنة مسار محطة الاستلام بعد الموافقة على الطلب ID {$changeRequest->id}: " . $e->getMessage());
                    }
                } else {
                    // نقطة التسليم مرتبطة عادة بمحطة مدرسة مشتركة بين عدة أطفال (route_stops بنوع school)،
                    // لذا لا تُعدَّل هذه المحطة المشتركة هنا. يُحدَّث سجل active_subscriptions فقط كمصدر
                    // الحقيقة لنقطة تسليم هذا الطفل تحديداً، ليُعتمد عليه عند أي تطوير لاحق لمحطات مخصصة لكل طفل.
                    $activeSub->update([
                        'dropoff_lat'   => $changeRequest->new_lat,
                        'dropoff_lng'   => $changeRequest->new_lng,
                        'dropoff_label' => $changeRequest->new_label,
                    ]);
                }

                $changeRequest->update([
                    'status'       => LocationChangeRequest::STATUS_APPROVED,
                    'responded_at' => now(),
                ]);
            } else {
                $changeRequest->update([
                    'status'           => LocationChangeRequest::STATUS_REJECTED,
                    'rejection_reason' => $rejectionReason,
                    'responded_at'     => now(),
                ]);
            }

            $changeRequest->loadMissing(['child', 'parent', 'driver.user']);

            try {
                $parentUser = $changeRequest->parent;
                if ($parentUser) {
                    $childName = $changeRequest->child?->full_name ?? 'الطفل';
                    if ($approve) {
                        $this->notifyUser(
                            $parentUser,
                            'تمت الموافقة على تغيير الموقع 🟢',
                            "وافق السائق على تغيير موقع " . ($changeRequest->point_type === LocationChangeRequest::POINT_TYPE_PICKUP ? 'استلام' : 'تسليم') . " الطفل ({$childName})، وتم تحديث المسار.",
                            'location_change_approved',
                            (string) $changeRequest->id,
                            ['child_name' => $childName]
                        );
                    } else {
                        $this->notifyUser(
                            $parentUser,
                            'تم رفض طلب تغيير الموقع 🔴',
                            "عذراً، رفض السائق طلب تغيير موقع الطفل ({$childName})." . ($rejectionReason ? " السبب: {$rejectionReason}" : ''),
                            'location_change_rejected',
                            (string) $changeRequest->id,
                            ['child_name' => $childName]
                        );
                    }
                }
            } catch (Throwable $e) {
                Log::warning("فشل إرسال إشعار الرد على طلب تغيير الموقع ID {$changeRequest->id}: " . $e->getMessage());
            }

            return $changeRequest->fresh(['child', 'parent', 'driver.user', 'activeSubscription']);
        });
    }

    public function getParentRequests(int $userId)
    {
        return LocationChangeRequest::where('parent_id', $userId)
            ->with(['child', 'driver.user', 'activeSubscription'])
            ->orderByDesc('id')
            ->get();
    }

    public function getDriverRequests(int $userId, ?string $status = null)
    {
        $driver = Driver::where('user_id', $userId)->first();
        if (!$driver) {
            throw new Exception('هذا الحساب غير مسجل كسائق في النظام.');
        }

        $query = LocationChangeRequest::where('driver_id', $driver->id)
            ->with(['child', 'parent', 'activeSubscription']);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('id')->get();
    }

    private function notifyUser(User|null $user, string $title, string $message, string $type, ?string $entityId = null, array $extra = []): void
    {
        if ($user) {
            $this->notificationService->sendToUser($user, $type, array_merge([
                'title'       => $title,
                'message'     => $message,
                'entity_type' => 'location_change_request',
                'entity_id'   => $entityId,
            ], $extra));
        }
    }
}
