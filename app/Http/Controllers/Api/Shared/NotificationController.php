<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    /**
     * جلب قائمة إشعارات المستخدم الحالي مع تقسيم لصفحات
     */
    public function index(Request $request): JsonResponse
    {
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
                'id'         => $item->id,
                'type'       => $data['type'] ?? 'general',
                'title'      => $data['title'] ?? 'إشعار جديد',
                'message'    => $data['message'] ?? '',
                'action_url' => $data['action_url'] ?? null,
                'entity_id'  => $data['entity_id'] ?? null,
                'screen'     => $data['screen'] ?? 'HOME',
                'payload'    => $data['payload'] ?? [],
                'read_at'    => $item->read_at ? $item->read_at->toIso8601String() : null,
                'is_read'    => !is_null($item->read_at),
                'created_at' => $item->created_at ? $item->created_at->toIso8601String() : null,
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
            ]
        ]);
    }

    /**
     * إرجاع عدد الإشعارات غير المقروءة فقط
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $unreadCount = $request->user()->unreadNotifications()->count();

        return response()->json([
            'status'       => true,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * تحديد إشعار معين كمقروء
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
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

    /**
     * تحديد جميع الإشعارات كمقروءة
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'status'  => true,
            'message' => 'تم تمييز جميع الإشعارات كمقروءة بنجاح.',
        ]);
    }

    /**
     * حذف إشعار معين
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
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

    /**
     * تسجيل أو تحديث توكن FCM الخاص بالجهاز
     */
    public function storeDeviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token'   => 'required|string',
            'device_name' => 'nullable|string',
            'platform'    => 'nullable|string|in:ios,android,web',
        ], [
            'fcm_token.required' => 'رمز الإشعارات (FCM Token) مطلوب.',
            'platform.in'        => 'نظام التشغيل (platform) يجب أن يكون أحد القيم: ios, android, web.',
        ]);

        $user = $request->user();
        $deviceName = $request->device_name ?? 'mobile_device';
        $platform = in_array($request->platform, ['ios', 'android', 'web']) ? $request->platform : 'android';

        UserDevice::updateOrCreate(
            [
                'user_id'   => $user->id,
                'fcm_token' => $request->fcm_token,
            ],
            [
                'device_name'    => $deviceName,
                'platform'       => $platform,
                'last_active_at' => Carbon::now(),
            ]
        );

        return response()->json([
            'status'  => true,
            'message' => 'تم تسجيل رمز الإشعارات للجهاز بنجاح.',
        ]);
    }

    /**
     * إلغاء أو حذف توكن FCM عند تسجيل الخروج
     */
    public function removeDeviceToken(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->has('fcm_token')) {
            UserDevice::where('user_id', $user->id)
                ->where('fcm_token', $request->fcm_token)
                ->delete();
        } elseif ($request->has('device_name')) {
            UserDevice::where('user_id', $user->id)
                ->where('device_name', $request->device_name)
                ->delete();
        } else {
            UserDevice::where('user_id', $user->id)->delete();
        }

        return response()->json([
            'status'  => true,
            'message' => 'تم إزالة رمز الإشعارات للجهاز بنجاح.',
        ]);
    }
}
