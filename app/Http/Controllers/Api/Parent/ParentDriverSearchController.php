<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\FilterDriversRequest;
use App\Http\Resources\Api\Parent\ParentDriverSearchResource;
use App\Models\Driver\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ParentDriverSearchController extends Controller
{
    /**
     * البحث والفلترة المتقدمة للسائقين (متوافق مع حقول السعة الحقيقية في قاعدة البيانات)
     */
    public function index(FilterDriversRequest $request): JsonResponse
    {
        try {
            // الاستعلام الأساسي للسائقين الذين حالتهم Approved أو Active وقابلين للبحث (is_searchable = true)
            $query = Driver::query()
                ->whereIn('drivers.status', ['Active', 'Approved'])
                ->where('drivers.is_searchable', true)
                ->with(['user', 'vehicles']);

            // 1️⃣ الفلترة بالاسم أو الهاتف (باستخدام الحروف الأولى)
            if ($request->filled('keyword')) {
                $keyword = trim($request->keyword);
                $normalizedKeyword = str_replace(['أ', 'إ', 'آ'], 'ا', $keyword);

                $query->whereHas('user', function ($q) use ($keyword, $normalizedKeyword) {
                    $q->where(function($subQuery) use ($keyword, $normalizedKeyword) {
                        $subQuery->where('users.full_name', 'like', "{$keyword}%")
                                 ->orWhere('users.full_name', 'like', "{$normalizedKeyword}%")
                                 ->orWhereRaw("REPLACE(REPLACE(REPLACE(users.full_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا') LIKE ?", ["{$normalizedKeyword}%"])
                                 ->orWhere('users.phone_number', 'like', "{$keyword}%");
                    });
                });
            }

            // 2️⃣ فلترة الجنس (اختيارية)
            if ($request->filled('gender') && in_array($request->gender, ['male', 'female'])) {
                $query->where('drivers.gender', $request->gender);
            }

            // 3️⃣ فلترة الفترة الزمنية (اختيارية)
            if ($request->filled('shift') && $request->shift !== 'both' && $request->shift !== '') {
                $query->where('drivers.shift', $request->shift);
            }

            // 4️⃣ فلترة المناطق الاختيارية
            if ($request->filled('zones') && is_array($request->zones) && !empty($request->zones)) {
                $zoneIds = $request->zones;
                $query->whereHas('zones', function ($q) use ($zoneIds) {
                    $q->whereIn('zones.id', $zoneIds);
                });
            }

            // 5️⃣ فلترة السعة الركابية الذكية باستخدام الحقل الفعلي من الـ ERD: capacity_manual
            $requiredSeats = $request->filled('children_count') ? intval($request->children_count) : 1;
            $hasStudentsTable = \Illuminate\Support\Facades\Schema::hasTable('students');

            $query->whereHas('vehicles', function ($q) use ($requiredSeats, $hasStudentsTable) {
                if ($hasStudentsTable) {
                    // إذا كان جدول الطلاب موجوداً، نطرح العدد الفعلي منهم من الحقل capacity_manual
                    $q->whereRaw('(capacity_manual - (select count(*) from students where students.driver_id = drivers.id)) >= ?', [$requiredSeats]);
                } else {
                    // إذا كان جدول الطلاب غير موجود بعد، نتحقق مباشرة من الحقل الصحيح capacity_manual
                    $q->where('capacity_manual', '>=', $requiredSeats);
                }
            });

            // تنفيذ الاستعلام مع الـ Pagination
            $drivers = $query->paginate(15);

            return response()->json([
                'status'  => true,
                'message' => $drivers->isEmpty() ? 'لم يتم العثور على نتائج مطابقة.' : 'تمت الفلترة وجلب البيانات بنجاح.',
                'data'    => ParentDriverSearchResource::collection($drivers)->response()->getData(true)
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'FILTER_ERROR',
                'message'    => 'حدث خطأ أثناء المعالجة: ' . $e->getMessage()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}