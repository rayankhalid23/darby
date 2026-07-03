<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\StoreAddressRequest;
use App\Http\Requests\Api\Parent\UpdateAddressRequest;
use App\Http\Resources\Api\Parent\AddressResource;
use App\Models\Parent\Address;
use App\Services\Parent\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AddressController extends Controller
{
    protected AddressService $addressService;

    public function __construct(AddressService $addressService)
    {
        $this->addressService = $addressService;
    }

    public function index(): JsonResponse
{
    // الحصول على معرف المستخدم الحالي بدلاً من القيمة الثابتة 1
    $userId = auth()->id();

    // استدعاء الخدمة لجلب العناوين
    $addresses = $this->addressService->getParentAddresses($userId);

    return response()->json([
        'success' => true,
        'message' => 'تم جلب دفتر العناوين بنجاح.',
        'data'    => AddressResource::collection($addresses)
    ], Response::HTTP_OK);
}

   

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $parentId = 1; // سيتم استبدالها بـ auth()->user()->parent->id
        $address = $this->addressService->createAddress($parentId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة العنوان الجديد بنجاح.',
            'data'    => new AddressResource($address)
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        $parentId = 1; // سيتم استبدالها بـ auth()->user()->parent->id
        $updatedAddress = $this->addressService->updateAddress($address, $parentId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات العنوان بنجاح.',
            'data'    => new AddressResource($updatedAddress)
        ], Response::HTTP_OK);
    }

    public function destroy(Address $address): JsonResponse
    {
        try {
            $this->addressService->deleteAddress($address);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف العنوان بنجاح من دفتر عناوينك.'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            // إمساك خطأ الارتباط وعرض الرسالة التجارية لولي الأمر
            return response()->json([
                'success'    => false,
                'error_code' => 'ADDRESS_ALREADY_IN_USE',
                'message'    => $e->getMessage()
            ], Response::HTTP_UNPROCESSABLE_ENTITY); // Code: 422
        }
    }
}