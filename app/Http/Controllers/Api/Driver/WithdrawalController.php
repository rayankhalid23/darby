<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\WithdrawalRequest as WithdrawalFormRequest;
use App\Services\Driver\WithdrawalService;
use Illuminate\Http\JsonResponse;

class WithdrawalController extends Controller
{
    protected WithdrawalService $withdrawalService;

    public function __construct(WithdrawalService $withdrawalService)
    {
        $this->withdrawalService = $withdrawalService;
    }

    public function index(): JsonResponse
    {
        $driver = auth()->user()->driver;

        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية.'], 403);
        }

        $requests = $this->withdrawalService->getDriverWithdrawals(
            $driver->id,
            request()->only(['status'])
        );

        return response()->json([
            'success'    => true,
            'data'       => $requests,
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page'    => $requests->lastPage(),
                'total'        => $requests->total(),
            ],
        ]);
    }

    public function store(WithdrawalFormRequest $request): JsonResponse
    {
        $driver = auth()->user()->driver;

        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية.'], 403);
        }

        $withdrawal = $this->withdrawalService->requestWithdrawal(
            $driver->id,
            $request->validated('amount'),
            $request->validated('payment_method_details')
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تقديم طلب السحب بنجاح. بانتظار مراجعة الإدارة.',
            'data'    => $withdrawal,
        ], 201);
    }

    public function balance(): JsonResponse
    {
        $driver = auth()->user()->driver;

        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية.'], 403);
        }

        $balance = $driver->balance / 100;

        return response()->json([
            'success' => true,
            'data'    => [
                'balance'  => $balance,
                'currency' => 'د.ل',
            ],
        ]);
    }
}
