<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shared\Zone;
use App\Models\Shared\Municipality;
use App\Models\Shared\SubMunicipality;
use App\Services\Shared\ZoneService;
use App\Http\Requests\Api\Admin\StoreZoneRequest;
use App\Http\Resources\Api\Shared\ZoneResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Exception;

class ZoneController extends Controller
{
    protected ZoneService $zoneService;

    public function __construct(ZoneService $zoneService)
    {
        $this->zoneService = $zoneService;
    }

    /**
     * 1. عرض المناطق (أو الشجرة الجغرافية الكاملة عند طلب zones-tree)
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->is('*/zones-tree')) {
            $data = Municipality::with('subMunicipalities.zones')->get();
            return response()->json([
                'status'  => true,
                'message' => 'تم جلب شجرة الجغرافيا الكاملة بنجاح.',
                'data'    => $data
            ], Response::HTTP_OK);
        }

        $zones = $this->zoneService->getAllZones();

        return response()->json([
            'status'  => true,
            'message' => 'تم جلب كافة المناطق بنجاح.',
            'data'    => ZoneResource::collection($zones)
        ], Response::HTTP_OK);
    }

    /**
     * 2. عرض تفاصيل منطقة محددة
     */
    public function show(int $id): JsonResponse
    {
        try {
            $zone = $this->zoneService->getZoneById($id);

            return response()->json([
                'status' => true,
                'data'   => new ZoneResource($zone)
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * 3. إضافة منطقة جديدة (Admin)
     */
    public function store(StoreZoneRequest $request): JsonResponse
    {
        try {
            try { $validated = $request->validated(); } catch (\Throwable $e) { $validated = $request->all(); }
            $zone = $this->zoneService->createZone($validated);
            
            return response()->json([
                'status'  => true,
                'message' => 'تم إضافة المنطقة بنجاح.',
                'data'    => new ZoneResource($zone)
            ], Response::HTTP_CREATED);
            
        } catch (Exception $e) {
            return response()->json([
                'status'  => false, 
                'message' => $e->getMessage()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * 4. تعديل منطقة (Admin)
     */
    public function update(StoreZoneRequest $request, $id): JsonResponse
    {
        try {
            $zone = Zone::findOrFail($id);
            try { $validated = $request->validated(); } catch (\Throwable $e) { $validated = $request->all(); }
            
            $updatedZone = $this->zoneService->updateZone($zone, $validated);
            
            return response()->json([
                'status'  => true,
                'message' => 'تم تحديث بيانات المنطقة بنجاح.',
                'data'    => new ZoneResource($updatedZone)
            ], Response::HTTP_OK);
            
        } catch (Exception $e) {
            return response()->json([
                'status'  => false, 
                'message' => $e->getMessage()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * 5. حذف منطقة (Admin) مع حماية علاقات السائقين
     */
    public function destroy($id): JsonResponse
    {
        try {
            $zone = Zone::findOrFail($id);
            $this->zoneService->deleteZone($zone);
            
            return response()->json([
                'status'  => true,
                'message' => 'تم حذف المنطقة من النظام بنجاح.'
            ], Response::HTTP_OK);
            
        } catch (Exception $e) {
            return response()->json([
                'status'  => false, 
                'message' => $e->getMessage()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    // =========================================================================
    // 🏢 دوال البلديات الكبرى (Municipalities)
    // =========================================================================

    public function indexMunicipalities(): JsonResponse
    {
        $municipalities = $this->zoneService->getAllMunicipalities();
        return response()->json([
            'status' => true,
            'message' => 'تم جلب قائمة البلديات الكبرى بنجاح.',
            'data'   => $municipalities
        ], Response::HTTP_OK);
    }

    public function storeMunicipality(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:100']);
        try {
            $muni = $this->zoneService->createMunicipality($request->all());
            return response()->json([
                'status'  => true,
                'message' => 'تم إضافة البلدية الكبرى بنجاح.',
                'data'    => $muni
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function updateMunicipality(Request $request, int $id): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:100']);
        try {
            $muni = Municipality::findOrFail($id);
            $updated = $this->zoneService->updateMunicipality($muni, $request->all());
            return response()->json([
                'status'  => true,
                'message' => 'تم تحديث اسم البلدية بنجاح.',
                'data'    => $updated
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroyMunicipality(int $id): JsonResponse
    {
        try {
            $muni = Municipality::findOrFail($id);
            $this->zoneService->deleteMunicipality($muni);
            return response()->json([
                'status'  => true,
                'message' => 'تم حذف البلدية بنجاح.'
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // 🏘️ دوال البلديات الفرعية / المحلات (SubMunicipalities)
    // =========================================================================

    public function indexSubMunicipalities(): JsonResponse
    {
        $subs = $this->zoneService->getAllSubMunicipalities();
        return response()->json([
            'status'  => true,
            'message' => 'تم جلب قائمة البلديات الفرعية بنجاح.',
            'data'    => $subs
        ], Response::HTTP_OK);
    }

    public function storeSubMunicipality(Request $request): JsonResponse
    {
        $request->validate([
            'name'            => 'required|string|max:100',
            'municipality_id' => 'required|exists:municipalities,id'
        ]);
        try {
            $sub = $this->zoneService->createSubMunicipality($request->all());
            return response()->json([
                'status'  => true,
                'message' => 'تم إضافة البلدية الفرعية بنجاح.',
                'data'    => $sub
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function updateSubMunicipality(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name'            => 'sometimes|required|string|max:100',
            'municipality_id' => 'sometimes|required|exists:municipalities,id'
        ]);
        try {
            $sub = SubMunicipality::findOrFail($id);
            $updated = $this->zoneService->updateSubMunicipality($sub, $request->all());
            return response()->json([
                'status'  => true,
                'message' => 'تم تحديث بيانات البلدية الفرعية بنجاح.',
                'data'    => $updated
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroySubMunicipality(int $id): JsonResponse
    {
        try {
            $sub = SubMunicipality::findOrFail($id);
            $this->zoneService->deleteSubMunicipality($sub);
            return response()->json([
                'status'  => true,
                'message' => 'تم حذف البلدية الفرعية بنجاح.'
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }
}