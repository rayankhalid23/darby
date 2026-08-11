<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreSubMunicipalityRequest;
use App\Http\Resources\Api\Admin\SubMunicipalityResource;
use App\Models\Shared\Municipality;
use App\Models\Shared\SubMunicipality;
use App\Services\Admin\GeographyService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 🏘️ إدارة المحلات — المستوى الثاني. كل محلة تتبع بلدية إجبارياً.
 */
class SubMunicipalityController extends Controller
{
    protected GeographyService $geography;

    public function __construct(GeographyService $geography)
    {
        $this->geography = $geography;
    }

    /**
     * 📋 عرض كل محلات بلدية معينة
     * GET /api/admin/municipalities/{municipalityId}/sub-municipalities?search=
     */
    public function index(Request $request, $municipalityId): JsonResponse
    {
        try {
            $municipality = Municipality::find($municipalityId);

            if (!$municipality) {
                return response()->json(['status' => false, 'message' => 'عذراً، البلدية غير موجودة.'], 404);
            }

            $subs = $this->geography->getSubMunicipalities($municipality, $request->query('search'));

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب محلات البلدية بنجاح.',
                'data'    => [
                    'municipality' => [
                        'id'   => $municipality->id,
                        'name' => $municipality->name,
                    ],
                    'sub_municipalities' => SubMunicipalityResource::collection($subs),
                ],
            ], 200);

        } catch (Exception $e) {
            Log::error('Fetch SubMunicipalities Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'حدث خطأ في النظام.'], 500);
        }
    }

    /**
     * ➕ إضافة محلة تحت بلدية
     * POST /api/admin/municipalities/{municipalityId}/sub-municipalities
     */
    public function store(StoreSubMunicipalityRequest $request, $municipalityId): JsonResponse
    {
        try {
            $municipality = Municipality::find($municipalityId);

            if (!$municipality) {
                return response()->json(['status' => false, 'message' => 'عذراً، البلدية غير موجودة.'], 404);
            }

            $sub = $this->geography->createSubMunicipality($municipality, $request->validated());

            return response()->json([
                'status'  => true,
                'message' => 'تم إضافة المحلة بنجاح.',
                'data'    => new SubMunicipalityResource($sub->load('municipality')->loadCount('zones')),
            ], 201);

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 🔍 عرض محلة واحدة مع مناطقها
     * GET /api/admin/sub-municipalities/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            $sub = SubMunicipality::with(['municipality', 'zones' => fn ($q) => $q->orderBy('name')])
                ->withCount('zones')
                ->find($id);

            if (!$sub) {
                return response()->json(['status' => false, 'message' => 'عذراً، المحلة غير موجودة.'], 404);
            }

            $this->geography->attachUsageCounts($sub->zones);

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب بيانات المحلة بنجاح.',
                'data'    => new SubMunicipalityResource($sub),
            ], 200);

        } catch (Exception $e) {
            Log::error('Show SubMunicipality Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'حدث خطأ في النظام.'], 500);
        }
    }

    /**
     * ✏️ تعديل اسم محلة
     * POST /api/admin/sub-municipalities/{id}
     */
    public function update(StoreSubMunicipalityRequest $request, $id): JsonResponse
    {
        try {
            $sub = SubMunicipality::with('municipality')->find($id);

            if (!$sub) {
                return response()->json(['status' => false, 'message' => 'عذراً، المحلة غير موجودة.'], 404);
            }

            $updated = $this->geography->updateSubMunicipality($sub, $request->validated());

            return response()->json([
                'status'  => true,
                'message' => 'تم تحديث اسم المحلة بنجاح.',
                'data'    => new SubMunicipalityResource($updated->load('municipality')->loadCount('zones')),
            ], 200);

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 🗑️ حذف محلة (مع كل مناطقها)
     * DELETE /api/admin/sub-municipalities/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $sub = SubMunicipality::find($id);

            if (!$sub) {
                return response()->json(['status' => false, 'message' => 'عذراً، المحلة غير موجودة.'], 404);
            }

            $name       = $sub->name;
            $zonesCount = $sub->zones()->count();

            $this->geography->deleteSubMunicipality($sub);

            return response()->json([
                'status'  => true,
                'message' => "تم حذف محلة ({$name}) وما تتبعها من {$zonesCount} منطقة بنجاح.",
            ], 200);

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 409);
        }
    }
}
