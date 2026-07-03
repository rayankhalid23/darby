<?php

namespace App\Services\Shared;

use App\Models\Shared\SubscriptionRequest;
use App\Models\Parent\ParentModel;
use App\Models\Driver\Driver;
use App\Models\Shared\Contract;
use App\Models\Shared\ActiveSubscription;
use App\Services\Shared\EmailService;
use App\Services\Shared\ContractService;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;

class SubscriptionRequestService
{
    protected EmailService $emailService;
    protected ContractService $contractService;

    public function __construct(EmailService $emailService, ContractService $contractService)
    {
        $this->emailService     = $emailService;
        $this->contractService  = $contractService;
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

            $this->notifyDriverOfNewRequest($driver, $parent, $subscriptionRequest);

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
        $req->update(['status' => SubscriptionRequest::STATUS_ACCEPTED]);

        SubscriptionRequest::where('parent_id', $req->parent_id)
            ->where('timing', $req->timing)
            ->where('status', SubscriptionRequest::STATUS_PENDING) // استخدام ثابت
            ->where('id', '!=', $req->id)
            ->update(['status' => SubscriptionRequest::STATUS_CANCELLED]); // استخدام ثابت
        $contract = $this->contractService->generateContract($req);

        $this->createActiveSubscriptions($req, $contract);

        if ($parent && $parent->user && $parent->user->email) {
            try {
                $this->emailService->sendRequestAcceptedToParent(
                    to:           $parent->user->email,
                    parentName:   $parent->user->full_name,
                    driverName:   $req->driver->user->full_name ?? 'السائق',
                    contractNumber: $contract->contract_number,
                    startDate:    $contract->start_date->format('Y-m-d'),
                    totalPrice:   $contract->total_price,
                );
            } catch (\Exception $e) {
                Log::warning("فشل إشعار ولي الأمر بالقبول: " . $e->getMessage());
            }
        }

        return $req->refresh()->load(['children', 'driver.user', 'parent.user', 'contract']);
    }

    // ============================================================
    // منطق الرفض
    // ============================================================

    private function handleRejection(SubscriptionRequest $req, ?ParentModel $parent, ?string $reason): SubscriptionRequest
    {
        $req->update([
            'status'           => SubscriptionRequest::STATUS_REJECTED, // ثابت
            'rejection_reason' => $reason,
        ]);

        if ($parent && $parent->user && $parent->user->email) {
            try {
                $this->emailService->sendRequestRejectedToParent(
                    to:            $parent->user->email,
                    parentName:    $parent->user->full_name,
                    driverName:    $req->driver->user->full_name ?? 'السائق',
                    rejectionReason: $reason ?? 'لم يحدد السائق سبباً.',
                );
            } catch (\Exception $e) {
                Log::warning("فشل إشعار ولي الأمر بالرفض: " . $e->getMessage());
            }
        }

        return $req->refresh();
    }

    // ============================================================
    // إنشاء سجلات الاشتراكات النشطة
    // ============================================================

    private function createActiveSubscriptions(SubscriptionRequest $req, Contract $contract): void
    {
        foreach ($req->children as $child) {
            ActiveSubscription::create([
                'contract_id'   => $contract->id,
                'child_id'      => $child->id,
                'driver_id'     => $req->driver_id,
                'parent_id'     => $req->parent_id,
                'pickup_lat'    => $child->pivot->home_lat,
                'pickup_lng'    => $child->pivot->home_lng,
                'pickup_label'  => $child->pivot->home_label,
                'dropoff_lat'   => $child->pivot->school_lat,
                'dropoff_lng'   => $child->pivot->school_lng,
                'dropoff_label' => $child->pivot->school_label,
                'status'        => 'active',
            ]);
        }
    }

    // ============================================================
    // إشعار السائق
    // ============================================================
    
    private function notifyDriverOfNewRequest(
        Driver $driver,
        ParentModel $parent,
        SubscriptionRequest $subscriptionRequest
    ): void {
        // التأكد من وجود المستخدم المرتبط بالسائق
        if (!$driver->user || !$driver->user->id) {
            return;
        }
    
        try {
            // إنشاء نص الإشعار
            $parentName = $parent->user->full_name ?? 'ولي الأمر';
            $body = "لديك طلب اشتراك جديد من {$parentName}. نوع الاشتراك: {$subscriptionRequest->subscription_type_text}، الاتجاه: {$subscriptionRequest->direction_text}.";
    
            // إدراج الإشعار في جدول notifications
            DB::table('notifications')->insert([
                'user_id'    => $driver->user_id, // معرف المستخدم الخاص بالسائق
                'type'       => 'SYSTEM',          // نوع الإشعار (تأكد أنه متوافق مع الـ Enum لديك)
                'title'      => 'طلب اشتراك جديد',
                'body'       => $body,
                'metadata'   => json_encode([
                    'subscription_request_id' => $subscriptionRequest->id,
                    'parent_id'               => $parent->id
                ]),
                'priority'   => 'High',
                'is_read'    => 0,
                'created_at' => now(),
            ]);
    
        } catch (\Exception $e) {
            // تسجيل الخطأ في حال فشل الإدراج في قاعدة البيانات
            Log::error("فشل إدراج إشعار السائق في قاعدة البيانات: " . $e->getMessage());
        }
    }
   
}