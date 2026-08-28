<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DriverRechargeAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverRechargeController extends Controller
{
    protected DriverRechargeAdminService $service;

    public function __construct(DriverRechargeAdminService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $recharges = $this->service->getAll($request->all());

        return response()->json([
            'status'     => true,
            'data'       => $recharges->items(),
            'pagination' => [
                'current_page' => $recharges->currentPage(),
                'last_page'    => $recharges->lastPage(),
                'total'        => $recharges->total(),
                'per_page'     => $recharges->perPage(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $recharge = $this->service->getDetail($id);

        return response()->json([
            'status' => true,
            'data'   => $recharge,
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $adminId = auth()->id();
        $recharge = $this->service->approve($id, $adminId, $request->input('notes'));

        return response()->json([
            'status'  => true,
            'message' => 'تمت الموافقة على طلب الشحن وإيداع الرصيد في محفظة السائق فوراً.',
            'data'    => $recharge,
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:3|max:500',
        ], [
            'rejection_reason.required' => 'يجب كتابة سبب رفض طلب الشحن لتوضيحه للسائق.',
            'rejection_reason.min'      => 'سبب الرفض يجب ألا يقل عن 3 أحرف.',
        ]);

        $adminId = auth()->id();
        $recharge = $this->service->reject($id, $adminId, $request->input('rejection_reason'));

        return response()->json([
            'status'  => true,
            'message' => 'تم رفض طلب الشحن وتوثيق السبب وإشعار السائق.',
            'data'    => $recharge,
        ]);
    }
}
