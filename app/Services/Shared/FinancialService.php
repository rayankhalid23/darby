<?php

namespace App\Services\Shared;

use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Invoice;
use App\Models\Shared\Trip;
use App\Models\Shared\TripStudentAttendance;
use App\Models\Parent\ParentModel;
use App\Models\Driver\Driver;
use App\Models\User;
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

    /**
     * توليد فاتورة مبدئية لطلب اشتراك
     */
    public function generateProformaInvoice(SubscriptionRequest|ActiveSubscription $sub): Invoice
    {
        $subscriptionRequest = $sub instanceof ActiveSubscription ? $sub->subscriptionRequest : $sub;
        if (!$subscriptionRequest) {
            throw new \Exception('تعذر العثور على طلب الاشتراك لتوليد الفاتورة.');
        }

        $subscriptionRequest->loadMissing(['parent.user', 'driver.user']);

        $invoiceNumber = 'INV-REQ-' . $subscriptionRequest->id . '-' . strtoupper(substr(uniqid(), -4));

        $parentUserId = $subscriptionRequest->parent?->user_id ?? $subscriptionRequest->parent_id;
        $driverId     = $subscriptionRequest->driver_id;

        if (!$parentUserId) {
            throw new \Exception('تعذر تحديد معرّف ولي الأمر لتوليد الفاتورة.');
        }

        if (!$driverId) {
            throw new \Exception('تعذر تحديد معرّف السائق لتوليد الفاتورة.');
        }

        $totalTrips = $this->calculateTotalTrips($subscriptionRequest);
        $totalPrice = (float) ($subscriptionRequest->total_amount_after_discount ?? $subscriptionRequest->total_price ?? 0);

        return Invoice::create([
            'subscription_request_id' => $subscriptionRequest->id,
            'parent_id'               => $parentUserId,
            'driver_id'               => $driverId,
            'invoice_number'          => $invoiceNumber,
            'amount'                  => $totalPrice,
            'type'                    => 'proforma',
            'status'                  => 'pending',
            'due_date'                => $subscriptionRequest->end_date ?? now()->addDays(30),
            'subscription_type'       => $subscriptionRequest->subscription_type ?? 'multi_day',
            'total_trips'             => $totalTrips,
            'completed_trips'         => 0,
            'driver_absences'         => 0,
            'student_absences'        => 0,
        ]);
    }

    /**
     * تسوية الاشتراك المالي
     */
    public function settleSubscription(SubscriptionRequest|ActiveSubscription $sub): Invoice
    {
        $subscriptionRequest = $sub instanceof ActiveSubscription ? $sub->subscriptionRequest : $sub;
        if (!$subscriptionRequest) {
            throw new \Exception('بيانات الاشتراك غير مكتملة.');
        }

        return DB::transaction(function () use ($subscriptionRequest) {
            $subscriptionRequest->loadMissing(['parent.user', 'driver.user']);

            $parent = $subscriptionRequest->parent;
            $driver = $subscriptionRequest->driver;

            if (!$parent || !$driver) {
                throw new \Exception('بيانات ولي الأمر أو السائق غير مكتملة.');
            }

            $proforma = Invoice::where('subscription_request_id', $subscriptionRequest->id)
                ->where('type', 'proforma')
                ->where('status', 'pending')
                ->latest()
                ->first();

            if (!$proforma) {
                $proforma = $this->generateProformaInvoice($subscriptionRequest);
            }

            $trips = Trip::whereHas('route', function ($q) use ($subscriptionRequest) {
                $q->where('subscription_request_id', $subscriptionRequest->id);
            })->where('driver_id', $subscriptionRequest->driver_id)->get();

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

            $totalPrice = (float) ($subscriptionRequest->total_amount_after_discount ?? $subscriptionRequest->total_price ?? 0);
            $perTripCost = $totalPrice / $totalTrips;
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
                        'message'    => "تم خصم {$netAmount} د.ل من محفظتك لقاء الاشتراك رقم #{$subscriptionRequest->id}.",
                        'amount'     => $netAmount,
                        'invoice_id' => (string) $proforma->id,
                    ]);
                }

                if (isset($driver->user)) {
                    $this->notificationService->sendToUser($driver->user, 'settlement_received', [
                        'title'      => 'تم إيداع مستحقات الاشتراك',
                        'message'    => "تم إيداع {$netAmount} د.ل في محفظتك لقاء الاشتراك رقم #{$subscriptionRequest->id}.",
                        'amount'     => $netAmount,
                        'invoice_id' => (string) $proforma->id,
                    ]);
                }
            } else {
                $overdueInvoices = Invoice::where('subscription_request_id', $subscriptionRequest->id)
                    ->where('status', 'overdue')
                    ->exists();

                if ($overdueInvoices) {
                    $subscriptionRequest->update(['status' => 'cancelled']);

                    ActiveSubscription::where('subscription_request_id', $subscriptionRequest->id)
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
                        'message'    => "رصيد محفظتك غير كافٍ لتسوية {$netAmount} د.ل للاشتراك #{$subscriptionRequest->id}. يرجى شحن المحفظة.",
                        'amount'     => $netAmount,
                        'invoice_id' => (string) $proforma->id,
                    ]);
                }
            }

            return $proforma->fresh();
        });
    }

    /**
     * Alias للتوافقية
     */
    public function settleContract($subscription): Invoice
    {
        return $this->settleSubscription($subscription);
    }

    public function sendPreSettlementWarning(SubscriptionRequest|ActiveSubscription $sub): void
    {
        $subscriptionRequest = $sub instanceof ActiveSubscription ? $sub->subscriptionRequest : $sub;
        if (!$subscriptionRequest) return;

        $parent = $subscriptionRequest->parent;
        if (!$parent || !isset($parent->user)) return;

        $invoice = Invoice::where('subscription_request_id', $subscriptionRequest->id)
            ->where('type', 'proforma')
            ->where('status', 'pending')
            ->first();

        if (!$invoice) {
            $invoice = $this->generateProformaInvoice($subscriptionRequest);
        }

        $this->notificationService->sendToUser($parent->user, 'settlement_warning', [
            'title'      => 'تذكير بتسوية الاشتراك',
            'message'    => "سيتم تسوية الاشتراك رقم #{$subscriptionRequest->id} بقيمة {$invoice->amount} د.ل بعد 3 أيام. يرجى شحن محفظتك.",
            'action_url' => '/parent/wallet',
            'amount'     => $invoice->amount,
            'invoice_id' => (string) $invoice->id,
        ]);
    }

    public function getParentInvoices(int $parentUserId, array $filters = [])
    {
        $query = Invoice::with(['subscriptionRequest', 'driver.user'])
            ->where('parent_id', $parentUserId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate(15);
    }

    public function getDriverInvoices(int $driverId, array $filters = [])
    {
        $query = Invoice::with(['subscriptionRequest', 'parent'])
            ->where('driver_id', $driverId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate(15);
    }

    public function getAllInvoices(array $filters = [])
    {
        $query = Invoice::with(['subscriptionRequest', 'parent', 'driver.user']);

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
        return Invoice::with(['subscriptionRequest', 'parent', 'driver.user'])->findOrFail($id);
    }

    /**
     * حساب إجمالي الرحلات بشكل آمن
     */
    private function calculateTotalTrips(SubscriptionRequest $req): int
    {
        $daysCount = $req->days_count ?? 1;

        if ($req->subscription_type === 'single_day') {
            return max((int)$daysCount, 1);
        }

        if ($req->start_date && $req->end_date) {
            try {
                $start = Carbon::parse($req->start_date);
                $end   = Carbon::parse($req->end_date);

                // استثناء الجمعة (5) والسبت (6)
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