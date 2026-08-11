<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreAdminZoneRequest;
use App\Http\Resources\Api\Admin\ZoneResource;
use App\Models\Shared\Municipality;
use App\Models\Shared\SubMunicipality;
use App\Models\Shared\Zone;
use App\Services\Admin\GeographyService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 📍 إدارة المناطق — المستوى الثالث. كل منطقة تتبع محلة إجبارياً.
 *
 * ⚠️ هذا الكنترولر خاص بلوحة الأدمن فقط. المسار القديم
 *    (Api\Admin\ZoneController) بقي كما هو دون أي تعديل لأن تطبيقي
 *    السائق وولي الأمر يعتمدان عليه.
 */
class MunicipalityZoneController extends Controller
{
    protected GeographyService $geography;

    public function __construct(GeographyService $geography)
    {
        $this->geography = $geography;
    }

    /**
     * 📋 عرض مناطق محلة معينة
     * GET /api/admin/sub-municipalities/{subMunicipalityId}/zones?search=
     */
    public function index(Request $request, $subMunicipalityId): JsonResponse
    {
        try {
            $sub = SubMunicipality::with('municipality')->find($subMunicipalityId);

            if (!$sub) {
                return response()->json(['status' => false, 'message' => 'عذراً، المحلة غير موجودة.'], 404);
            }

            $zones = $this->geography->getZonesOfSubMunicipality($sub, $request->query('search'));
            $zones->load('subMunicipality.municipality');
            $this->geography->attachUsageCounts($zones);

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب مناطق المحلة بنجاح.',
                'data'    => [
                    'municipality' => [
                        'id'   => $sub->municipality?->id,
                        'name' => $sub->municipality?->name,
                    ],
                    'sub_municipality' => [
                        'id'   => $sub->id,
                        'name' => $sub->name,
                    ],
                    'zones' => ZoneResource::collection($zones),
                ],
            ], 200);

        } catch (Exception $e) {
            Log::error('Fetch SubMunicipality Zones Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'حدث خطأ في النظام.'], 500);
        }
    }

    /**
     * 📋 عرض كل مناطق بلدية (مسطّحة عبر كل محلاتها)
     * GET /api/admin/municipalities/{municipalityId}/zones?search=
     */
    public function indexByMunicipality(Request $request, $municipalityId): JsonResponse
    {
        try {
            $municipality = Municipality::find($municipalityId);

            if (!$municipality) {
                return response()->json(['status' => false, 'message' => 'عذراً، البلدية غير موجودة.'], 404);
            }

            $zones = $this->geography->getZonesOfMunicipality($municipality, $request->query('search'));
            $zones->load('subMunicipality.municipality');
            $this->geography->attachUsageCounts($zones);

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب مناطق البلدية بنجاح.',
                'data'    => [
                    'municipality' => [
                        'id'   => $municipality->id,
                        'name' => $municipality->name,
                    ],
                    'zones' => ZoneResource::collection($zones),
                ],
            ], 200);

        } catch (Exception $e) {
            Log::error('Fetch Municipality Zones Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'حدث خطأ في النظام.'], 500);
        }
    }

    /**
     * ➕ إضافة منطقة تحت محلة
     * POST /api/admin/sub-municipalities/{subMunicipalityId}/zones
     */
    public function store(StoreAdminZoneRequest $request, $subMunicipalityId): JsonResponse
    {
        try {
            $sub = SubMunicipality::find($subMunicipalityId);

            if (!$sub) {
                return response()->json(['status' => false, 'message' => 'عذراً، المحلة غير موجودة.'], 404);
            }

            $zone = $this->geography->createZone($sub, $request->validated());

            return response()->json([
                'status'  => true,
                'message' => 'تم إضافة المنطقة بنجاح.',
                'data'    => new ZoneResource($zone->load('subMunicipality.municipality')),
            ], 201);

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 🔍 عرض تفاصيل منطقة
     * GET /api/admin/zones/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            $zone = Zone::with('subMunicipality.municipality')->find($id);

            if (!$zone) {
                return response()->json(['status' => false, 'message' => 'عذراً، المنطقة غير موجودة.'], 404);
            }

            $this->geography->attachUsageCounts(collect([$zone]));

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب بيانات المنطقة بنجاح.',
                'data'    => new ZoneResource($zone),
            ], 200);

        } catch (Exception $e) {
            Log::error('Show Zone Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'حدث خطأ في النظام.'], 500);
        }
    }

    /**
     * ✏️ تعديل اسم منطقة
     * POST /api/admin/zones/{id}
     */
    public function update(StoreAdminZoneRequest $request, $id): JsonResponse
    {
        try {
            $zone = Zone::with('subMunicipality.municipality')->find($id);

            if (!$zone) {
                return response()->json(['status' => false, 'message' => 'عذراً، المنطقة غير موجودة.'], 404);
            }

            $updated = $this->geography->updateZone($zone, $request->validated());

            return response()->json([
                'status'  => true,
                'message' => 'تم تحديث اسم المنطقة بنجاح.',
                'data'    => new ZoneResource($updated->load('subMunicipality.municipality')),
            ], 200);

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 🗑️ حذف منطقة
     * DELETE /api/admin/zones/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $zone = Zone::find($id);

            if (!$zone) {
                return response()->json(['status' => false, 'message' => 'عذراً، المنطقة غير موجودة.'], 404);
            }

            $name = $zone->name;
            $this->geography->deleteZone($zone);

            return response()->json([
                'status'  => true,
                'message' => "تم حذف منطقة ({$name}) بنجاح.",
            ], 200);

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 409);
        }
    }
}
