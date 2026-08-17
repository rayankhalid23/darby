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

    /**
     * حجز مبلغ الرحلة اليومية في أمانات المحفظة
     * POST /api/parent/wallet/hold-trip
     */
    public function holdTripAmount(\Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate([
            'trip_id' => 'required|integer|exists:trips,id',
            'amount'  => 'required|numeric|min:0.5',
        ]);

        $trip = \App\Models\Shared\Trip::findOrFail($request->trip_id);
        $ledgerService = app(\App\Services\Shared\FinancialLedgerService::class);

        $hold = $ledgerService->holdTripAmount($trip, auth()->id(), (float) $request->amount);

        return response()->json([
            'success' => true,
            'message' => 'تم حجز مبلغ الرحلة بنجاح في أمانات المحفظة.',
            'data'    => [
                'id'           => $hold->id,
                'trip_id'      => $hold->trip_id,
                'parent_id'    => $hold->parent_id,
                'driver_id'    => $hold->driver_id,
                'amount'       => round($hold->amount / 100, 2), // بالدينار، مطابقة لوحدة باقي واجهات المحفظة
                'hold_status'  => $hold->hold_status,
                'held_at'      => $hold->held_at,
                'captured_at'  => $hold->captured_at,
                'available_at' => $hold->available_at,
                'disputed_at'  => $hold->disputed_at,
            ],
        ], 201);
    }

    /**
     * تقديم اعتراض مالي على رحلة خلال مهلة 24 ساعة
     * POST /api/parent/trips/{tripId}/dispute
     */
    public function openDispute(\Illuminate\Http\Request $request, int $tripId): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $ledgerService = app(\App\Services\Shared\FinancialLedgerService::class);
        $dispute = $ledgerService->openDispute($tripId, auth()->id(), $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'تم تقديم الاعتراض وتجميد مبلغ الرحلة لحين مراجعة الإدارة.',
            'data'    => $dispute,
        ], 201);
    }
}
