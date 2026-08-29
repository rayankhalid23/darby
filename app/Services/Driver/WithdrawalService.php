<?php

namespace App\Services\Driver;

use App\Models\Shared\WithdrawalRequest;
use App\Models\Driver\Driver;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    protected ?NotificationService $notificationService;

    public function __construct(?NotificationService $notificationService = null)
    {
        $this->notificationService = $notificationService ?? app(NotificationService::class);
    }

    public function requestWithdrawal(int $driverId, float $amount, ?array $paymentDetails = null): WithdrawalRequest
    {
        $driver = Driver::findOrFail($driverId);

        $balance = $driver->balance / 100;

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['المبلغ يجب أن يكون أكبر من صفر.'],
            ]);
        }

        if ($amount > $balance) {
            throw ValidationException::withMessages([
                'amount' => ["رصيد محفظتك غير كافٍ. الرصيد المتاح: {$balance} د.ل"],
            ]);
        }

        if ($amount < 5) {
            throw ValidationException::withMessages([
                'amount' => ['الحد الأدنى للسحب هو 5 دنانير.'],
            ]);
        }

        $pendingExists = WithdrawalRequest::where('driver_id', $driverId)
            ->where('status', 'pending')
            ->exists();

        if ($pendingExists) {
            throw ValidationException::withMessages([
                'amount' => ['لديك طلب سحب قيد المراجعة بالفعل. انتظر حتى تتم معالجته.'],
            ]);
        }

        $driver->wallet->withdraw($amount * 100);

        return WithdrawalRequest::create([
            'driver_id'                => $driverId,
            'amount'                   => $amount,
            'wallet_balance_at_request' => $balance,
            'status'                   => 'pending',
            'payment_method_details'   => $paymentDetails,
        ]);
    }

    public function approveWithdrawal(int $withdrawalId, int $adminId): WithdrawalRequest
    {
        $request = WithdrawalRequest::where('id', $withdrawalId)
            ->where('status', 'pending')
            ->firstOrFail();

        $request->update([
            'status'       => 'approved',
            'admin_id'     => $adminId,
            'processed_at' => now(),
        ]);

        $fresh = $request->fresh(['driver.user']);

        try {
            $driverUser = $fresh->driver?->user;
            if ($driverUser && $this->notificationService) {
                $formattedAmount = number_format($fresh->amount, 2);
                $this->notificationService->sendToUser($driverUser, 'withdrawal_approved', [
                    'title'       => '💵 تمت الموافقة على طلب السحب',
                    'message'     => "تمت معالجة طلب سحب مبلغ ({$formattedAmount} د.ل) بنجاح وتحويله إلى حسابك.",
                    'amount'      => $fresh->amount,
                    'entity_type' => 'withdrawal',
                    'entity_id'   => (string) $fresh->id,
                    'screen'      => 'WALLET',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("فشل إرسال إشعار الموافقة على السحب للسائق: " . $e->getMessage());
        }

        return $fresh;
    }

    public function rejectWithdrawal(int $withdrawalId, int $adminId, string $reason): WithdrawalRequest
    {
        $request = WithdrawalRequest::where('id', $withdrawalId)
            ->where('status', 'pending')
            ->firstOrFail();

        $driver = Driver::findOrFail($request->driver_id);
        $driver->deposit($request->amount * 100);

        $request->update([
            'status'           => 'rejected',
            'admin_id'         => $adminId,
            'rejection_reason' => $reason,
            'processed_at'     => now(),
        ]);

        $fresh = $request->fresh(['driver.user']);

        try {
            $driverUser = $fresh->driver?->user;
            if ($driverUser && $this->notificationService) {
                $formattedAmount = number_format($fresh->amount, 2);
                $this->notificationService->sendToUser($driverUser, 'withdrawal_rejected', [
                    'title'       => '⚠️ رفض طلب سحب الرصيد',
                    'message'     => "تم رفض طلب سحب مبلغ ({$formattedAmount} د.ل) وإعادة المبلغ إلى محفظتك. السبب: {$reason}",
                    'amount'      => $fresh->amount,
                    'entity_type' => 'withdrawal',
                    'entity_id'   => (string) $fresh->id,
                    'screen'      => 'WALLET',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("فشل إرسال إشعار رفض السحب للسائق: " . $e->getMessage());
        }

        return $fresh;
    }

    public function getDriverWithdrawals(int $driverId, array $filters = [])
    {
        $query = WithdrawalRequest::where('driver_id', $driverId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate(15);
    }

    public function getAllWithdrawals(array $filters = [])
    {
        $query = WithdrawalRequest::with(['driver.user']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate(15);
    }
}
