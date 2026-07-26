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

    /**
     * حساب عدد أيام العمل بين تاريخ البدء والانتهاء باستثناء الجمعة والسبت
     */
    private function calculateWorkingDays(?string $startDate, ?string $endDate): int
    {
        if (empty($startDate) || empty($endDate)) {
            return 0;
        }

        try {
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);

            if ($start->greaterThan($end)) {
                return 0;
            }

            $period = \Carbon\CarbonPeriod::create($start, $end);
            $workingDaysCount = 0;

            foreach ($period as $date) {
                // استثناء يوم الجمعة (5) والسبت (6)
                if (!in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY])) {
                    $workingDaysCount++;
                }
            }

            return $workingDaysCount;
        } catch (\Exception $e) {
            return 0;
        }
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
            $startDate = $data['start_date'] ?? null;
            $endDate = $data['end_date'] ?? null;

            // 1. حساب عدد أيام العمل وتعريف المتغير هنا لتجنب الخطأ
            $daysCount = $this->calculateWorkingDays($startDate, $endDate);

            $totalPrice = collect($data['children'])->sum(fn($c) => $c['price_per_child'] ?? 0);

            $subscriptionRequest = SubscriptionRequest::create([
                'parent_id'         => $parent->id,
                'driver_id'         => $driver->id,
                'school_id'         => $data['school_id'] ?? null,
                'subscription_type' => $data['subscription_type'] ?? 'monthly',
                'direction'         => $data['direction'],
                'timing'            => $data['timing'],
                'start_date'        => $startDate,
                'end_date'          => $endDate,
                'days_count'        => $daysCount, // 2. استخدامه هنا بشكل صحيح
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

    private function handleAcceptance(SubscriptionRequest $req, ?ParentModel $parent): SubscriptionRequest
    {
        return DB::transaction(function () use ($req, $parent) {
            
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
                throw new Exception("تعذر إتمام العملية: لا توجد مركبة نشطة مرتبطة بالسائق.");
            }

           // 5. منطق حساب المسار الذكي عبر OSRM
           $osrm = new \App\Services\Shared\OsrmRoutingService();
           
           $driverPos = ['lat' => (float)($req->driver->current_lat ?? 0), 'lng' => (float)($req->driver->current_lng ?? 0)];
           $childPos  = ['lat' => (float)($req->children->first()->pivot->home_lat ?? 0), 'lng' => (float)($req->children->first()->pivot->home_lng ?? 0)];
           $schoolPos = ['lat' => (float)($req->school->latitude ?? 0), 'lng' => (float)($req->school->longitude ?? 0)];

           $routeData = $osrm->calculateRoute([$driverPos, $childPos, $schoolPos]);
           
           if (!$routeData) {
               Log::warning("فشل حساب المسار عبر OSRM للطلب ID: {$req->id}");
           }

           $distanceInMeters = $routeData['routes'][0]['distance'] ?? 0;
           $durationInSeconds = $routeData['routes'][0]['duration'] ?? 0;

           $distanceKm = round($distanceInMeters / 1000, 2); 
           $durationMinutes = (int) ceil($durationInSeconds / 60);

           // 6. إنشاء سجل المسار
           \App\Models\Shared\Route::create([
               'contract_id'        => $contract->id,
               'driver_id'          => $req->driver_id,
               'vehicle_id'         => $vehicle->id, 
               'route_name'         => 'مسار ' . ($req->parent->user->full_name ?? 'العميل') . ' - ' . $req->timing,
               'route_type'         => $req->timing === 'MORNING' ? 'Morning' : 'Evening',
               'start_time'         => $req->pickup_time ?? '07:00:00',
               'optimized_points'   => $routeData ?? null, 
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
                'status'        => 'active', // القيمة الافتراضية عند التفعيل
            ]);
        }
    }

    // ============================================================
    // دالة جديدة: تغيير حالة الاشتراك النشط (مفعل، معلق، مكتمل، ملغي)
    // ============================================================
    public function updateActiveSubscriptionStatus(int $activeSubscriptionId, string $status): ActiveSubscription
    {
        $allowedStatuses = ['active', 'pending', 'completed', 'cancelled'];
        
        if (!in_array($status, $allowedStatuses)) {
            throw new Exception("حالة الاشتراك غير صالحة. يجب أن تكون إحدى الحالات التالية: " . implode(', ', $allowedStatuses));
        }

        $activeSub = ActiveSubscription::find($activeSubscriptionId);
        if (!$activeSub) {
            throw new Exception('الاشتراك النشط غير موجود.');
        }

        $activeSub->update([
            'status' => $status
        ]);

        return $activeSub->load(['contract', 'child', 'driver.user']);
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
    /**
     * جلب تفاصيل اشتراك نشط واحد خاص بالسائق مع العلاقات الكاملة
     */
    public function getDriverActiveSubscriptionDetails(int $activeSubscriptionId, int $driverId)
    {
        $activeSub = ActiveSubscription::where('id', $activeSubscriptionId)
            ->where('driver_id', $driverId)
            ->with([
                'contract',
                'child.school',
                'child.address',
                'parent', // تم تعديلها لتتوافق مع نموذج الأب مباشرة
                'school'
            ])
            ->first();

        if (!$activeSub) {
            throw new Exception('الاشتراك النشط غير موجود أو ليس لديك صلاحية للوصول إليه.');
        }

        return $activeSub;
    }

    // ============================================================
    // جلب طلبات الاشتراك الخاصة بولي الأمر
    // ============================================================
    public function getParentSubscriptions(int $userId, ?string $filter = null)
    {
        $parent = ParentModel::where('user_id', $userId)->first();
        if (!$parent) {
            throw new Exception('هذا الحساب غير مسجل كولي أمر في النظام.');
        }

        $query = SubscriptionRequest::where('parent_id', $parent->id)
            ->with([
                'driver.user',
                'school:id,name',
                'children.school',
                'contract'
            ]);

        switch ($filter) {
            case 'pending':
                $query->where('status', SubscriptionRequest::STATUS_PENDING);
                break;
            case 'accepted':
                $query->where('status', SubscriptionRequest::STATUS_ACCEPTED);
                break;
            case 'rejected':
                $query->where('status', SubscriptionRequest::STATUS_REJECTED);
                break;
            case 'cancelled':
                $query->where('status', SubscriptionRequest::STATUS_CANCELLED);
                break;
        }

        return $query->orderBy('id', 'desc')->get();
    }
    
    /**
     * إلغاء طلب الاشتراك بواسطة ولي الأمر قبل قبول السائق له
     */
    public function cancelSubscriptionByParent(int $id, int $userId): SubscriptionRequest
    {
        $parent = ParentModel::where('user_id', $userId)->first();
        if (!$parent) {
            throw new Exception('هذا الحساب غير مسجل كولي أمر في النظام.');
        }

        $subscription = SubscriptionRequest::where('id', $id)
            ->where('parent_id', $parent->id)
            ->first();

        if (!$subscription) {
            throw new Exception('طلب الاشتراك غير موجود، أو لا تملك صلاحية الوصول إليه.');
        }

        if ($subscription->status !== SubscriptionRequest::STATUS_PENDING) {
            throw new Exception('لا يمكن إلغاء هذا الطلب لأن حالته الحالية هي: ' . $subscription->status);
        }

        $subscription->update([
            'status' => SubscriptionRequest::STATUS_CANCELLED
        ]);

        return $subscription;
    }

    /**
     * جلب الاشتراكات المفعّلة لولي الأمر والموافَق عليها مقسمة بالفلاتر الذكية
     */
    public function getParentActiveSubscriptions(int $userId, ?string $filter = null)
    {
        // 1. جلب سجل ولي الأمر والتأكد من وجوده
        $parent = ParentModel::where('user_id', $userId)->first();
        if (!$parent) {
            throw new Exception('هذا الحساب غير مسجل كولي أمر في النظام.');
        }

        // 2. بناء الاستعلام مع العلاقات الأساسية
        $query = ActiveSubscription::with([
            'contract', 
            'child', 
            'driver.user', 
            'driver.vehicles',
            'school'
        ])
        ->where(function ($q) use ($userId, $parent) {
            $q->where('parent_id', $parent->id)
              ->orWhere('parent_id', $userId);
        });

        // 3. تطبيق الفلتر بشكل صحيح وآمن فقط إذا تم إرساله وكان ضمن القيم المسموحة
        if (!empty($filter)) {
            $allowedFilters = ['active', 'pending', 'completed', 'cancelled'];
            if (in_array($filter, $allowedFilters)) {
                $query->where('status', $filter);
            }
        }

        return $query->orderBy('id', 'desc')->get();
    }

    /**
     * جلب طلبات الاشتراك المبدئية الواردة للسائق مع الفلترة الذكية
     */
    public function getDriverSubscriptionRequests(int $userId, ?string $filter = null)
    {
        $driver = Driver::where('user_id', $userId)->first();
        if (!$driver) {
            throw new Exception('لم يتم العثور على ملف السائق الخاص بك.');
        }

        $query = SubscriptionRequest::where('driver_id', $driver->id)
            ->with([
                'parent.user',
                'school:id,name',
                'children'
            ]);

        switch ($filter) {
            case 'pending':
                $query->where('status', SubscriptionRequest::STATUS_PENDING);
                break;
            case 'cancelled':
                $query->where('status', SubscriptionRequest::STATUS_CANCELLED);
                break;
            case 'rejected':
                $query->where('status', SubscriptionRequest::STATUS_REJECTED);
                break;
        }

        return $query->orderBy('id', 'desc')->get();
    }

    public function parentHasSubscriptionWithDriver(int $userId, int $driverId): bool
    {
        $parent = ParentModel::where('user_id', $userId)->first();
        if (!$parent) {
            return false;
        }

        return ActiveSubscription::where(function ($q) use ($userId, $parent) {
            $q->where('parent_id', $parent->id)
              ->orWhere('parent_id', $userId);
        })->where('driver_id', $driverId)
          ->whereIn('status', ['active', 'completed', 'cancelled']) // الحالات المطلوبة
          ->exists();
    }

    public function getDriverActiveSubscriptions(int $userId, ?string $filter = null)
    {
        $driver = Driver::where('user_id', $userId)->first();
        if (!$driver) {
            throw new Exception('لم يتم العثور على ملف السائق الخاص بك.');
        }

        $query = ActiveSubscription::where('driver_id', $driver->id)
            ->with([
                'contract',
                'child.school',
                'parent'
            ]);

        $today = now()->toDateString();

        // تطبيق فلاتر الحالات مباشرة
        switch ($filter) {
            case 'active':
                $query->where('status', 'active');
                break;

            case 'pending':
                $query->where('status', 'pending');
                break;

            case 'completed':
                $query->where('status', 'completed');
                break;

            case 'cancelled':
                $query->where('status', 'cancelled');
                break;
        }

        return $query->orderBy('id', 'desc')->get();
    }

    public function getSubscriptionDetails($id)
    {
        return SubscriptionRequest::with([
            'parent.user',
            'driver.user',
            'children.school',
            'children.address',
            'contract',
        ])->findOrFail($id);
    }
    /**
     * جلب أIDs جميع السائقين المشترك معهم ولي الأمر
     */
    public function getParentSubscribedDriverIds(int $userId): array
    {
        $parent = ParentModel::where('user_id', $userId)->first();
        if (!$parent) {
            return [];
        }

        return ActiveSubscription::where(function ($q) use ($userId, $parent) {
                $q->where('parent_id', $parent->id)
                  ->orWhere('parent_id', $userId);
            })
            ->whereIn('status', ['active', 'completed', 'cancelled'])
            ->pluck('driver_id')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * جلب أIDs جميع أولياء الأمور المشترك معهم السائق
     */
    public function getDriverSubscribedParentIds(int $userId): array
    {
        $driver = Driver::where('user_id', $userId)->first();
        if (!$driver) {
            return [];
        }

        // جلب الـ parent_id مباشرة من جدول الاشتراكات النشطة/المكتملة/الملغاة
        return ActiveSubscription::where('driver_id', $driver->id)
            ->whereIn('status', ['active', 'completed', 'cancelled'])
            ->pluck('parent_id')
            ->unique()
            ->values()
            ->toArray();
    }
    public function getSubscribedDrivers(Request $request): JsonResponse
    {
        try {
            $driverIds = $this->subscriptionService->getParentSubscribedDriverIds($request->user()->id);

            return response()->json([
                'success'    => true,
                'driver_ids' => $driverIds
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 400);
        }
    }

    
}