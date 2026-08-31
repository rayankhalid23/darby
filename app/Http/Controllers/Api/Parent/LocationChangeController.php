<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\StoreLocationChangeRequestRequest;
use App\Http\Resources\Api\Shared\LocationChangeRequestResource;
use App\Services\Shared\LocationChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class LocationChangeController extends Controller
{
    protected LocationChangeService $service;

    public function __construct(LocationChangeService $service)
    {
        $this->service = $service;
    }

    /**
     * خيارات ولي الأمر لبناء شاشة طلب تغيير الموقع: مواقعه المحفوظة + اشتراكاته/رحلاته النشطة.
     */
    public function options(Request $request): JsonResponse
    {
        try {
            $options = $this->service->getChangeableOptions($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الخيارات بنجاح.',
                'data'    => $options,
            ], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $requests = $this->service->getParentRequests($request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب طلبات تغيير الموقع بنجاح.',
            'data'    => LocationChangeRequestResource::collection($requests),
        ], 200);
    }

    /**
     * معاينة تغيير الموقع قبل الإرسال: معلومات الرحلة وسعرها، المسافة، شريحة الرسوم
     * والمبلغ المطلوب — دون إنشاء أي طلب. ولي الأمر يوافق ثم يستدعي store.
     */
    public function preview(StoreLocationChangeRequestRequest $request): JsonResponse
    {
        try {
            $quote = $this->service->quoteChange(
                $request->user()->id,
                (int) $request->input('active_subscription_id'),
                $request->input('point_type'),
                $request->filled('address_id') ? (int) $request->input('address_id') : null,
                $request->filled('lat') ? (float) $request->input('lat') : null,
                $request->filled('lng') ? (float) $request->input('lng') : null,
                $request->input('label'),
                $request->input('change_date')
            );

            return response()->json([
                'success' => true,
                'message' => 'تم حساب تفاصيل تغيير الموقع بنجاح. راجع السعر والرسوم ثم أكّد الطلب.',
                'data'    => $quote['payload'],
            ], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function store(StoreLocationChangeRequestRequest $request): JsonResponse
    {
        try {
            $changeRequest = $this->service->requestChange(
                $request->user()->id,
                (int) $request->input('active_subscription_id'),
                $request->input('point_type'),
                $request->filled('address_id') ? (int) $request->input('address_id') : null,
                $request->filled('lat') ? (float) $request->input('lat') : null,
                $request->filled('lng') ? (float) $request->input('lng') : null,
                $request->input('label'),
                $request->input('change_date')
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال طلب تغيير الموقع للسائق بانتظار موافقته.',
                'data'    => new LocationChangeRequestResource($changeRequest),
            ], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
