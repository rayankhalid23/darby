<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Models\Admin\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Services\Shared\EmailService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; 
use Exception;

class AdminService
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * جلب قائمة المشرفين مع دعم البحث والترقيم
     * ملاحظة: جدول admins بلا أعمدة تواريخ لذلك نرتب تنازلياً حسب المعرف
     */
    public function getAllAdmins(int $perPage = 10, ?string $search = null)
    {
        return Admin::with(['user', 'creator'])
            // استبعاد المشرفين الذين حُذفت حساباتهم (soft deleted)
            ->whereHas('user', function ($query) use ($search) {
                if (!empty($search)) {
                    $query->where(function ($q) use ($search) {
                        $q->where('full_name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhere('phone_number', 'like', "%{$search}%");
                    });
                }
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function createAdmin(array $data, ?UploadedFile $avatar = null): Admin
    {
        return DB::transaction(function () use ($data, $avatar) {
            $avatarUrl = null;
            if ($avatar) {
                $path = $avatar->store('uploads/admins/avatars', 'public');
                $avatarUrl = 'storage/' . $path;
            }

            $generatedPassword = $data['password'] ?? (Str::random(8) . rand(10, 99));

            $user = User::create([
                'full_name'     => $data['full_name'],
                'email'         => $data['email'],
                'phone_number'  => $data['phone_number'],
                'password_hash' => Hash::make($generatedPassword),
                'role_id'       => $data['role_id'],
                'is_active'     => $data['is_active'],
                'avatar_url'    => $avatarUrl,
            ]);

            $admin = Admin::create([
                'user_id'    => $user->id,
                'created_by' => $data['created_by'],
            ]);

            $this->emailService->sendAdminCredentials(
                $user->email,
                $user->full_name,
                $user->phone_number,
                $generatedPassword
            );

            return $admin->load(['user', 'creator']);
        });
    }

    /**
     * 🚀 النسخة المحدثة والمحمية بالكامل لدالة تعديل بيانات المشرف (تعديل جزئي 100%)
     */
    public function updateAdmin(Admin $admin, array $data, ?UploadedFile $avatar = null): Admin
    {
        return DB::transaction(function () use ($admin, $data, $avatar) {
            try {
                $user = $admin->user;
                $updateData = [];

                // الاعتماد على array_key_exists لضمان التقاط القيم مثل 0 أو null بأمان
                if (array_key_exists('full_name', $data))    $updateData['full_name'] = $data['full_name'];
                // ملاحظة: تغيير البريد الإلكتروني تتم معالجته بالكامل في AdminController
                // عبر إرسال رابط تأكيد موقّع، لذلك لا يُحدَّث الإيميل هنا إطلاقاً.
                if (array_key_exists('phone_number', $data)) $updateData['phone_number'] = $data['phone_number'];
                if (array_key_exists('is_active', $data))    $updateData['is_active'] = $data['is_active'];

                // تحديث كلمة المرور فقط في حال تمريرها غير فارغة
                if (!empty($data['password'])) {
                    $updateData['password_hash'] = Hash::make($data['password']);
                }

                // معالجة وحذف الصورة القديمة بشكل آمن عند رفع صورة جديدة
                if ($avatar) {
                    if ($user->avatar_url && Storage::disk('public')->exists(str_replace('storage/', '', $user->avatar_url))) {
                        Storage::disk('public')->delete(str_replace('storage/', '', $user->avatar_url));
                    }
                    $path = $avatar->store('uploads/admins/avatars', 'public');
                    $updateData['avatar_url'] = 'storage/' . $path;
                }

                // تنفيذ التحديث الجزئي الفعلي للمستخدم المرتبط
                if (!empty($updateData)) {
                    $user->update($updateData);
                }

                return $admin->refresh()->load(['user', 'creator']);

            } catch (Exception $e) {
                Log::error("Error updating admin ID {$admin->id}: " . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * 🗑️ حذف المشرف نهائياً مع تنظيف كل ما يتعلق به
     *
     * الخطوات: نقل ملكية المشرفين الذين أنشأهم (بسبب قيد RESTRICT على created_by)،
     * ثم إلغاء توكنات الدخول، ثم حذف صورته من التخزين، وأخيراً حذف الحساب والسجل.
     */
    public function deleteAdmin(Admin $admin, int $performedByUserId): void
    {
        DB::transaction(function () use ($admin, $performedByUserId) {
            $user = $admin->user;

            // 1. نقل ملكية أي مشرفين أنشأهم هذا المشرف إلى منفّذ عملية الحذف
            //    لأن العمود created_by محمي بقيد ON DELETE RESTRICT
            Admin::where('created_by', $user->id)
                ->where('id', '!=', $admin->id)
                ->update(['created_by' => $performedByUserId]);

            // 2. إلغاء كل جلسات الدخول النشطة لهذا المشرف فوراً
            $user->tokens()->delete();

            // 3. حذف الصورة الشخصية من التخزين إن وُجدت
            if ($user->avatar_url) {
                $path = str_replace('storage/', '', $user->avatar_url);
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            // 4. حذف سجل المشرف ثم حذف حساب المستخدم نهائياً
            $admin->delete();
            $user->forceDelete();

            Log::info("Admin ID {$admin->id} (user {$user->id}) deleted by user {$performedByUserId}");
        });
    }
}