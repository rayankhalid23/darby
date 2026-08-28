<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\FinancialLedger;
use App\Models\Shared\Trip;
use App\Services\Shared\FinancialLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialLedgerController extends Controller
{
    protected FinancialLedgerService $ledgerService;

    public function __construct(FinancialLedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * 0️⃣ الملخص المالي اليومي للداشبورد (Financial Dashboard Summary)
     * GET /api/admin/financial/summary
     */
    public function summary(): JsonResponse
    {
        $vault = \App\Models\Shared\MasterEscrowVault::getVault();

        $pendingWithdrawals = \App\Models\Shared\WithdrawalRequest::where('status', 'pending')->count();
        $pendingRecharges   = \App\Models\Shared\RechargeRequest::where('status', 'pending')->count();
        $pendingDisputes    = \App\Models\Shared\TripDispute::where('status', 'open')->count();
        $pendingEscrows     = \App\Models\Shared\TripEscrowHold::where('hold_status', 'captured_pending')->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'parents_escrow_pool'       => round($vault->parents_escrow_pool / 100, 2),
                'driver_pending_pool'       => round($vault->driver_pending_pool / 100, 2),
                'driver_available_pool'     => round($vault->driver_available_pool / 100, 2),
                'platform_revenue_pool'     => round($vault->platform_revenue_pool / 100, 2),
                'penalty_pool'              => round($vault->penalty_pool / 100, 2),
                'pending_withdrawals_count' => $pendingWithdrawals,
                'pending_recharges_count'   => $pendingRecharges,
                'pending_disputes_count'    => $pendingDisputes,
                'pending_escrows_count'     => $pendingEscrows,
            ],
        ]);
    }

    /**
     * 1️⃣ فحص معادلة السلامة المالية اليومية (Daily Solvency Check)
     * GET /api/admin/financial/solvency-check
     */
    public function solvencyCheck(): JsonResponse
    {
        $result = $this->ledgerService->checkDailySolvency();

        return response()->json([
            'success' => true,
            'message' => $result['is_solvent'] ? 'النظام متسق مالياً بنسبة 100%.' : 'تنبيه: يوجد عدم اتساق مالي مجهول المصدر!',
            'data'    => $result,
        ]);
    }

    /**
     * 2️⃣ نظرة عامة على الأمانات المعلقة وتفاصيل التحرير (Escrows Overview)
     * GET /api/admin/financial/escrows
     */
    public function escrowOverview(): JsonResponse
    {
        $holds = \App\Models\Shared\TripEscrowHold::where('hold_status', 'captured_pending')->get();

        $pendingAmountCents = $holds->sum('amount');
        $eligibleHolds = $holds->filter(fn($h) => $h->available_at && $h->available_at->isPast());
        $eligibleAmountCents = $eligibleHolds->sum('amount');
        $oldestEscrow = $holds->sortBy('created_at')->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'pending_amount'  => round($pendingAmountCents / 100, 2),
                'eligible_amount' => round($eligibleAmountCents / 100, 2),
                'trips_count'     => $holds->count(),
                'eligible_count'  => $eligibleHolds->count(),
                'oldest_escrow'   => $oldestEscrow?->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * 2️⃣-ب معالجة تحويل الأرباح المعلقة بعد 24 ساعة (Release Escrows Cron Trigger)
     * POST /api/admin/financial/release-escrows
     */
    public function releaseEscrows(): JsonResponse
    {
        $count = $this->ledgerService->releasePendingTripEscrows();

        return response()->json([
            'success' => true,
            'message' => "تم تحويل أرباح {$count} رحلة مكتملة إلى رصيد السائقين المتاح.",
            'data'    => [
                'released_count' => $count,
            ],
        ]);
    }

    /**
     * 3️⃣ عرض السجل المالي غير القابل للمسح (Immutable Ledger Entries)
     * GET /api/admin/financial/ledger
     */
    public function ledgerLogs(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);

        $query = FinancialLedger::latest()
            ->when($request->type, fn($q, $v) => $q->where('type', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->search, function ($q, $v) {
                $q->where('reference_number', 'like', "%{$v}%")
                  ->orWhere('source_account', 'like', "%{$v}%")
                  ->orWhere('destination_account', 'like', "%{$v}%");
            })
            ->when($request->date_from, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->date_to, fn($q, $v) => $q->whereDate('created_at', '<=', $v));

        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $logs->items(),
            'meta'    => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    /**
     * 3️⃣-ب سجل تدقيق الحركات المالية للإدارة (Financial Audit Logs)
     * GET /api/admin/financial/audit-logs
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);

        $query = FinancialLedger::where('type', 'like', '%admin%')
            ->orWhereNotNull('metadata->admin_id')
            ->latest()
            ->when($request->search, function ($q, $v) {
                $q->where('reference_number', 'like', "%{$v}%");
            });

        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $logs->items(),
            'meta'    => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    /**
     * 4️⃣ قائمة النزاعات المالية (Disputes List)
     * GET /api/admin/financial/disputes
     */
    public function disputesList(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = \App\Models\Shared\TripDispute::with(['parent.user', 'driver.user', 'trip'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->latest();

        $disputes = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $disputes->items(),
            'meta'    => [
                'current_page' => $disputes->currentPage(),
                'last_page'    => $disputes->lastPage(),
                'per_page'     => $disputes->perPage(),
                'total'        => $disputes->total(),
            ],
        ]);
    }

    /**
     * 4️⃣-ب تفاصيل نزاع مالي واحد (Dispute Details)
     * GET /api/admin/financial/disputes/{id}
     */
    public function disputeDetail(int $id): JsonResponse
    {
        $dispute = \App\Models\Shared\TripDispute::with(['parent.user', 'driver.user', 'trip.route'])->findOrFail($id);

        $hold = \App\Models\Shared\TripEscrowHold::where('trip_id', $dispute->trip_id)->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'               => $dispute->id,
                'trip_id'          => $dispute->trip_id,
                'parent'           => [
                    'id'    => $dispute->parent_id,
                    'name'  => $dispute->parent?->user?->full_name,
                    'phone' => $dispute->parent?->user?->phone_number,
                ],
                'driver'           => [
                    'id'    => $dispute->driver_id,
                    'name'  => $dispute->driver?->user?->full_name,
                    'phone' => $dispute->driver?->user?->phone_number,
                ],
                'amount'           => $hold ? round($hold->amount / 100, 2) : 0,
                'reason'           => $dispute->reason,
                'status'           => $dispute->status,
                'resolution_notes' => $dispute->resolution_notes,
                'created_at'       => $dispute->created_at,
                'resolved_at'      => $dispute->resolved_at,
            ],
        ]);
    }

    /**
     * 4️⃣-ج حل النزاع المالي بواسطة الأدمن (Resolve Dispute)
     * POST /api/admin/financial/disputes/{disputeId}/resolve
     */
    public function resolveDispute(Request $request, int $disputeId): JsonResponse
    {
        $request->validate([
            'resolution' => 'required|in:resolve_parent_refunded,resolve_driver_paid',
            'notes'      => 'nullable|string',
        ]);

        $disputeObj = \App\Models\Shared\TripDispute::findOrFail($disputeId);

        // 🔴 Idempotency Guard
        if ($disputeObj->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تنفيذ العملية.',
                'errors'  => ['resolution' => ['تم حسم هذا النزاع المالي مسبقاً.']]
            ], 422);
        }

        $adminId = auth()->user()->admin->id ?? auth()->id();

        $dispute = $this->ledgerService->resolveDispute(
            $disputeId,
            $adminId,
            $request->resolution,
            $request->notes
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حل النزاع المالي وإعادة توزيع الأرصدة بنجاح.',
            'data'    => $dispute,
        ]);
    }

    /**
     * 5️⃣ قائمة الاشتراكات الجاهزة للتسوية المالية (Pending Monthly Settlements)
     * GET /api/admin/financial/contracts/pending-settlements
     */
    public function pendingSettlements(): JsonResponse
    {
        $perPage = (int) request('per_page', 15);

        $subscriptions = SubscriptionRequest::with(['parent.user', 'driver.user', 'routes.trips'])
            ->where('status', 'accepted')
            ->latest()
            ->paginate($perPage);

        $formatted = collect($subscriptions->items())->map(function (SubscriptionRequest $subscription) {
            $totalPrice = (float) ($subscription->total_amount_after_discount ?? $subscription->total_price ?? 0);
            $plannedTrips = max((int) ($subscription->days_count ?? 20), 1);
            $perTripCost = $totalPrice / $plannedTrips;

            $trips = Trip::whereHas('route', fn($q) => $q->where('subscription_request_id', $subscription->id))->get();
            $completedCount = $trips->where('status', 'completed')->count();

            $executedAmount = round($completedCount * $perTripCost, 2);
            $pendingAmount  = max(0, round($totalPrice - $executedAmount, 2));

            return [
                'contract_id'       => $subscription->id,
                'contract_number'   => "REQ-{$subscription->id}",
                'parent'            => $subscription->parent?->user?->full_name,
                'driver'            => $subscription->driver?->user?->full_name,
                'total_amount'      => $totalPrice,
                'executed_amount'   => $executedAmount,
                'pending_amount'    => $pendingAmount,
                'completed_trips'   => $completedCount,
                'settlement_status' => 'pending_settlement',
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $formatted,
            'meta'    => [
                'current_page' => $subscriptions->currentPage(),
                'last_page'    => $subscriptions->lastPage(),
                'per_page'     => $subscriptions->perPage(),
                'total'        => $subscriptions->total(),
            ],
        ]);
    }

    /**
     * 5️⃣-ب تسوية الاشتراك الشهري الإغلاق والمقاصة النهائية (Monthly Subscription Settlement)
     * POST /api/admin/financial/contracts/{contractId}/settle-monthly
     */
    public function settleMonthly(int $contractId): JsonResponse
    {
        $subscription = SubscriptionRequest::findOrFail($contractId);

        // 🔴 Idempotency Guard
        if ($subscription->status === 'settled') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تنفيذ العملية.',
                'errors'  => ['settlement' => ['تم إجراء التسوية النهائية لهذا الاشتراك مسبقاً.']]
            ], 422);
        }

        $result = $this->ledgerService->settleMonthlySubscription($subscription);
        $subscription->update(['status' => 'settled']);

        return response()->json([
            'success' => true,
            'message' => 'تمت تسوية الاشتراك الشهري وجرد الحساب بنجاح.',
            'data'    => $result,
        ]);
    }

    /**
     * 6️⃣ معاينة حسابات الإلغاء المبكر للاشتراك (Termination Preview)
     * GET /api/admin/financial/contracts/{contractId}/termination-preview
     */
    public function terminationPreview(Request $request, int $contractId): JsonResponse
    {
        $subscription = SubscriptionRequest::findOrFail($contractId);
        $terminatedBy = $request->query('terminated_by', 'parent');
        $isArbitrary = $request->boolean('is_arbitrary_parent');

        $result = $this->ledgerService->previewSubscriptionTermination($subscription, $terminatedBy, $isArbitrary);

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * 6️⃣-ب الإلغاء المبكر للاشتراك في منتصف الشهر (Mid-Month Termination)
     * POST /api/admin/financial/contracts/{contractId}/terminate-mid-month
     */
    public function terminateMidMonth(Request $request, int $contractId): JsonResponse
    {
        $request->validate([
            'terminated_by'        => 'required|in:parent,driver,admin',
            'is_arbitrary_parent'  => 'nullable|boolean',
        ]);

        $subscription = SubscriptionRequest::findOrFail($contractId);

        // 🔴 Idempotency Guard
        if ($subscription->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تنفيذ العملية.',
                'errors'  => ['termination' => ['الاشتراك ملغى بالفعل مسبقاً.']]
            ], 422);
        }

        $result = $this->ledgerService->terminateSubscriptionMidMonth(
            $subscription,
            $request->terminated_by,
            (bool) $request->is_arbitrary_parent
        );

        return response()->json([
            'success' => true,
            'message' => 'تم الإلغاء المبكر للاشتراك وإجراء التسوية الفورية.',
            'data'    => $result,
        ]);
    }

    /**
     * 7️⃣ معاينة مصفوفة الغرامات لإلغاء رحلة (Trip Cancellation Preview)
     * GET /api/admin/financial/trips/{tripId}/cancel-preview
     */
    public function cancellationPreview(Request $request, int $tripId): JsonResponse
    {
        $cancelledBy = $request->query('cancelled_by', 'parent');
        $trip = Trip::findOrFail($tripId);

        $result = $this->ledgerService->previewTripCancellation($trip, $cancelledBy);

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * 7️⃣-ب إلغاء رحلة بتطبيق جدول وسياسة الغرامات (Cancellation Matrix)
     * POST /api/admin/financial/trips/{tripId}/cancel-with-matrix
     */
    public function cancelTripWithMatrix(Request $request, int $tripId): JsonResponse
    {
        $request->validate([
            'cancelled_by' => 'required|in:parent,driver,no_show',
        ]);

        $trip = Trip::findOrFail($tripId);

        // 🔴 Idempotency Guard
        if ($trip->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تنفيذ العملية.',
                'errors'  => ['cancellation' => ['الرحلة ملغاة بالفعل مسبقاً.']]
            ], 422);
        }

        $result = $this->ledgerService->processTripCancellation(
            $trip,
            $request->cancelled_by
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الرحلة ومعالجة الغرامات والتعويضات حسب جدول إلغاء الرحلات.',
            'data'    => $result,
        ]);
    }
}
