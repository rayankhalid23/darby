<?php

namespace App\Services\Shared;

use App\Models\Shared\OtpCode;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class OtpService
{
    /**
     * توليد كود تحقق جديد مع حماية ضد الطلبات المتكررة
     */
    public function generate(string $email, string $purpose): array
    {
        // 1. منع الطلب المتكرر قبل مرور 60 ثانية
        $lastOtp = OtpCode::where('email', $email)
            ->where('purpose', $purpose)
            ->latest()
            ->first();

            if ($lastOtp && Carbon::parse($lastOtp->created_at)->gt(Carbon::now()->subSeconds(60))) {
                $secondsLeft = 60 - Carbon::now()->diffInSeconds(Carbon::parse($lastOtp->created_at));
            return [
                'success' => false,
                'message' => "يرجى الانتظار {$secondsLeft} ثانية قبل طلب رمز جديد."
            ];
        }

        $code = strval(random_int(100000, 999999));

        // 2. إلغاء صلاحية أي أكواد نشطة سابقة
        OtpCode::where('email', $email)
            ->where('purpose', $purpose)
            ->where('is_used', 0)
            ->update(['is_used' => 1]);

        // 3. إنشاء سجل جديد بصلاحية 5 دقائق
        OtpCode::create([
            'email'      => $email,
            'purpose'    => $purpose,
            'code_hash'  => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes(5),
            'is_used'    => 0,
            'attempts'   => 0
        ]);

        return [
            'success' => true,
            'code'    => $code
        ];
    }

    /**
     * التحقق من كود الـ OTP الممرر بدقة وعناية
     */
    public function verify(string $email, string $code, string $purpose): array
    {
        $otp = OtpCode::where('email', $email)
            ->where('purpose', $purpose)
            ->where('is_used', 0)
            ->latest()
            ->first();

        if (!$otp) {
            return ['success' => false, 'message' => 'انتهت صلاحية الرمز يرجى إعادة طلب رمز التحقق.'];
        }
        
        // 1. التحقق من تجاوز المحاولات الفاشلة
        if ($otp->attempts >= 3) {
            $otp->update(['is_used' => 1]);
            return ['success' => false, 'message' => 'تم تجاوز عدد المحاولات المسموح بها لحماية حسابك.'];
        }

        // 2. التحقق من صلاحية الوقت
        if (Carbon::parse($otp->expires_at)->isPast()) {
            $otp->update(['is_used' => 1]);
            return ['success' => false, 'message' => 'الكود منتهي الصلاحية، يرجى طلب كود جديد.'];
        }

        // 3. مطابقة الكود
        if (Hash::check($code, $otp->code_hash)) {
            $otp->update(['is_used' => 1]);
            return ['success' => true, 'message' => 'تم التحقق بنجاح.'];
        }

        // 4. زيادة المحاولات وحظر الكود فوراً إذا وصل للحد الأقصى
        $otp->increment('attempts');
        $remainingAttempts = 3 - $otp->attempts;

        if ($remainingAttempts <= 0) {
            $otp->update(['is_used' => 1]);
            return ['success' => false, 'message' => 'تم تجاوز عدد المحاولات المسموح بها لحماية حسابك.'];
        }

        return [
            'success' => false, 
            'message' => 'رمز التحقق غير صحيح. متبقي لديك ' . $remainingAttempts . ' محاولات.'
        ];
    }
}