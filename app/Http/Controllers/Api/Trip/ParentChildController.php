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

    private function resolveParentId(): int
    {
        $user = Auth::user();
        $parent = \Illuminate\Support\Facades\DB::table('parents')->where('user_id', $user->id)->first();
        return $parent ? (int) $parent->id : (int) $user->id;
    }

    private function checkChildBelongsToParent(int $childId): void
    {
        $user = Auth::user();
        $parent = \Illuminate\Support\Facades\DB::table('parents')->where('user_id', $user->id)->first();
        $parentId = $parent ? $parent->id : null;

        $child = \App\Models\Parent\Child::where('id', $childId)
            ->where(function ($q) use ($user, $parentId) {
                $q->where('parent_id', $user->id);
                if ($parentId) {
                    $q->orWhere('parent_id', $parentId);
                }
            })->first();

        if (!$child) {
            throw new Exception("عذراً، هذا الطفل غير موجود أو لا يتبع لحسابك.");
        }
    }

    public function setAbsence(ChildAbsenceRequest $request, $childId): JsonResponse
    {
        $user = Auth::user();
        $parentId = $this->resolveParentId();

        try {
            $this->checkChildBelongsToParent((int) $childId);
            $this->lifecycleService->setChildAbsence((int) $childId, $request->dates);

            // تسجيل عملية غياب الطفل في الـ Log
            Log::info("Parent Registered Child Absence", [
                'parent_id'   => $parentId,
                'parent_name' => $user->full_name ?? $user->name,
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
        $parentId = $this->resolveParentId();

        try {
            $this->checkChildBelongsToParent((int) $childId);
            $this->lifecycleService->removeChildAbsence((int) $childId, $request->dates);

            // تسجيل إلغاء الغياب في الـ Log
            Log::info("Parent Cancelled Child Absence", [
                'parent_id'   => $parentId,
                'parent_name' => $user->full_name ?? $user->name,
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

    public function confirmManualPickup($param1 = null, $param2 = null): JsonResponse
    {
        $user = Auth::user();
        $parentId = $this->resolveParentId();

        $childId = request()->route('childId') ?? $param2 ?? $param1;
        $tripId = request()->route('tripId') ?? $param1 ?? $param2;

        try {
            $this->checkChildBelongsToParent((int) $childId);
            $this->stopService->confirmManualPickup((int) $tripId, (int) $childId, $parentId);

            // تسجيل عملية التأكيد اليدوي كإثبات أمان بديل للـ QR
            Log::notice("Parent Confirmed Manual Pickup", [
                'trip_id'     => $tripId,
                'parent_id'   => $parentId,
                'parent_name' => $user->full_name ?? $user->name,
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