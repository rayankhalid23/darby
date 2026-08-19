<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إشعارات لوحة تحكم الأدمن — نفس بنية الإشعارات القياسية (جدول notifications)
 * لكن مقيّدة بالمستخدمين ذوي role_id ضمن [1, 2] (مدير النظام / مشرف) فقط.
 */
class AdminNotificationController extends Controller
{
    private const ADMIN_ROLE_IDS = [1, 2];

    /**
     * يُرجع استجابة 403 جاهزة إن لم يكن المستخدم أدمن، أو null للسماح بالمتابعة.
     * ملاحظة: لا نستخدم abort() هنا لأن معالج الأخطاء العام في bootstrap/app.php
     * يحوّل أي استثناء HTTP غير مصنّف صراحة إلى 500 SERVER_ERROR.
     */
    private function denyIfNotAdmin(Request $request): ?JsonResponse
    {
        $roleId = (int) ($request->user()->role_id ?? 0);

        if (!in_array($roleId, self::ADMIN_ROLE_IDS, true)) {
            return response()->json([
                'status'     => false,
                'error_code' => 'FORBIDDEN',
                'message'    => 'هذا المسار متاح فقط لحسابات الإدارة.',
            ], 403);
        }

        return null;
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfNotAdmin($request)) {
            return $deny;
        }

        $user = $request->user();
        $query = $user->notifications();

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        if ($request->has('type')) {
            $query->where('data->type', $request->query('type'));
        }

        $perPage = (int) $request->query('per_page', 15);
        $notifications = $query->paginate($perPage);

        $formattedData = collect($notifications->items())->map(function ($item) {
            $data = is_string($item->data) ? json_decode($item->data, true) : $item->data;

            return [
                'id'          => $item->id,
                'type'        => $data['type'] ?? 'general',
                'title'       => $data['title'] ?? 'إشعار جديد',
                'message'     => $data['message'] ?? '',
                'entity_type' => $data['entity_type'] ?? null,
                'entity_id'   => $data['entity_id'] ?? null,
                'screen'      => $data['screen'] ?? 'HOME',
                'action'      => $data['action'] ?? 'open',
                'payload'     => $data['payload'] ?? [],
                'read_at'     => $item->read_at ? $item->read_at->toIso8601String() : null,
                'is_read'     => !is_null($item->read_at),
                'created_at'  => $item->created_at ? $item->created_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => [
                'notifications' => $formattedData,
                'pagination'    => [
                    'current_page' => $notifications->currentPage(),
                    'last_page'    => $notifications->lastPage(),
                    'per_page'     => $notifications->perPage(),
                    'total'        => $notifications->total(),
                    'has_more'     => $notifications->hasMorePages(),
                ],
                'unread_count'  => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfNotAdmin($request)) {
            return $deny;
        }

        return response()->json([
            'status'       => true,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        if ($deny = $this->denyIfNotAdmin($request)) {
            return $deny;
        }

        $notification = $request->user()->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json([
                'status'     => false,
                'error_code' => 'NOTIFICATION_NOT_FOUND',
                'message'    => 'الإشعار غير موجود أو لا تملك صلاحية الوصول إليه.',
            ], 404);
        }

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return response()->json([
            'status'  => true,
            'message' => 'تم تمييز الإشعار كمقروء بنجاح.',
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        if ($deny = $this->denyIfNotAdmin($request)) {
            return $deny;
        }

        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'status'  => true,
            'message' => 'تم تمييز جميع الإشعارات كمقروءة بنجاح.',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($deny = $this->denyIfNotAdmin($request)) {
            return $deny;
        }

        $notification = $request->user()->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json([
                'status'     => false,
                'error_code' => 'NOTIFICATION_NOT_FOUND',
                'message'    => 'الإشعار غير موجود.',
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'status'  => true,
            'message' => 'تم حذف الإشعار بنجاح.',
        ]);
    }
}
