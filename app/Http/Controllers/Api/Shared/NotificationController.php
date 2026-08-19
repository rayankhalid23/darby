<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                'id'          => $item->id,
                'type'        => $data['type'] ?? 'general',
                'title'       => $data['title'] ?? 'إشعار جديد',
                'message'     => $data['message'] ?? '',
                'action_url'  => $data['action_url'] ?? null,
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
     * تسجيل أو تحديث توكن FCM الخاص بالجهاز.
     *
     * idempotent بالكامل: نداء متكرر بنفس (user, device_id, fcm_token) يحدّث نفس الصف
     * بلا تكرار. المطابقة الأساسية تتم عبر fcm_token (فريد عالمياً، لأنه معرّف Firebase
     * الحقيقي لتثبيت التطبيق)، مع تنظيف أي صف سابق لنفس الجهاز عند تغيّر التوكن
     * (token refresh) حتى لا تتراكم صفوف قديمة لنفس الجهاز.
     *
     * سياسة الملكية (Ownership Policy): fcm_token واحد يمكن أن ينتقل شرعياً لمستخدم آخر
     * فقط عندما يكون device_id مطابقاً لما كان مسجّلاً سابقاً لهذا التوكن بالضبط — وهو ما
     * يثبت استمرارية نفس الجهاز الفعلي (تسجيل خروج مستخدم A ثم دخول مستخدم B على نفس
     * الهاتف). أما إن وصل توكن مملوك حالياً لمستخدم آخر مع device_id مختلف، فهذا يُعامَل
     * كمحاولة استيلاء (سواء توكن مسرّب أو مُعاد استخدامه خطأً) ونرفضه صراحة بدل نقل الملكية
     * ضمنياً — لأن نقل الملكية بصمت يعني توجيه إشعارات مستخدم B الخاصة (مالية، رحلات...)
     * إلى الجهاز الفعلي لمستخدم A، وحرمان A من إشعاراته الخاصة.
     */
    public function storeDeviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token'   => 'required|string',
            'device_id'   => 'required|string',
            'device_name' => 'nullable|string',
            'platform'    => 'nullable|string|in:ios,android,web',
            'app_version' => 'nullable|string',
        ], [
            'fcm_token.required' => 'رمز الإشعارات (FCM Token) مطلوب.',
            'device_id.required' => 'معرّف الجهاز (device_id) مطلوب.',
            'platform.in'        => 'نظام التشغيل (platform) يجب أن يكون أحد القيم: ios, android, web.',
        ]);

        $user = $request->user();
        $deviceId = $request->device_id;
        $token = $request->fcm_token;
        $deviceName = $request->device_name ?? 'mobile_device';
        $platform = in_array($request->platform, ['ios', 'android', 'web']) ? $request->platform : 'android';
        $appVersion = $request->app_version;

        $existingByToken = UserDevice::where('fcm_token', $token)->first();

        if ($existingByToken && $existingByToken->user_id !== $user->id && $existingByToken->device_id !== $deviceId) {
            Log::warning("Device token ownership conflict: user #{$user->id} attempted to claim fcm_token currently owned by user #{$existingByToken->user_id} with a mismatched device_id (expected [{$existingByToken->device_id}], got [{$deviceId}]).");

            return response()->json([
                'status'     => false,
                'error_code' => 'DEVICE_TOKEN_CONFLICT',
                'message'    => 'تعذر تسجيل هذا الجهاز، الرجاء إعادة تسجيل الدخول من التطبيق على هذا الجهاز والمحاولة مجدداً.',
            ], 409);
        }

        DB::transaction(function () use ($user, $deviceId, $token, $deviceName, $platform, $appVersion) {
            UserDevice::where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->where('fcm_token', '!=', $token)
                ->delete();

            UserDevice::updateOrCreate(
                ['fcm_token' => $token],
                [
                    'user_id'        => $user->id,
                    'device_id'      => $deviceId,
                    'device_name'    => $deviceName,
                    'platform'       => $platform,
                    'app_version'    => $appVersion,
                    'is_active'      => true,
                    'last_active_at' => Carbon::now(),
                ]
            );
        });

        return response()->json([
            'status'  => true,
            'message' => 'تم تسجيل رمز الإشعارات للجهاز بنجاح.',
        ]);
    }

    /**
     * إلغاء/حذف توكن FCM لجهاز واحد محدد فقط (تسجيل خروج طبيعي).
     * يتطلب device_id أو fcm_token صراحة — لا يحذف كل أجهزة المستخدم افتراضياً.
     */
    public function removeDeviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required_without:fcm_token|string',
            'fcm_token' => 'required_without:device_id|string',
        ], [
            'device_id.required_without' => 'يجب تحديد device_id أو fcm_token لتحديد الجهاز المراد حذفه.',
            'fcm_token.required_without' => 'يجب تحديد device_id أو fcm_token لتحديد الجهاز المراد حذفه.',
        ]);

        $user = $request->user();
        $query = UserDevice::where('user_id', $user->id);

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        } else {
            $query->where('fcm_token', $request->fcm_token);
        }

        $query->delete();

        return response()->json([
            'status'  => true,
            'message' => 'تم إزالة رمز الإشعارات للجهاز بنجاح.',
        ]);
    }

    /**
     * تسجيل الخروج من كل الأجهزة المسجّلة لهذا المستخدم (سلوك صريح فقط، وليس افتراضياً).
     */
    public function logoutAllDevices(Request $request): JsonResponse
    {
        $user = $request->user();
        $removed = UserDevice::where('user_id', $user->id)->delete();

        return response()->json([
            'status'  => true,
            'message' => 'تم تسجيل الخروج من جميع الأجهزة بنجاح.',
            'removed' => $removed,
        ]);
    }
}
