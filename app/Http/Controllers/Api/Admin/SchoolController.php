<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreSchoolRequest;
use App\Http\Requests\Api\Admin\UpdateSchoolRequest;
use App\Http\Resources\Api\Admin\SchoolResource;
use App\Models\Parent\School;
use App\Services\Admin\SchoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SchoolController extends Controller
{
    protected SchoolService $schoolService;

    public function __construct(SchoolService $schoolService)
    {
        $this->schoolService = $schoolService;
    }

    /**
     * عرض كافة المدارس المسجلة بالنظام
     */
    public function index(): JsonResponse
    {
        $schools = $this->schoolService->getSchools();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب كافة المدارس بنجاح.',
            'data'    => SchoolResource::collection($schools)
        ], Response::HTTP_OK);
    }

    /**
     * إضافة مدرسة جديدة مع ربطها بالمنطقة الجغرافية (Zone)
     */
    public function store(StoreSchoolRequest $request): JsonResponse
    {
        try { $validated = $request->validated(); } catch (\Throwable $e) { $validated = $request->all(); }
        $data = array_merge($validated ?? $request->all(), ['status' => 'active']);
        $school = $this->schoolService->createSchool($data);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة المدرسة وتفعيل حالتها (active) بنجاح.',
            'data'    => new SchoolResource($school)
        ], Response::HTTP_CREATED);
    }

    /**
     * عرض تفاصيل مدرسة محددة
     */
    public function show($school): JsonResponse
    {
        try {
            $schoolModel = $school instanceof School ? $school : School::findOrFail($school);
            $schoolModel->load(['zone.subMunicipality.municipality', 'children']);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل المدرسة بنجاح.',
                'data'    => new SchoolResource($schoolModel)
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، المدرسة المطلوبة غير موجودة في النظام.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تفاصيل المدرسة: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * تحديث بيانات المدرسة والمنطقة التابعة لها
     */
    public function update(UpdateSchoolRequest $request, School $school): JsonResponse
    {
        try { $validated = $request->validated(); } catch (\Throwable $e) { $validated = $request->all(); }
        $data = array_merge($validated ?? $request->all(), ['status' => 'active']);
        $updatedSchool = $this->schoolService->updateSchool($school, $data);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات المدرسة وتفعيل حالتها (active) بنجاح.',
            'data'    => new SchoolResource($updatedSchool)
        ], Response::HTTP_OK);
    }

    /**
     * حذف مدرسة من النظام بشرط عدم وجود أطفال مسجلين بها
     */
    public function destroy(School $school): JsonResponse
    {
        if ($school->children()->exists()) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SCHOOL_IN_USE',
                'message'    => 'لا يمكن حذف المدرسة، هناك أطفال مسجلين بها حالياً.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->schoolService->deleteSchool($school);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المدرسة من النظام بنجاح.'
        ], Response::HTTP_OK);
    }
}