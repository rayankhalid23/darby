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

    public function getAllAdmins($perPage = 10)
    {
        return Admin::with(['user', 'creator.user'])->latest()->paginate($perPage);
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
                if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
                    // 1. توليد توكن فريد ورابط موقع آمن للموافقة والرفض
                    $token = Str::random(64);
                    
                    $approveUrl = URL::signedRoute('admin.email.approve', ['token' => $token]);
                    $rejectUrl  = URL::signedRoute('admin.email.reject', ['token' => $token]);
                
                    // 2. تسجيل اللوج لإثبات العملية (بناءً على ملف الـ Log الخاص بك)
                    Log::info("=== Admin Email Change Request ===");
                
                    // 3. إرسال البريد الإلكتروني مع الروابط الجديدة المولدة بدون أخطاء
                    $this->emailService->sendEmailChangeNotification(
                        $data['email'],
                        $approveUrl,
                        $rejectUrl
                    );
                
                    // ملاحظة: لا نضيف الإيميل لـ $updateData هنا لكي لا يتغير حتى يضغط على رابط التأكيد في الإيميل
                }
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
     * حذف حساب مشرف وإلغاء مستخدمه المرتبط
     */
    public function deleteAdmin(Admin $admin): void
    {
        DB::transaction(function () use ($admin) {
            $user = $admin->user;
            $admin->delete();
            if ($user) {
                $user->delete();
            }
        });
    }
}