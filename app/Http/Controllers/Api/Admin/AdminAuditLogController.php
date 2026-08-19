<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class AdminAuditLogController extends Controller
{
    /**
     * 📜 عرض سجل إجراءات المشرفين والمدراء (الأدمن فقط)
     * GET /api/admin/admin-audit-logs
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // 🔒 حظر الوصول للمشرفين العاديين والمستخدمين الآخرين (مسموح للأدمن role_id = 1 فقط)
        if (!$user || (int) $user->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، هذا السجل مخصص للإدارة العامة فقط.',
                'errors'  => (object) [],
            ], 403);
        }

        try {
            $perPage = (int) $request->query('per_page', 20);
            $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 20;

            $query = AdminAuditLog::query()->orderBy('id', 'desc');

            // 1. فلترة المشرف المنفذ
            if ($request->filled('admin_id')) {
                $query->where('admin_id', $request->query('admin_id'));
            }

            // 2. فلترة نوع العنصر (entity_type)
            if ($request->filled('entity_type')) {
                $query->where('entity_type', $request->query('entity_type'));
            }

            // 3. فلترة الإجراء المباشر (action)
            if ($request->filled('action')) {
                $query->where('action', $request->query('action'));
            }

            // 4. فلترة عائلة الإجراء (action_group: decision / update / operation)
            if ($request->filled('action_group')) {
                $group = $request->query('action_group');
                $matchingActions = [];
                foreach (AdminAuditLog::$actionMap as $actKey => $meta) {
                    if ($meta['group'] === $group) {
                        $matchingActions[] = $actKey;
                    }
                }
                if (!empty($matchingActions)) {
                    $query->whereIn('action', $matchingActions);
                } else {
                    $query->whereRaw('1 = 0'); // لا توجد نتائج
                }
            }

            // 5. فلترة التاريخ (date_from / date_to)
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->query('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->query('date_to'));
            }

            // 6. البحث النصي الذكي في اسم المشرف أو اسم العنصر أو الملاحظة
            if ($request->filled('search')) {
                $search = $request->query('search');
                $query->where(function ($q) use ($search) {
                    $q->where('admin_name', 'like', "%{$search}%")
                      ->orWhere('entity_name', 'like', "%{$search}%")
                      ->orWhere('reason', 'like', "%{$search}%");
                });
            }

            $paginated = $query->paginate($perPage);

            $data = collect($paginated->items())->map(function (AdminAuditLog $log) {
                return [
                    'id'           => $log->id,
                    'admin_id'     => $log->admin_id,
                    'admin_name'   => $log->admin_name,
                    'admin_role'   => $log->admin_role,
                    'action'       => $log->action,
                    'action_label' => $log->action_label,
                    'action_group' => $log->action_group,
                    'entity_type'  => $log->entity_type,
                    'entity_id'    => $log->entity_id,
                    'entity_name'  => $log->entity_name,
                    'result'       => $log->result,
                    'reason'       => $log->reason,
                    'changes'      => $log->changes ?? [],
                    'created_at'   => $log->created_at?->toISOString() ?? $log->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'تم جلب سجل إجراءات المشرفين بنجاح.',
                'data'    => $data,
                'meta'    => [
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب سجلات التدقيق: ' . $e->getMessage(),
                'errors'  => (object) [],
            ], 500);
        }
    }

    /**
     * 🔍 عرض تفاصيل سطر سجل واحد
     * GET /api/admin/admin-audit-logs/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user || (int) $user->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، هذا السجل مخصص للإدارة العامة فقط.',
                'errors'  => (object) [],
            ], 403);
        }

        $log = AdminAuditLog::find($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، سجل الإجراء المطلوب غير موجود.',
                'errors'  => (object) [],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب تفاصيل سجل الإجراء بنجاح.',
            'data'    => [
                'id'           => $log->id,
                'admin_id'     => $log->admin_id,
                'admin_name'   => $log->admin_name,
                'admin_role'   => $log->admin_role,
                'action'       => $log->action,
                'action_label' => $log->action_label,
                'action_group' => $log->action_group,
                'entity_type'  => $log->entity_type,
                'entity_id'    => $log->entity_id,
                'entity_name'  => $log->entity_name,
                'result'       => $log->result,
                'reason'       => $log->reason,
                'changes'      => $log->changes ?? [],
                'created_at'   => $log->created_at?->toISOString() ?? $log->created_at,
            ]
        ], 200);
    }
}
