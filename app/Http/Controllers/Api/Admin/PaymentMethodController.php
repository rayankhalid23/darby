<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    protected PaymentMethodService $service;

    public function __construct(PaymentMethodService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $methods = $this->service->getAll($request->all());

        return response()->json([
            'status'     => true,
            'data'       => $methods->items(),
            'pagination' => [
                'current_page' => $methods->currentPage(),
                'last_page'    => $methods->lastPage(),
                'total'        => $methods->total(),
                'per_page'     => $methods->perPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name_ar'         => 'required|string|max:100',
            'name_en'         => 'nullable|string|max:100',
            'code'            => 'required|string|max:50|unique:payment_methods,code',
            'target_audience' => 'required|in:parent,driver,both',
            'processing_type' => 'required|in:instant_simulation,manual_proof',
            'account_name'    => 'nullable|string|max:150',
            'account_number'  => 'nullable|string|max:100',
            'iban'            => 'nullable|string|max:100',
            'wallet_number'   => 'nullable|string|max:100',
            'icon_url'        => 'nullable|string|max:255',
            'min_amount'      => 'nullable|numeric|min:0.5',
            'max_amount'      => 'nullable|numeric|gt:min_amount',
            'instructions_ar' => 'nullable|string|max:1000',
            'instructions_en' => 'nullable|string|max:1000',
            'is_active'       => 'nullable|boolean',
            'sort_order'      => 'nullable|integer',
        ]);

        $method = $this->service->create($validated);

        return response()->json([
            'status'  => true,
            'message' => 'تم إنشاء طريقة الدفع بنجاح.',
            'data'    => $method,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $method = $this->service->getById($id);

        return response()->json([
            'status' => true,
            'data'   => $method,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name_ar'         => 'sometimes|required|string|max:100',
            'name_en'         => 'nullable|string|max:100',
            'code'            => 'sometimes|required|string|max:50|unique:payment_methods,code,' . $id,
            'target_audience' => 'sometimes|required|in:parent,driver,both',
            'processing_type' => 'sometimes|required|in:instant_simulation,manual_proof',
            'account_name'    => 'nullable|string|max:150',
            'account_number'  => 'nullable|string|max:100',
            'iban'            => 'nullable|string|max:100',
            'wallet_number'   => 'nullable|string|max:100',
            'icon_url'        => 'nullable|string|max:255',
            'min_amount'      => 'nullable|numeric|min:0.5',
            'max_amount'      => 'nullable|numeric',
            'instructions_ar' => 'nullable|string|max:1000',
            'instructions_en' => 'nullable|string|max:1000',
            'is_active'       => 'nullable|boolean',
            'sort_order'      => 'nullable|integer',
        ]);

        $method = $this->service->update($id, $validated);

        return response()->json([
            'status'  => true,
            'message' => 'تم تعديل طريقة الدفع بنجاح.',
            'data'    => $method,
        ]);
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $method = $this->service->toggleStatus($id);

        return response()->json([
            'status'  => true,
            'message' => 'تم تغيير حالة تفعيل وسيلة الدفع بنجاح.',
            'data'    => [
                'id'        => $method->id,
                'name_ar'   => $method->name_ar,
                'is_active' => (bool) $method->is_active,
            ],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json([
            'status'  => true,
            'message' => 'تم حذف طريقة الدفع بنجاح.',
        ]);
    }
}
