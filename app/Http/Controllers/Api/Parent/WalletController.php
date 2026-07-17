<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\RechargeWalletRequest;
use App\Services\Parent\WalletRechargeService;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    protected WalletRechargeService $rechargeService;

    public function __construct(WalletRechargeService $rechargeService)
    {
        $this->rechargeService = $rechargeService;
    }

    public function paymentMethods(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->rechargeService->getPaymentMethods(),
        ]);
    }

    public function recharge(RechargeWalletRequest $request): JsonResponse
    {
        $userId = auth()->id();

        $rechargeRequest = $this->rechargeService->initiateRecharge(
            $userId,
            $request->validated('amount'),
            $request->validated('payment_method'),
            $request->validated('reference_number')
        );

        $methodLabels = [
            'ncb'     => 'المصرف التجاري الوطني',
            'libyana' => 'ليبيانا',
            'almadar' => 'المدار',
        ];

        return response()->json([
            'success' => true,
            'message' => 'تم تقديم طلب الشحن عبر ' . ($methodLabels[$rechargeRequest->payment_method] ?? '') . ' بنجاح. بانتظار تأكيد الإدارة.',
            'data'    => $rechargeRequest,
        ], 201);
    }

    public function balance(): JsonResponse
    {
        $user = auth()->user();
        $parentProfile = $user->parentProfile ?? $user->parent;

        if (!$parentProfile) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك حساب محفظة.',
            ], 404);
        }

        $balance = $parentProfile->balance / 100;

        return response()->json([
            'success' => true,
            'data'    => [
                'balance' => $balance,
                'currency' => 'د.ل',
            ],
        ]);
    }
}
