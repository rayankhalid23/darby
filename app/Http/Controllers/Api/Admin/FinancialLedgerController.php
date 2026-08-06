<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shared\Contract;
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
     * 2️⃣ معالجة تحويل الأرباح المعلقة بعد 24 ساعة (Release Escrows Cron Trigger)
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
        $logs = FinancialLedger::latest()
            ->when($request->type, fn($q, $v) => $q->where('type', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->paginate(15);

        return response()->json([
            'success'    => true,
            'data'       => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    /**
     * 4️⃣ حل النزاع المالي بواسطة الأدمن (Resolve Dispute)
     * POST /api/admin/financial/disputes/{disputeId}/resolve
     */
    public function resolveDispute(Request $request, int $disputeId): JsonResponse
    {
        $request->validate([
            'resolution' => 'required|in:resolve_parent_refunded,resolve_driver_paid',
            'notes'      => 'nullable|string',
        ]);

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
     * 5️⃣ تسوية العقد الشهري الإغلاق والمقاصة النهائية (Monthly Subscription Settlement)
     * POST /api/admin/financial/contracts/{contractId}/settle-monthly
     */
    public function settleMonthly(int $contractId): JsonResponse
    {
        $contract = Contract::findOrFail($contractId);
        $result = $this->ledgerService->settleMonthlyContract($contract);

        return response()->json([
            'success' => true,
            'message' => 'تمت تسوية العقد الشهري وجرد الحساب بنجاح.',
            'data'    => $result,
        ]);
    }

    /**
     * 6️⃣ الإلغاء المبكر للعقد في منتصف الشهر (Mid-Month Termination)
     * POST /api/admin/financial/contracts/{contractId}/terminate-mid-month
     */
    public function terminateMidMonth(Request $request, int $contractId): JsonResponse
    {
        $request->validate([
            'terminated_by'        => 'required|in:parent,driver,admin',
            'is_arbitrary_parent'  => 'nullable|boolean',
        ]);

        $contract = Contract::findOrFail($contractId);
        $result = $this->ledgerService->terminateContractMidMonth(
            $contract,
            $request->terminated_by,
            (bool) $request->is_arbitrary_parent
        );

        return response()->json([
            'success' => true,
            'message' => 'تم الإلغاء المبكر للعقد وإجراء التسوية الفورية.',
            'data'    => $result,
        ]);
    }

    /**
     * 7️⃣ إلغاء رحلة بتطبيق جدول وسياسة الغرامات (Cancellation Matrix)
     * POST /api/admin/financial/trips/{tripId}/cancel-with-matrix
     */
    public function cancelTripWithMatrix(Request $request, int $tripId): JsonResponse
    {
        $request->validate([
            'cancelled_by' => 'required|in:parent,driver,no_show',
        ]);

        $trip = Trip::findOrFail($tripId);
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
