<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ProcessWithdrawalRequest;
use App\Http\Resources\Api\Shared\InvoiceResource;
use App\Services\Admin\ComplaintService;
use App\Services\Driver\WithdrawalService;
use App\Services\Parent\WalletRechargeService;
use App\Services\Shared\FinancialService;
use App\Models\Shared\RechargeRequest;
use Illuminate\Http\JsonResponse;

class FinancialController extends Controller
{
    protected FinancialService $financialService;
    protected WithdrawalService $withdrawalService;
    protected WalletRechargeService $rechargeService;

    public function __construct(
        FinancialService $financialService,
        WithdrawalService $withdrawalService,
        WalletRechargeService $rechargeService
    ) {
        $this->financialService = $financialService;
        $this->withdrawalService = $withdrawalService;
        $this->rechargeService = $rechargeService;
    }

    public function invoices(): JsonResponse
    {
        $invoices = $this->financialService->getAllInvoices(
            request()->only(['status', 'type', 'driver_id'])
        );

        return response()->json([
            'status'     => true,
            'data'       => InvoiceResource::collection($invoices),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page'    => $invoices->lastPage(),
                'total'        => $invoices->total(),
            ],
        ]);
    }

    public function invoiceDetail(int $id): JsonResponse
    {
        $invoice = $this->financialService->getInvoiceById($id);

        return response()->json([
            'status' => true,
            'data'   => new InvoiceResource($invoice),
        ]);
    }

    public function withdrawals(): JsonResponse
    {
        $requests = $this->withdrawalService->getAllWithdrawals(
            request()->only(['status'])
        );

        return response()->json([
            'status'     => true,
            'data'       => $requests,
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page'    => $requests->lastPage(),
                'total'        => $requests->total(),
            ],
        ]);
    }

    public function processWithdrawal(int $id, ProcessWithdrawalRequest $request): JsonResponse
    {
        $admin = auth()->user()->admin;

        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية.'], 403);
        }

        $action = $request->validated('action');

        if ($action === 'approve') {
            $withdrawal = $this->withdrawalService->approveWithdrawal($id, $admin->id);
            $message = 'تمت الموافقة على طلب السحب بنجاح.';
        } else {
            $withdrawal = $this->withdrawalService->rejectWithdrawal(
                $id,
                $admin->id,
                $request->validated('rejection_reason')
            );
            $message = 'تم رفض طلب السحب.';
        }

        return response()->json([
            'status'  => true,
            'message' => $message,
            'data'    => $withdrawal,
        ]);
    }

    public function rechargeRequests(): JsonResponse
    {
        $requests = RechargeRequest::with(['parent'])
            ->when(request('status'), fn($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(15);

        return response()->json([
            'status'     => true,
            'data'       => $requests,
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page'    => $requests->lastPage(),
                'total'        => $requests->total(),
            ],
        ]);
    }

    public function processRecharge(int $id): JsonResponse
    {
        $admin = auth()->user()->admin;

        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية.'], 403);
        }

        $action = request('action', 'complete');

        if ($action === 'complete') {
            $recharge = $this->rechargeService->completeRecharge($id, $admin->id);
            $message = 'تم تأكيد عملية الشحن وإضافة الرصيد للمحفظة.';
        } else {
            $recharge = $this->rechargeService->failRecharge(
                $id,
                $admin->id,
                request('reason', 'تم رفض طلب الشحن.')
            );
            $message = 'تم رفض طلب الشحن.';
        }

        return response()->json([
            'status'  => true,
            'message' => $message,
            'data'    => $recharge,
        ]);
    }
}
