<?php

namespace App\Services\Shared;

use App\Models\Shared\SubscriptionRequest;
use App\Models\Parent\ParentModel;
use App\Models\Driver\Driver;
use App\Models\Shared\Contract;
use App\Models\Shared\ActiveSubscription;
use App\Services\Shared\ContractService;
use App\Notifications\CustomDatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SubscriptionRequestService
{
    protected ContractService $contractService;

    public function __construct(ContractService $contractService)
    {
        $this->contractService = $contractService;
    }

    // ============================================================
    // إنشاء طلب اشتراك
    // ============================================================

    public function createRequest(array $data, $userId)
    {
        $driverId = $data['driver_id'] ?? null;

        $parent = ParentModel::where('user_id', $userId)->with('user')->first();
        if (!$parent) {
            throw new Exception('هذا الحساب غير مسجل كولي أمر في النظام.');
        }

        $driver = Driver::with('user')->find($driverId);
        if (!$driver) {
            throw new Exception("السائق المحدد غير موجود في النظام.");
        }

        return DB::transaction(function () use ($data, $parent, $driver) {
            $totalPrice = collect($data['children'])->sum(fn($c) => $c['price_per_child'] ?? 0);

            $subscriptionRequest = SubscriptionRequest::create([
                'parent_id'         => $parent->id,
                'driver_id'         => $driver->id,
                'school_id'         => $data['school_id'] ?? null,
                'subscription_type' => $data['subscription_type'] ?? 'monthly',
                'direction'         => $data['direction'],
                'timing'            => $data['timing'],
                'start_date'        => $data['start_date'],
                'end_date'          => $data['end_date'] ?? null,
                'days_count'        => $data['days_count'] ?? null,
                'total_price'       => $totalPrice,
                'pickup_time'       => $data['pickup_time'] ?? null,
                'dropoff_time'      => $data['dropoff_time'] ?? null,
                'max_waiting_time'  => $data['max_waiting_time'] ?? 15,
                'status'            => SubscriptionRequest::STATUS_PENDING,
                'notes'             => $data['notes'] ?? null,
                'children_count'    => count($data['children']),
            ]);

            foreach ($data['children'] as $childData) {
                DB::table('request_children')->insert([
                    'request_id'         => $subscriptionRequest->id,
                    'child_id'           => $childData['child_id'],
                    'pickup_address_id'  => $childData['pickup_address_id'] ?? null,
                    'home_lat'           => $childData['home_lat'] ?? null,
                    'home_lng'           => $childData['home_lng'] ?? null,
                    'home_label'         => $childData['home_label'] ?? null,
                    'dropoff_address_id' => $childData['dropoff_address_id'] ?? null,
                    'school_lat'         => $childData['school_lat'] ?? null,
                    'school_lng'         => $childData['school_lng'] ?? null,
                    'school_label'       => $childData['school_label'] ?? null,
                    'price_per_child'    => $childData['price_per_child'] ?? 0,
                    'child_notes'        => $childData['child_notes'] ?? null,
                ]);
            }

            // إرسال الإشعار للسائق
            $this->notifyUser(
                $driver->user,
                'طلب اشتراك جديد',
                "لديك طلب اشتراك جديد من {$parent->user->full_name}.",
                'new_subscription_request',
                ['subscription_request_id' => $subscriptionRequest->id, 'parent_id' => $parent->id]
            );

            return $subscriptionRequest->load(['children', 'driver.user', 'parent.user', 'school']);
        });
    }

    // ============================================================
    // تحديث حالة الطلب
    // ============================================================

    public function updateStatus(
        SubscriptionRequest $subscriptionRequest,
        string $status,
        ?string $rejectionReason = null
    ): SubscriptionRequest {

        return DB::transaction(function () use ($subscriptionRequest, $status, $rejectionReason) {
            $parent = $subscriptionRequest->parent()->with('user')->first();

            if ($status === SubscriptionRequest::STATUS_ACCEPTED) {
                return $this->handleAcceptance($subscriptionRequest, $parent);
            }
        
            if ($status === SubscriptionRequest::STATUS_REJECTED) {
                return $this->handleRejection($subscriptionRequest, $parent, $rejectionReason);
            }

            throw new Exception("الحالة المطلوبة '{$status}' غير مدعومة.");
        });
    }

    // ============================================================
    // منطق القبول
    // ============================================================

     /**
 * تنفيذ عملية قبول طلب الاشتراك وتوليد كافة الموارد المرتبطة مع حساب المسار الذكي.
 * * @param SubscriptionRequest $req
 * @param ParentModel|null $parent
 * @return SubscriptionRequest
 * @throws \Exception
 */
private function handleAcceptance(SubscriptionRequest $req, ?ParentModel $parent): SubscriptionRequest
{
    return \DB::transaction(function () use ($req, $parent) {
        
        // 1. تحديث حالة الطلب الحالي
        $req->update(['status' => SubscriptionRequest::STATUS_ACCEPTED]);

        // 2. إلغاء الطلبات الأخرى المعلقة لنفس العميل ونفس التوقيت
        SubscriptionRequest::where('parent_id', $req->parent_id)
            ->where('timing', $req->timing)
            ->where('status', SubscriptionRequest::STATUS_PENDING)
            ->where('id', '!=', $req->id)
            ->update(['status' => SubscriptionRequest::STATUS_CANCELLED]);

        // 3. توليد العقد
        $contract = $this->contractService->generateContract($req);

        // 4. التحقق من حالة مركبة السائق
        $vehicle = \App\Models\Driver\Vehicle::where('driver_id', $req->driver_id)
            ->where('status', 'Active')
            ->first();

        if (!$vehicle) {
            throw new \Exception("تعذر إتمام العملية: لا توجد مركبة نشطة مرتبطة بالسائق.");
        }

       // 5. منطق حساب المسار الذكي عبر OSRM
       $osrm = new \App\Services\Shared\OsrmRoutingService();
        
       $driverPos = ['lat' => (float)($req->driver->current_lat ?? 0), 'lng' => (float)($req->driver->current_lng ?? 0)];
       $childPos  = ['lat' => (float)($req->children->first()->latitude ?? 0), 'lng' => (float)($req->children->first()->longitude ?? 0)];
       $schoolPos = ['lat' => (float)($req->school->latitude ?? 0), 'lng' => (float)($req->school->longitude ?? 0)];

       $routeData = $osrm->calculateRoute([$driverPos, $childPos, $schoolPos]);
       
       if (!$routeData) {
           \Log::warning("فشل حساب المسار عبر OSRM للطلب ID: {$req->id}");
       }

       // --- التعديل الجوهري هنا ---
       $distanceInMeters = $routeData['routes'][0]['distance'] ?? 0;
       $durationInSeconds = $routeData['routes'][0]['duration'] ?? 0;

       // تحويل المسافة إلى كيلومتر (التقريب لرقمن عشريين) والوقت إلى دقائق
       $distanceKm = round($distanceInMeters / 1000, 2); 
       $durationMinutes = (int) ceil($durationInSeconds / 60);
       // ---------------------------

       // 6. إنشاء سجل المسار
       \App\Models\Shared\Route::create([
           'contract_id'        => $contract->id,
           'driver_id'          => $req->driver_id,
           'vehicle_id'         => $vehicle->id, 
           'route_name'         => 'مسار ' . ($req->parent->user->full_name ?? 'العميل') . ' - ' . $req->timing,
           'route_type'         => $req->timing === 'MORNING' ? 'Morning' : 'Evening',
           'start_time'         => $req->pickup_time ?? '07:00:00',
           'optimized_points'   => $routeData ?? null, 
           
           // تمرير القيم المحولة
           'total_distance'     => $distanceKm,
           'estimated_duration' => $durationMinutes,
           
           'status'             => 'Active'
       ]);

        // 7. تفعيل اشتراكات الطفل
        $parentUserId = $parent?->user_id ?? $req->parent->user_id;
        $this->createActiveSubscriptions($req, $contract, $parentUserId);

        // 8. إرسال إشعار القبول وتفاصيل العقد لولي الأمر
        if ($parent && $parent->user) {
            $this->notifyUser(
                $parent->user,
                'تم قبول طلب الاشتراك',
                "تم قبول طلبك مع السائق " . ($req->driver->user->full_name ?? 'السائق') . ". رقم العقد: {$contract->contract_number}",
                'request_accepted',
                ['contract_id' => $contract->id]
            );
        }

        // إعادة تحميل الطلب مع العلاقات المحدثة لإرساله في الـ Response
        return $req->refresh()->load(['children', 'driver.user', 'parent.user', 'contract']);
    });
}

    // ============================================================
    // منطق الرفض
    // ============================================================

    private function handleRejection(SubscriptionRequest $req, ?ParentModel $parent, ?string $reason): SubscriptionRequest
    {
        $req->update([
            'status'           => SubscriptionRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
        ]);

        // إرسال إشعار لولي الأمر بالرفض
        if ($parent && $parent->user) {
            $this->notifyUser(
                $parent->user,
                'تم رفض طلب الاشتراك',
                "عذراً، تم رفض طلبك. السبب: " . ($reason ?? 'لم يحدد السائق سبباً.'),
                'request_rejected'
            );
        }

        return $req->refresh();
    }

    // ============================================================
    // إنشاء سجلات الاشتراكات النشطة
    // ============================================================

    private function createActiveSubscriptions(SubscriptionRequest $req, Contract $contract, ?int $parentUserId = null): void
    {
        $parentUserId = $parentUserId ?? optional($req->parent)->user_id;
        $pickupTime = $req->pickup_time ?? '07:00:00';
        $dropoffTime = $req->dropoff_time ?? '14:00:00';

        foreach ($req->children as $child) {
            ActiveSubscription::create([
                'contract_id'   => $contract->id,
                'child_id'      => $child->id,
                'driver_id'     => $req->driver_id,
                'parent_id'     => $parentUserId,
                'pickup_lat'    => $child->pivot->home_lat,
                'pickup_lng'    => $child->pivot->home_lng,
                'pickup_label'  => $child->pivot->home_label,
                'pickup_time'   => $pickupTime,
                'dropoff_lat'   => $child->pivot->school_lat,
                'dropoff_lng'   => $child->pivot->school_lng,
                'dropoff_label' => $child->pivot->school_label,
                'dropoff_time'  => $dropoffTime,
                'status'        => 'active',
            ]);
        }
    }

    // ============================================================
    // نظام إشعارات موحد
    // ============================================================
    
    private function notifyUser($user, string $title, string $message, string $type, array $metadata = []): void
    {
        if ($user) {
            try {
                $user->notify(new CustomDatabaseNotification([
                    'title'    => $title,
                    'message'  => $message,
                    'type'     => $type,
                    'metadata' => $metadata
                ]));
            } catch (Exception $e) {
                Log::error("فشل إرسال الإشعار لـ {$user->id}: " . $e->getMessage());
            }
        }
    }
}