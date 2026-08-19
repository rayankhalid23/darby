<?php

namespace App\Services\Shared;

use App\Models\Shared\Contract;
use App\Models\Shared\Invoice;
use App\Models\Shared\Trip;
use App\Models\Shared\TripStudentAttendance;
use App\Models\Shared\ActiveSubscription;
use App\Models\Parent\ParentModel;
use App\Models\Driver\Driver;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FinancialService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function generateProformaInvoice(Contract $contract): Invoice
{
    $contract->loadMissing(['subscriptionRequest.parent', 'parent.user', 'driver']);

    $invoiceNumber = 'INV-' . $contract->contract_number;

    // 1. جلب parent_id (المطابق لـ users.id)
    $parentUserId = $contract->parent_id 
        ?? $contract->parent?->user_id 
        ?? $contract->parent?->id 
        ?? $contract->subscriptionRequest?->parent?->user_id 
        ?? $contract->subscriptionRequest?->parent_id;

    if (!$parentUserId) {
        Log::error("تعذر تحديد parent_id للعقد رقم: {$contract->id}");
        throw new \Exception('تعذر تحديد معرّف ولي الأمر لتوليد الفاتورة.');
    }

    // 2. جلب driver_id الخاص بجدول drivers (وليس user_id) لحل خطأ FK
    $driver = $contract->driver;
    if (!$driver) {
        // البحث عن السائق في جدول drivers سواء بالـ id أو الـ user_id
        $driver = Driver::where('id', $contract->driver_id)
            ->orWhere('user_id', $contract->driver_id)
            ->first();
    }

    $driverId = $driver?->id ?? $contract->subscriptionRequest?->driver_id;

    if (!$driverId) {
        Log::error("تعذر تحديد driver_id الخاص بجدول drivers للعقد رقم: {$contract->id}");
        throw new \Exception('تعذر تحديد معرّف السائق لتوليد الفاتورة.');
    }

    $totalTrips = $this->calculateTotalTrips($contract);

    return Invoice::create([
        'contract_id'       => $contract->id,
        'parent_id'         => $parentUserId,
        'driver_id'         => $driverId, // تم إرسال id الخاص بـ drivers (وليس user_id)
        'invoice_number'    => $invoiceNumber,
        'amount'            => $contract->total_price ?? 0,
        'type'              => 'proforma',
        'status'            => 'pending',
        'due_date'          => $contract->end_date ?? now()->addDays(30),
        'subscription_type' => $contract->subscription_type ?? 'monthly',
        'total_trips'       => $totalTrips,
        'completed_trips'   => 0,
        'driver_absences'   => 0,
        'student_absences'  => 0,
    ]);
}

    public function settleContract(Contract $contract): Invoice
    {
        return DB::transaction(function () use ($contract) {
            $contract->load(['parent', 'driver']);

            $parent = $contract->parent;
            $driver = $contract->driver;

            if (!$parent || !$driver) {
                throw new \Exception('بيانات العقد غير مكتملة.');
            }

            $proforma = Invoice::where('contract_id', $contract->id)
                ->where('type', 'proforma')
                ->where('status', 'pending')
                ->latest()
                ->first();

            if (!$proforma) {
                $proforma = $this->generateProformaInvoice($contract);
            }

            $trips = Trip::whereHas('route', function ($q) use ($contract) {
                $q->where('contract_id', $contract->id);
            })->where('driver_id', $contract->driver_id)->get();

            $totalTrips = max($trips->count(), 1);
            $completedTrips = $trips->where('status', 'completed');
            $completedCount = $completedTrips->count();

            $driverAbsences = $completedTrips->where('driver_attendance', false)->count();
            $studentAbsences = 0;

            $tripIds = $completedTrips->pluck('id');
            if ($tripIds->isNotEmpty()) {
                $studentAbsences = TripStudentAttendance::whereIn('trip_id', $tripIds)
                    ->whereIn('attendance_status', ['absent', 'late'])
                    ->count();
            }

            $perTripCost = ($contract->total_price ?? 0) / $totalTrips;
            $totalCost = $completedCount * $perTripCost;
            $deductions = $driverAbsences * $perTripCost;
            $netAmount = max($totalCost - $deductions, 0);

            $parentWallet = $parent;
            $driverWallet = $driver;

            $netAmountCents = (int) round($netAmount * 100);
            $parentBalance = $parentWallet->balance ?? 0;
            $sufficient = $parentBalance >= $netAmountCents;

            if ($sufficient) {
                $parentWallet->transfer($driverWallet, $netAmountCents);

                $proforma->update([
                    'status'            => 'paid',
                    'type'              => 'final',
                    'completed_trips'   => $completedCount,
                    'driver_absences'   => $driverAbsences,
                    'student_absences'  => $studentAbsences,
                    'calculated_amount' => $netAmount,
                    'paid_at'           => now(),
                ]);

                if (isset($parent->user)) {
                    $this->notificationService->sendToUser($parent->user, 'settlement_paid', [
                        'title'      => 'تم تسوية الاشتراك',
                        'message'    => "تم خصم {$netAmount} د.ل من محفظتك لقاء العقد رقم {$contract->contract_number}.",
                        'amount'     => $netAmount,
                        'invoice_id' => (string) $proforma->id,
                    ]);
                }

                if (isset($driver->user)) {
                    $this->notificationService->sendToUser($driver->user, 'settlement_received', [
                        'title'      => 'تم إيداع مستحقات الاشتراك',
                        'message'    => "تم إيداع {$netAmount} د.ل في محفظتك لقاء العقد رقم {$contract->contract_number}.",
                        'amount'     => $netAmount,
                        'invoice_id' => (string) $proforma->id,
                    ]);
                }
            } else {
                $overdueInvoices = Invoice::where('contract_id', $contract->id)
                    ->where('status', 'overdue')
                    ->exists();

                if ($overdueInvoices) {
                    $contract->update(['status' => 'terminated']);

                    ActiveSubscription::where('contract_id', $contract->id)
                        ->update(['status' => 'suspended_unpaid']);
                }

                $proforma->update([
                    'status'            => 'overdue',
                    'type'              => 'final',
                    'completed_trips'   => $completedCount,
                    'driver_absences'   => $driverAbsences,
                    'student_absences'  => $studentAbsences,
                    'calculated_amount' => $netAmount,
                    'action_taken'      => 'overdue_unpaid',
                ]);

                if (isset($parent->user)) {
                    $this->notificationService->sendToUser($parent->user, 'settlement_overdue', [
                        'title'      => 'فاتورة غير مدفوعة',
                        'message'    => "رصيد محفظتك غير كافٍ لتسوية {$netAmount} د.ل للعقد {$contract->contract_number}. يرجى شحن المحفظة.",
                        'amount'     => $netAmount,
                        'invoice_id' => (string) $proforma->id,
                    ]);
                }
            }

            return $proforma->fresh();
        });
    }

    public function sendPreSettlementWarning(Contract $contract): void
    {
        $parent = $contract->parent;
        if (!$parent || !isset($parent->user)) return;

        $invoice = Invoice::where('contract_id', $contract->id)
            ->where('type', 'proforma')
            ->where('status', 'pending')
            ->first();

        if (!$invoice) {
            $invoice = $this->generateProformaInvoice($contract);
        }

        $this->notificationService->sendToUser($parent->user, 'settlement_warning', [
            'title'      => 'تذكير بتسوية الاشتراك',
            'message'    => "سيتم تسوية العقد رقم {$contract->contract_number} بقيمة {$invoice->amount} د.ل بعد 3 أيام. يرجى شحن محفظتك.",
            'action_url' => '/parent/wallet',
            'amount'     => $invoice->amount,
            'invoice_id' => (string) $invoice->id,
        ]);
    }

    public function getParentInvoices(int $parentUserId, array $filters = [])
    {
        $query = Invoice::with(['contract', 'driver.user'])
            ->where('parent_id', $parentUserId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate(15);
    }

    public function getDriverInvoices(int $driverId, array $filters = [])
    {
        $query = Invoice::with(['contract', 'parent'])
            ->where('driver_id', $driverId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate(15);
    }

    public function getAllInvoices(array $filters = [])
    {
        $query = Invoice::with(['contract', 'parent', 'driver.user']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['driver_id'])) {
            $query->where('driver_id', $filters['driver_id']);
        }

        return $query->latest()->paginate(15);
    }

    public function getInvoiceById(int $id): Invoice
    {
        return Invoice::with(['contract', 'parent', 'driver.user'])->findOrFail($id);
    }

    /**
     * حساب إجمالي الرحلات بشكل آمن
     */
    private function calculateTotalTrips(Contract $contract): int
    {
        $daysCount = $contract->days_count ?? 1;

        if ($contract->subscription_type === 'single_day') {
            return max((int)$daysCount, 1);
        }

        if ($contract->start_date && $contract->end_date) {
            try {
                $start = Carbon::parse($contract->start_date);
                $end   = Carbon::parse($contract->end_date);

                // استثناء الجمعة (5) والسبت (6) — نظام الأسبوع الإسلامي
                $workingDays = 0;
                while ($start->lte($end)) {
                    if (!in_array($start->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY])) {
                        $workingDays++;
                    }
                    $start->addDay();
                }
                return max($workingDays, 1);
            } catch (\Exception $e) {
                return max((int)$daysCount, 1);
            }
        }

        return max((int)$daysCount, 1);
    }
}