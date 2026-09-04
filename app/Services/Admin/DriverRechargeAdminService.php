<?php

namespace App\Services\Admin;

use App\Models\Driver\Driver;
use App\Models\Driver\DriverRechargeRequest;
use App\Services\Notification\NotificationService;
use App\Services\Shared\FinancialLedgerService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverRechargeAdminService
{
    protected NotificationService $notificationService;
    protected FinancialLedgerService $ledgerService;

    public function __construct(NotificationService $notificationService, FinancialLedgerService $ledgerService)
    {
        $this->notificationService = $notificationService;
        $this->ledgerService       = $ledgerService;
    }

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = DriverRechargeRequest::with(['driver.user', 'paymentMethod', 'admin'])
            ->latest('id');

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['driver_id'])) {
            $query->where('driver_id', $filters['driver_id']);
        }

        if (!empty($filters['payment_method_id'])) {
            $query->where('payment_method_id', $filters['payment_method_id']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', $search)
                  ->orWhereHas('driver.user', function ($uq) use ($search) {
                      $uq->where('full_name', 'like', $search)
                         ->orWhere('phone_number', 'like', $search);
                  });
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        return $query->paginate($perPage);
    }

    public function getDetail(int $id): DriverRechargeRequest
    {
        return DriverRechargeRequest::with(['driver.user', 'paymentMethod', 'admin'])
            ->findOrFail($id);
    }

    public function approve(int $id, int $adminId, ?string $notes = null): DriverRechargeRequest
    {
        return DB::transaction(function () use ($id, $adminId, $notes) {
            $recharge = DriverRechargeRequest::where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($recharge->status !== DriverRechargeRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => ['هذا الطلب تمت معالجته والبت فيه مسبقاً.'],
                ]);
            }

            $driver = Driver::with('user')->where('id', $recharge->driver_id)->lockForUpdate()->firstOrFail();

            // تحويل المبلغ إلى قروش وإيداعه في محفظة السائق
            $amountCents = (int) round($recharge->amount * 100);
            $balanceBefore = (int) ($driver->balance ?? 0);
            $driver->deposit($amountCents);

            // مرآة الأحواض: كل إيداع في محفظة سائق يقابله ارتفاع في حوض أرصدتهم المتاحة.
            \App\Models\Shared\MasterEscrowVault::getVault()->increment('driver_available_pool', $amountCents);

            $recharge->update([
                'status'      => DriverRechargeRequest::STATUS_APPROVED,
                'admin_id'    => $adminId,
                'notes'       => $notes ?? $recharge->notes,
                'approved_at' => now(),
            ]);

            // القيد جزء من المعاملة لا ملحق اختياري بها: شحن بلا قيد يعني مالاً
            // دخل النظام دون أثر يمكن تدقيقه.
            $this->ledgerService->recordLedgerEntry(
                'platform_cash_inflow',
                FinancialLedgerService::driverAccount($driver),
                $amountCents,
                'driver_recharge',
                $balanceBefore,
                (int) ($driver->balance ?? 0),
                "RECHARGE-DRV-{$recharge->id}",
                ['recharge_id' => $recharge->id, 'admin_id' => $adminId]
            );

            // إرسال إشعار فوري للسائق
            if ($driver->user) {
                $formattedAmount = number_format($recharge->amount, 2);
                $newBalance = number_format(($driver->balance ?? 0) / 100, 2);
                $this->notificationService->sendToUser($driver->user, 'recharge_approved', [
                    'title'   => '💰 تم شحن محفظتك بنجاح',
                    'message' => "تمت الموافقة على طلب الشحن بمبلغ {$formattedAmount} د.ل. رصيدك الحالي: {$newBalance} د.ل",
                    'entity_id' => (string) $recharge->id,
                ]);
            }

            return $recharge->fresh()->load(['driver.user', 'paymentMethod', 'admin']);
        });
    }

    public function reject(int $id, int $adminId, string $reason): DriverRechargeRequest
    {
        return DB::transaction(function () use ($id, $adminId, $reason) {
            $recharge = DriverRechargeRequest::where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($recharge->status !== DriverRechargeRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => ['هذا الطلب تمت معالجته والبت فيه مسبقاً.'],
                ]);
            }

            $recharge->update([
                'status'           => DriverRechargeRequest::STATUS_REJECTED,
                'admin_id'         => $adminId,
                'rejection_reason' => $reason,
                'rejected_at'      => now(),
            ]);

            $driver = Driver::with('user')->find($recharge->driver_id);
            if ($driver && $driver->user) {
                $formattedAmount = number_format($recharge->amount, 2);
                $this->notificationService->sendToUser($driver->user, 'recharge_rejected', [
                    'title'   => '❌ تم رفض طلب الشحن',
                    'message' => "تم رفض طلب الشحن بمبلغ {$formattedAmount} د.ل. السبب: {$reason}",
                    'entity_id' => (string) $recharge->id,
                ]);
            }

            return $recharge->fresh()->load(['driver.user', 'paymentMethod', 'admin']);
        });
    }
}
