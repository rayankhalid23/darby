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
        // التصحيح هنا: نجلب رقم ولي الأمر الفعلي المرتبط بالمستخدم، وليس رقم المستخدم نفسه
        $parentId = auth()->user()->parent->id;

        // استدعاء الخدمة لجلب العناوين
        $addresses = $this->addressService->getParentAddresses($parentId);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب دفتر العناوين بنجاح.',
            'data'    => AddressResource::collection($addresses)
        ], Response::HTTP_OK);
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        // التصحيح هنا: استبدال الرقم الثابت بالرقم الفعلي لولي الأمر
        $parentId = auth()->user()->parent->id; 
        
        $address = $this->addressService->createAddress($parentId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة العنوان الجديد بنجاح.',
            'data'    => new AddressResource($address)
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        // التصحيح هنا أيضاً: استبدال الرقم الثابت
        $parentId = auth()->user()->parent->id; 
        
        $updatedAddress = $this->addressService->updateAddress($address, $parentId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات العنوان بنجاح.',
            'data'    => new AddressResource($updatedAddress)
        ], Response::HTTP_OK);
    }
}