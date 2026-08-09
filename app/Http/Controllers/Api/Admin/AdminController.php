<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Services\Admin\AdminService;
use App\Http\Requests\Api\Admin\StoreAdminRequest;
use App\Http\Requests\Api\Admin\UpdateAdminRequest;
use App\Http\Resources\Api\Admin\AdminResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Exception;

class AdminController extends Controller
{
    protected AdminService $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    /**
     * عرض قائمة المشرفين
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $admins = $this->adminService->getAllAdmins(
                (int) $request->query('per_page', 10),
                $request->query('search')
            );
            return response()->json([
                'status'  => true,
                'message' => 'تم جلب قائمة المشرفين بنجاح.',
                'data'    => AdminResource::collection($admins)->response()->getData(true)
            ], 200);
        } catch (Exception $e) {
            Log::error("Fetch Admins Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'حدث خطأ في النظام.'], 500);
        }
    }

    /**
     * إضافة مشرف جديد
     */
    public function store(StoreAdminRequest $request): JsonResponse
    {
        try {
            $admin = $this->adminService->createAdmin(
                $request->validated(),
                $request->file('avatar') ?? $request->file('avatar_url')
            );

            return response()->json([
                'status'  => true,
                'message' => 'تم إضافة المشرف بنجاح.',
                'data'    => new AdminResource($admin)
            ], 201);
        } catch (Exception $e) {
            Log::error("Store Admin Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'تعذر إضافة المشرف.'], 500);
        }
    }

    /**
     * عرض مشرف واحد بالتحديد
     */
    public function show($id): JsonResponse
    {
        try {
            $admin = Admin::with(['user', 'creator'])->whereHas('user')->find($id);

            if (!$admin) {
                return response()->json(['status' => false, 'message' => 'عذراً، المشرف غير موجود.'], 404);
            }

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب بيانات المشرف.',
                'data'    => new AdminResource($admin)
            ], 200);
        } catch (Exception $e) {
            Log::error("Show Admin Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'حدث خطأ في النظام.'], 500);
        }
    }

    /**
     * تعديل بيانات المشرف (يدعم التحديث الجزئي الموثق)
     */
    public function update(UpdateAdminRequest $request, $id): JsonResponse
    {
        try {
            $admin = Admin::with('user')->find($id);

            if (!$admin || !$admin->user) {
                return response()->json(['status' => false, 'message' => 'عذراً، المشرف غير موجود.'], 404);
            }

            $data = $request->validated();
            $emailChanged = false;

            if (!empty($data['email']) && $data['email'] !== $admin->user->email) {
                $newEmail = $data['email'];
                unset($data['email']); 

                $token = Str::random(40);

                $approveUrl = URL::signedRoute('admin.email.approve', ['token' => $token]);
                $rejectUrl  = URL::signedRoute('admin.email.reject', ['token' => $token]);

                Cache::put("admin_email_change_{$token}", [
                    'admin_user_id' => $admin->user_id,
                    'new_email'     => $newEmail
                ], now()->addMinutes(30));

                Log::info("=== Admin Email Change Request ===");
                Log::info("🟢 Approve Link: " . $approveUrl);
                Log::info("🔴 Reject Link: " . $rejectUrl);

                app(\App\Services\Shared\EmailService::class)->sendEmailChangeLink(
                    $newEmail, 
                    $admin->user->full_name, 
                    $approveUrl, 
                    $rejectUrl
                );

                $emailChanged = true;
            }

            $updatedAdmin = $this->adminService->updateAdmin(
                $admin,
                $data,
                $request->file('avatar') ?? $request->file('avatar_url')
            );

            return response()->json([
                'status'  => true,
                'message' => $emailChanged 
                    ? 'تم تحديث البيانات المرفقة بنجاح. أرسلنا رابط تأكيد لبريدك الجديد، يرجى مراجعته لتفعيله بكبسة زر.'
                    : 'تم تحديث بيانات المشرف بنجاح.',
                'data'    => new AdminResource($updatedAdmin) // سيعمل بكفاءة مطلقة بعد تنظيف الريسورس
            ], 200);

        } catch (Exception $e) {
            Log::error("Update Admin Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'تعذر تحديث البيانات: ' . $e->getMessage()], 500);
        }
    }

    /**
     * حذف مشرف نهائياً من النظام
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $admin = Admin::with('user')->whereHas('user')->find($id);

            if (!$admin) {
                return response()->json([
                    'status'  => false,
                    'message' => 'عذراً، المشرف غير موجود.'
                ], 404);
            }

            $currentUser = $request->user();

            // منع المشرف من حذف حسابه الشخصي بنفسه
            if ($currentUser && $currentUser->id === $admin->user_id) {
                return response()->json([
                    'status'  => false,
                    'message' => 'لا يمكنك حذف حسابك الشخصي من هنا.'
                ], 403);
            }

            // حماية حساب مدير النظام الأساسي من الحذف
            if ((int) $admin->user->role_id === 1) {
                return response()->json([
                    'status'  => false,
                    'message' => 'لا يمكن حذف حساب مدير النظام الأساسي.'
                ], 403);
            }

            $deletedName = $admin->user->full_name;

            $this->adminService->deleteAdmin($admin, $currentUser?->id ?? $admin->created_by);

            return response()->json([
                'status'  => true,
                'message' => "تم حذف المشرف ({$deletedName}) نهائياً بنجاح."
            ], 200);

        } catch (Exception $e) {
            Log::error("Delete Admin Error: " . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'تعذر حذف المشرف، يرجى المحاولة لاحقاً.'
            ], 500);
        }
    }

    public function approveEmailChange(Request $request, $token): JsonResponse
    {
        try {
            if (! $request->hasValidSignature()) {
                return response()->json(['status' => false, 'message' => 'عذراً، هذا الرابط غير صالح أو انتهت صلاحيته!'], 403);
            }

            $cachedData = Cache::get("admin_email_change_{$token}");
            if (!$cachedData) {
                return response()->json(['status' => false, 'message' => 'الرابط منتهي الصلاحية أو تم استخدامه مسبقاً.'], 400);
            }

            $user = \App\Models\User::findOrFail($cachedData['admin_user_id']);
            $user->update([
                'email' => $cachedData['new_email'],
                'email_verified_at' => now()
            ]);

            Cache::forget("admin_email_change_{$token}");

            return response()->json(['status' => true, 'message' => 'تم تفعيل وتحديث بريدك الإلكتروني بنجاح! 🎉'], 200);
        } catch (Exception $e) {
            Log::error("Approve Email Change Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'حدث خطأ أثناء معالجة الطلب.'], 500);
        }
    }

    public function rejectEmailChange(Request $request, $token): JsonResponse
    {
        try {
            if (! $request->hasValidSignature()) {
                return response()->json(['status' => false, 'message' => 'رابط غير صالح أو تم التلاعب به.'], 403);
            }

            Cache::forget("admin_email_change_{$token}");

            return response()->json(['status' => true, 'message' => 'تم إلغاء طلب تغيير البريد الإلكتروني بنجاح.'], 200);
        } catch (Exception $e) {
            Log::error("Reject Email Change Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'حدث خطأ أثناء إلغاء الطلب.'], 500);
        }
    }
}