<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AdminAlert;
use App\Models\Driver\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class AdminDriverActionController extends Controller
{
    /**
     * ✅ تمييز التنبيه كمحسوم ومحلول (Resolve Alert)
     * POST /api/v1/admin/ai-alerts/{id}/resolve
     */
    public function resolveAlert(int $id): JsonResponse
    {
        try {
            $alert = AdminAlert::find($id);

            if (!$alert) {
                return response()->json([
                    'status'  => false,
                    'message' => 'عذراً، التنبيه غير موجود بالسيستم.',
                ], 404);
            }

            $alert->update([
                'is_resolved' => true,
                'is_read'     => true,
            ]);

            Log::info("AdminAlert [Resolve] - تم تمييز التنبيه رقم {$id} كمحسوم بواسطة الإدارة.");

            return response()->json([
                'status'  => true,
                'message' => 'تمت تسوية وتأكيد حل التنبيه بنجاح.',
                'data'    => [
                    'alert_id'    => $alert->id,
                    'is_resolved' => true,
                ],
            ]);
        } catch (Exception $e) {
            Log::error("AdminAlert [Resolve] Error: " . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'حدث خطأ أثناء معالجة حسم التنبيه: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 🔓 إعادة تفعيل وإلغاء حظر السائق يدوياً (Unblock Driver)
     * POST /api/v1/admin/drivers/{driverId}/unblock
     */
    public function unblockDriver(int $driverId): JsonResponse
    {
        try {
            $driver = Driver::with('user')->find($driverId);

            if (!$driver) {
                return response()->json([
                    'status'  => false,
                    'message' => 'عذراً، السائق غير موجود بالسيستم.',
                ], 404);
            }

            // إعادة تفعيل السائق وإتاحة ظهوره في نتائج البحث والفلترة
            $driver->update([
                'status'        => 'Approved',
                'is_searchable' => true,
            ]);

            if ($driver->user) {
                $driver->user->update(['is_active' => true]);
            }

            // حسم التنبيهات المرتبطة بهذا السائق تلقائياً عند إلغاء الحظر
            AdminAlert::where('driver_id', $driverId)->update([
                'is_resolved' => true,
                'is_read'     => true,
            ]);

            Log::info("AdminDriverAction [Unblock] - تم إلغاء حظر السائق رقم {$driverId} وإتاحة ظهوره للبحث.");

            return response()->json([
                'status'  => true,
                'message' => 'تم إلغاء حظر السائق وإعادة تفعيله للبحث والظهور بنجاح.',
                'data'    => [
                    'driver_id'     => $driver->id,
                    'name'          => $driver->user?->full_name,
                    'status'        => 'Approved',
                    'is_searchable' => true,
                ],
            ]);
        } catch (Exception $e) {
            Log::error("AdminDriverAction [Unblock] Error: " . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'حدث خطأ أثناء إلغاء حظر السائق: ' . $e->getMessage(),
            ], 500);
        }
    }
}
