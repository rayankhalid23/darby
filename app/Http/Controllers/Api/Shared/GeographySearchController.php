<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\Shared\Municipality;
use App\Models\Shared\SubMunicipality;
use App\Models\Shared\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GeographySearchController extends Controller
{
    /**
     * 🔍 البحث في التقسيمات الجغرافية (بلدية، بلدية فرعية، منطقة)
     *
     * GET / POST /api/geography/search
     *
     * Parameters:
     * - search_keyword: نص البحث (string)
     * - type: نوع الفلتر (municipality, sub_municipality, region)
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search_keyword' => 'required|string',
            'type'           => 'required|string|in:municipality,sub_municipality,region,zone',
        ], [
            'search_keyword.required' => 'يرجى إدخال كلمة البحث (search_keyword).',
            'type.required'           => 'يرجى تحديد نوع البحث (type).',
            'type.in'                 => 'نوع البحث يجب أن يكون أحد الخيارات التالية: municipality, sub_municipality, region.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first(),
                'data'    => [],
            ], 422);
        }

        $searchKeyword = trim($request->input('search_keyword'));
        $rawType = strtolower(trim($request->input('type')));

        // توحيد المسمى في حال إرسال zone كمرادف لـ region
        $type = ($rawType === 'zone') ? 'region' : $rawType;

        $data = collect();
        $emptyMessage = 'لا توجد نتائج مطابقة للبحث';

        switch ($type) {
            case 'municipality':
                $data = Municipality::query()
                    ->where('name', 'LIKE', "%{$searchKeyword}%")
                    ->select(['id', 'name'])
                    ->orderBy('name')
                    ->get();
                $emptyMessage = 'لا توجد بلدية بهذا الاسم';
                break;

            case 'sub_municipality':
                $data = SubMunicipality::query()
                    ->where('name', 'LIKE', "%{$searchKeyword}%")
                    ->select(['id', 'name', 'municipality_id'])
                    ->orderBy('name')
                    ->get();
                $emptyMessage = 'لا توجد بلدية فرعية بهذا الاسم';
                break;

            case 'region':
                $data = Zone::query()
                    ->where('name', 'LIKE', "%{$searchKeyword}%")
                    ->select(['id', 'name', 'sub_municipality_id'])
                    ->orderBy('name')
                    ->get();
                $emptyMessage = 'لا توجد منطقة بهذا الاسم';
                break;
        }

        if ($data->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => $emptyMessage,
                'data'    => [],
            ], 200);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'تم العثور على النتائج',
            'data'    => $data,
        ], 200);
    }
}
