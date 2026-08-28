<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\RechargeWalletRequest;
use App\Services\Parent\WalletRechargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    protected WalletRechargeService $rechargeService;

    public function __construct(WalletRechargeService $rechargeService)
    {
        $this->rechargeService = $rechargeService;
    }

    /**
     * 1️⃣ جلب طرق الدفع المفعلة المتاحة لولي الأمر
     */
    public function paymentMethods(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->rechargeService->getPaymentMethods(),
        ]);
    }

    /**
     * 2️⃣ بدء عملية الشحن لولي الأمر والحصول على توكن جلسة الدفع ورابط المحاكاة
     */
    public function initiateRecharge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'            => 'required|numeric|min:0.5',
            'payment_method'    => 'nullable|string|max:50',
            'payment_method_id' => 'nullable|integer|exists:payment_methods,id',
            'reference_number'  => 'nullable|string|max:100',
        ], [
            'amount.required' => 'يرجى إدخال مبلغ الشحن.',
            'amount.min'      => 'الحد الأدنى للشحن هو نصف دينار ليبي.',
        ]);

        $userId = auth()->id();
        $methodIdentifier = $validated['payment_method_id'] ?? $validated['payment_method'] ?? 'sadad';

        $sessionData = $this->rechargeService->initiateRecharge(
            $userId,
            (float) $validated['amount'],
            $methodIdentifier,
            $validated['reference_number'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء جلسة الشحن وجاهزة لتنفيذ عملية الدفع.',
            'data'    => $sessionData,
        ], 201);
    }

    /**
     * 3️⃣ تنفيذ محاكاة بوابة الدفع وتأكيد السداد وتحديث رصيد ولي الأمر فوراً
     */
    public function mockPay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => 'required|string',
            'card_number'   => 'nullable|string|max:30',
            'card_holder'   => 'nullable|string|max:100',
            'expiry_date'   => 'nullable|string|max:10',
            'cvv'           => 'nullable|string|max:5',
            'otp'           => 'nullable|string|max:10',
        ], [
            'session_token.required' => 'توكن جلسة الدفع مطلوب.',
        ]);

        $result = $this->rechargeService->processMockPayment(
            $validated['session_token'],
            $request->only(['card_number', 'card_holder', 'expiry_date', 'cvv', 'otp'])
        );

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $result,
        ]);
    }

    /**
     * الشحن المباشر (Legacy)
     */
    public function recharge(RechargeWalletRequest $request): JsonResponse
    {
        $userId = auth()->id();

        $sessionData = $this->rechargeService->initiateRecharge(
            $userId,
            $request->validated('amount'),
            $request->validated('payment_method'),
            $request->validated('reference_number')
        );

        return response()->json([
            'success' => true,
            'message' => 'تم بدء عملية الشحن بنجاح.',
            'data'    => $sessionData,
        ], 201);
    }

    /**
     * 4️⃣ عرض رصيد محفظة ولي الأمر الحالي
     */
    public function balance(): JsonResponse
    {
        $user = auth()->user();
        $parentProfile = $user->parentProfile ?? $user->parent ?? \App\Models\Parent\ParentModel::where('user_id', $user->id)->first();

        $balance = $parentProfile ? ($parentProfile->balance / 100) : 0.0;

        return response()->json([
            'success' => true,
            'data'    => [
                'balance'  => round($balance, 2),
                'currency' => 'د.ل',
            ],
        ]);
    }

    /**
     * 5️⃣ حجز مبلغ الرحلة اليومية في أمانات المحفظة
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
                'amount'       => round($hold->amount / 100, 2),
                'hold_status'  => $hold->hold_status,
                'held_at'      => $hold->held_at,
                'captured_at'  => $hold->captured_at,
                'available_at' => $hold->available_at,
                'disputed_at'  => $hold->disputed_at,
            ],
        ], 201);
    }

    /**
     * 6️⃣ تقديم اعتراض مالي على رحلة
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
