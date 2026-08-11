<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreMunicipalityRequest;
use App\Http\Resources\Api\Admin\MunicipalityResource;
use App\Models\Shared\Municipality;
use App\Services\Admin\GeographyService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 🏛️ إدارة البلديات في لوحة التحكم — المستوى الأول في الهرم الجغرافي.
 *
 * بلدية ← محلة ← منطقة
 */
class MunicipalityController extends Controller
{
    protected GeographyService $geography;

    public function __construct(GeographyService $geography)
    {
        $this->geography = $geography;
    }

    /**
     * 📋 عرض كل البلديات
     * GET /api/admin/municipalities?search=&with_children=1
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $municipalities = $this->geography->getAllMunicipalities(
                $request->query('search'),
                $request->boolean('with_children')
            );

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب قائمة البلديات بنجاح.',
                'data'    => MunicipalityResource::collection($municipalities),
            ], 200);

        } catch (Exception $e) {
            Log::error('Fetch Municipalities Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'حدث خطأ في النظام.'], 500);
        }
    }

    /**
     * 🔍 عرض بلدية واحدة مع محلاتها
     * GET /api/admin/municipalities/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            $municipality = Municipality::with(['subMunicipalities' => fn ($q) => $q->withCount('zones')->orderBy('name')])
                ->withCount(['subMunicipalities', 'zones'])
                ->find($id);

            if (!$municipality) {
                return response()->json(['status' => false, 'message' => 'عذراً، البلدية غير موجودة.'], 404);
            }

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب بيانات البلدية بنجاح.',
                'data'    => new MunicipalityResource($municipality),
            ], 200);

        } catch (Exception $e) {
            Log::error('Show Municipality Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'حدث خطأ في النظام.'], 500);
        }
    }

    /**
     * ➕ إضافة بلدية
     * POST /api/admin/municipalities
     */
    public function store(StoreMunicipalityRequest $request): JsonResponse
    {
        try {
            $municipality = $this->geography->createMunicipality($request->validated());

            return response()->json([
                'status'  => true,
                'message' => 'تم إضافة البلدية بنجاح.',
                'data'    => new MunicipalityResource($municipality->loadCount(['subMunicipalities', 'zones'])),
            ], 201);

        } catch (Exception $e) {
            Log::error('Store Municipality Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'تعذر إضافة البلدية.'], 500);
        }
    }

    /**
     * ✏️ تعديل اسم بلدية
     * POST /api/admin/municipalities/{id}
     */
    public function update(StoreMunicipalityRequest $request, $id): JsonResponse
    {
        try {
            $municipality = Municipality::find($id);

            if (!$municipality) {
                return response()->json(['status' => false, 'message' => 'عذراً، البلدية غير موجودة.'], 404);
            }

            $updated = $this->geography->updateMunicipality($municipality, $request->validated());

            return response()->json([
                'status'  => true,
                'message' => 'تم تحديث اسم البلدية بنجاح.',
                'data'    => new MunicipalityResource($updated->loadCount(['subMunicipalities', 'zones'])),
            ], 200);

        } catch (Exception $e) {
            Log::error('Update Municipality Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'تعذر تحديث البلدية.'], 500);
        }
    }

    /**
     * 🗑️ حذف بلدية (مع كل مناطقها)
     * DELETE /api/admin/municipalities/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $municipality = Municipality::find($id);

            if (!$municipality) {
                return response()->json(['status' => false, 'message' => 'عذراً، البلدية غير موجودة.'], 404);
            }

            $name       = $municipality->name;
            $zonesCount = $municipality->zones()->count();
            $subsCount  = $municipality->subMunicipalities()->count();

            $this->geography->deleteMunicipality($municipality);

            return response()->json([
                'status'  => true,
                'message' => "تم حذف بلدية ({$name}) وما تتبعها من {$subsCount} محلة و{$zonesCount} منطقة بنجاح.",
            ], 200);

        } catch (Exception $e) {
            // رسالة الخدمة تشرح سبب المنع بالتفصيل (سائقون/مدارس/عناوين)
            return response()->json(['status' => false, 'message' => $e->getMessage()], 409);
        }
    }
}
