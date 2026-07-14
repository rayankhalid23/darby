<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Shared\Route as RouteModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class DriverRouteController
 * يدير عمليات عرض وتحديث مسارات السائقين ضمن منظومة النقل.
 */
class DriverRouteController extends Controller
{
    /**
     * جلب قائمة المسارات النشطة للسائق الحالي مع تفاصيلها.
     * * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            // 1. التحقق من هوية السائق بأسلوب دفاعي
            $user = Auth::user();
            if (!$user || !$user->driver) {
                return response()->json([
                    'success' => false, 
                    'message' => 'بيانات السائق غير مرتبطة بهذا الحساب.'
                ], 403);
            }

            $driverId = $user->driver->id;

            // 2. استخدام Eager Loading لتحسين الأداء ومنع N+1 Query Problem
            $routes = RouteModel::where('driver_id', $driverId)
                ->where('status', 'Active') // جلب المسارات النشطة فقط
                ->with([
                    'contract' => function($query) {
                        $query->select('id', 'contract_number', 'subscription_request_id');
                    },
                    'contract.subscriptionRequest' => function($query) {
                        $query->select('id', 'parent_id', 'school_id', 'total_price');
                    }
                ])
                ->latest() // ترتيب تنازلي حسب الأحدث
                ->get();

            // 3. معالجة حالة عدم وجود مسارات (لا نعتبرها خطأ برمجياً بل حالة طبيعية)
            if ($routes->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'لا توجد مسارات نشطة حالياً.',
                    'data'    => []
                ], 200);
            }

            return response()->json([
                'success' => true,
                'count'   => $routes->count(),
                'data'    => $routes
            ], 200);

        } catch (Throwable $e) {
            // 4. معالجة أخطاء عبقرية: تسجيل الخطأ في السيرفر مع إخفاء التفاصيل عن المستخدم النهائي
            Log::error('خطأ في جلب مسارات السائق (DriverRouteController): ' . $e->getMessage(), [
                'driver_id' => Auth::id(),
                'trace'     => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'تعذر جلب المسارات حالياً، يرجى التواصل مع الدعم الفني.'
            ], 500);
        }
    }
}