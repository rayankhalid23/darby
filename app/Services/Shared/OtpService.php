<?php

namespace App\Services\Shared;

use App\Models\Shared\OtpCode;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class OtpService
{
    /**
     * توليد كود تحقق جديد وإلغاء الأكواد السابقة لضمان عدم التكرار
     */
    public function generate(string $email, string $purpose): string
    {
        $code = strval(random_int(100000, 999999));

        // 1. إلغاء صلاحية أي أكواد نشطة سابقة لهذا الإيميل لنفس الغرض (حتى لا يحدث تضارب)
        OtpCode::where('email', $email)
            ->where('purpose', $purpose)
            ->where('is_used', 0)
            ->update(['is_used' => 1]);

        // 2. إنشاء سجل جديد تماماً للطلب الحالي
        OtpCode::create([
            'email'      => $email,
            'purpose'    => $purpose,
            'code_hash'  => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes(100),
            'is_used'    => 0,
            'attempts'   => 0
        ]);

        return $code;
    }

    /**
     * التحقق من كود الـ OTP الممرر بدقة وعناية
     */
    public function verify(string $email, string $code, string $purpose): array
    {
        // جلب أحدث كود نشط متاح
        $otp = OtpCode::where('email', $email)
            ->where('purpose', $purpose)
            ->where('is_used', 0)
            ->latest()
            ->first();

        if (!$otp) {
            return ['success' => false, 'message' => 'لا يوجد كود نشط أو تم استخدامه مسبقاً.'];
        }
        
        // التحقق من عدد المحاولات الفاشلة
        if ($otp->attempts >= 30) {
            $otp->update(['is_used' => 1]);
            return ['success' => false, 'message' => 'تم تجاوز عدد المحاولات المسموح بها لحماية حسابك.'];
        }

        // التحقق من صلاحية الوقت
        if (Carbon::parse($otp->expires_at)->isPast()) {
            $otp->update(['is_used' => 1]);
            return ['success' => false, 'message' => 'الكود منتهي الصلاحية، يرجى طلب كود جديد.'];
        }

        // مطابقة التشفير المباشر للكود المدخل
        if (Hash::check($code, $otp->code_hash)) {
            $otp->update(['is_used' => 1]);
            return ['success' => true, 'message' => 'تم التحقق بنجاح.'];
        }

        // زيادة عداد المحاولات الفاشلة بحالة عدم التطابق
        $otp->increment('attempts');
        
        $remainingAttempts = 30 - $otp->attempts;
        
        return [
            'success' => false, 
            'message' => 'رمز التحقق غير صحيح. متبقي لديك ' . ($remainingAttempts > 0 ? $remainingAttempts : 0) . ' محاولات.'
        ];
    }
}