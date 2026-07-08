<?php

namespace App\Http\Controllers\Api\Trip; // مسار الكنترولر المنظم الجديد

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Trip\ChildAbsenceRequest; // استدعاء الـ Request من مجلده الجديد
use App\Services\Trip\TripLifecycleService;
use App\Services\Trip\TripStopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // 👈 استدعاء واجهة الـ Log
use Exception;

class ParentChildController extends Controller
{
    protected TripLifecycleService $lifecycleService;
    protected TripStopService $stopService;

    public function __construct(TripLifecycleService $lifecycleService, TripStopService $stopService)
    {
        $this->lifecycleService = $lifecycleService;
        $this->stopService = $stopService;
    }

    public function setAbsence(ChildAbsenceRequest $request, $childId): JsonResponse
    {
        $user = Auth::user();
        $parentId = $user->parent->id;

        try {
            $this->lifecycleService->setChildAbsence($childId, $request->dates);

            // تسجيل عملية غياب الطفل في الـ Log
            Log::info("Parent Registered Child Absence", [
                'parent_id'   => $parentId,
                'parent_name' => $user->name,
                'child_id'    => $childId,
                'dates'       => $request->dates
            ]);

            return response()->json(['status' => 'success', 'message' => 'تم جدولة غياب الطفل بنجاح وتحديث المسارات الجارية إن وجدت']);
        } catch (Exception $e) {
            Log::error("Failed to set child absence", [
                'parent_id' => $parentId,
                'child_id'  => $childId,
                'error'     => $e->getMessage()
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function cancelAbsence(ChildAbsenceRequest $request, $childId): JsonResponse
    {
        $user = Auth::user();
        $parentId = $user->parent->id;

        try {
            $this->lifecycleService->removeChildAbsence($childId, $request->dates);

            // تسجيل إلغاء الغياب في الـ Log
            Log::info("Parent Cancelled Child Absence", [
                'parent_id'   => $parentId,
                'parent_name' => $user->name,
                'child_id'    => $childId,
                'dates'       => $request->dates
            ]);

            return response()->json(['status' => 'success', 'message' => 'تم إلغاء الغياب وإعادة الطفل للمسارات التشغيلية']);
        } catch (Exception $e) {
            Log::error("Failed to cancel child absence", [
                'parent_id' => $parentId,
                'child_id'  => $childId,
                'error'     => $e->getMessage()
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function confirmManualPickup($tripId, $childId): JsonResponse
    {
        $user = Auth::user();
        $parentId = $user->parent->id;

        try {
            $this->stopService->confirmManualPickup($tripId, $childId, $parentId);

            // تسجيل عملية التأكيد اليدوي كإثبات أمان بديل للـ QR
            Log::notice("Parent Confirmed Manual Pickup", [
                'trip_id'     => $tripId,
                'parent_id'   => $parentId,
                'parent_name' => $user->name,
                'child_id'    => $childId
            ]);

            return response()->json(['status' => 'success', 'message' => 'قمتِ بتأكيد ركوب طفلك يدوياً، تم إخطار السائق']);
        } catch (Exception $e) {
            Log::error("Failed manual pickup confirmation", [
                'trip_id'   => $tripId,
                'parent_id' => $parentId,
                'child_id'  => $childId,
                'error'     => $e->getMessage()
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}