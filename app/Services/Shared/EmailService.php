<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class EmailService
{
    private string $primaryColor = "#007A99"; 
    private string $accentColor  = "#F59E0B";

    /**
     * إرسال رمز التحقق OTP مع تخصيص الخطاب والقالب بناءً على الغرض
     */
    public function sendOtp(string $to, string $fullName, string $otp, int $roleId, ?string $gender = null, string $purpose = 'GENERAL'): bool
    {
        try {
            $greeting = $this->determineGreeting($roleId, $gender);

            // تحديد عنوان البريد والقالب بناءً على الغرض
            if ($purpose === 'RESET_PASSWORD') {
                $subject = 'طلب إعادة تعيين كلمة المرور | Darby';
                $htmlContent = $this->getPasswordResetTemplate($fullName, $otp, $greeting);
            } else {
                $subject = 'رمز التحقق الخاص بك | Darby';
                $htmlContent = $this->getOtpTemplate($fullName, $otp, $greeting);
            }

            Mail::html($htmlContent, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            Log::info("Email OTP ({$purpose}) sent successfully to: {$to}");
            return true;
        } catch (Exception $e) {
            Log::error("Email OTP Error: " . $e->getMessage());
            throw new Exception("تعذر إرسال رمز التحقق عبر البريد الإلكتروني.");
        }
    }

    /**
     * تحديد صيغة الخطاب بناءً على الدور والجنس
     */
    private function determineGreeting(int $roleId, ?string $gender): string
    {
        if ($roleId == 3) return "عزيزي ولي الأمر";
        if ($roleId == 4) return ($gender === 'female') ? "عزيزتي السائقة" : "عزيزي السائق";
        return "مرحباً بك";
    }

    /**
     * 1️⃣ قالب بريد الـ OTP العام
     */
    private function getOtpTemplate(string $fullName, string $otp, string $greeting): string
    {
        $lightBackground = "#e6f2f5"; 

        return "
        <div style='background-color: #f4f7f6; padding: 50px 15px; font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; direction: rtl;'>
            <div style='max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;'>
                <div style='height: 5px; background: linear-gradient(90deg, {$this->primaryColor} 0%, {$this->accentColor} 100%);'></div>
                <div style='padding: 40px 30px 20px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 34px; font-weight: 900; color: {$this->primaryColor}; text-transform: uppercase;'>Darby<span style='color: {$this->accentColor};'>.</span></h1>
                </div>
                <div style='padding: 20px 40px 40px; text-align: center;'>
                    <h2 style='margin: 0 0 15px; font-size: 20px; color: #2d3748; font-weight: 700;'>{$greeting} {$fullName}</h2>
                    <p style='margin: 0 0 30px; font-size: 15px; color: #4a5568; line-height: 1.6;'>لقد تلقينا طلباً للتحقق من هويتك. يرجى استخدام الرمز السري أدناه لإتمام العملية.</p>
                    <div style='background-color: {$lightBackground}; border-radius: 12px; padding: 25px; margin: 0 auto 30px; border: 1px dashed {$this->primaryColor};'>
                        <span style='font-family: \"Courier New\", Courier, monospace; font-size: 38px; font-weight: 900; color: {$this->primaryColor}; letter-spacing: 14px;'>{$otp}</span>
                    </div>
                </div>
                <div style='background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #eaedf1;'>
                    <p style='margin: 0; font-size: 12px; color: #a0aec0; font-weight: 500;'>© " . date('Y') . " Darby. جميع الحقوق محفوظة.</p>
                </div>
            </div>
        </div>";
    }

    /**
     * 2️⃣ قالب بريد مخصص وحصري لإعادة تعيين كلمة المرور
     */
    private function getPasswordResetTemplate(string $fullName, string $otp, string $greeting): string
    {
        return "
        <div style='background-color: #f4f7f6; padding: 50px 15px; font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; direction: rtl;'>
            <div style='max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;'>
                <div style='height: 5px; background: linear-gradient(90deg, {$this->primaryColor} 0%, {$this->accentColor} 100%);'></div>
                <div style='padding: 40px 30px 20px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 34px; font-weight: 900; color: {$this->primaryColor}; text-transform: uppercase;'>Darby<span style='color: {$this->accentColor};'>.</span></h1>
                </div>
                <div style='padding: 10px 35px 35px; text-align: center;'>
                    <p style='font-size: 18px; color: #1e293b; font-weight: 700; margin-bottom: 8px;'>{$greeting} <span style='color: {$this->primaryColor};'>{$fullName}</span></p>
                    <p style='margin: 0 0 25px; font-size: 14px; color: #475569; line-height: 1.6;'>لقد تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بحسابك في منصة <b>Darby</b>. يرجى استخدام رمز الأمان المؤقت أدناه لإتمام العملية بنجاح:</p>
                    
                    <div style='background-color: #f8fafc; border-radius: 12px; padding: 20px; margin: 0 auto 25px; border: 2px solid #e2e8f0;'>
                        <span style='font-family: \"Courier New\", Courier, monospace; font-size: 40px; font-weight: 900; color: #1e293b; letter-spacing: 12px;'>{$otp}</span>
                    </div>

                    <div style='background-color: #fff9db; border-radius: 8px; padding: 12px; text-align: right; border-right: 4px solid #f59e0b;'>
                        <p style='margin: 0; font-size: 12px; color: #b45309; line-height: 1.5;'>⚠️ <b>ملاحظة أمنية هامة:</b> ينتهي صلاحية هذا الرمز بعد 10 دقائق. إذا لم تكن أنت من طلب هذا الرمز، فيرجى تجاهل هذا البريد تماماً لحماية حسابك.</p>
                    </div>
                </div>
                <div style='background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;'>
                    <p style='margin: 0; font-size: 11px; color: #94a3b8;'>هذا البريد الإلكتروني تم إرساله تلقائياً، يرجى عدم الرد عليه.</p>
                    <p style='margin: 5px 0 0; font-size: 12px; color: #64748b;'>© " . date('Y') . " Darby. جميع الحقوق محفوظة.</p>
                </div>
            </div>
        </div>";
    }
    /**
     * 3️⃣ إرسال بيانات الدخول للمشرف الجديد
     */
    public function sendAdminCredentials(string $to, string $fullName, string $email, string $password): bool
    {
        try {
            Mail::html($this->getAdminCredentialsTemplate($fullName, $email, $password), function ($message) use ($to) {
                $message->to($to)->subject('مرحباً بك في فريق العمل! بيانات حسابك المشرف | Darby');
            });

            Log::info("Admin credentials email sent successfully to: {$to}");
            return true;
        } catch (Exception $e) {
            Log::error("Admin Email Credentials Error: " . $e->getMessage());
            throw new Exception("تعذر إرسال بيانات اعتماد المشرف.");
        }
    }

    /**
     * قالب بريد بيانات المشرف
     */
    private function getAdminCredentialsTemplate(string $fullName, string $email, string $password): string
    {
        return "
        <div style='background-color: #f4f7f6; padding: 50px 15px; font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; direction: rtl;'>
            <div style='max-width: 520px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04); border: 1px solid #eaedf1;'>
                <div style='height: 5px; background: linear-gradient(90deg, {$this->primaryColor} 0%, {$this->accentColor} 100%);'></div>
                <div style='padding: 40px 30px 20px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 34px; font-weight: 900; color: {$this->primaryColor}; text-transform: uppercase;'>Darby<span style='color: {$this->accentColor};'>.</span></h1>
                </div>
                <div style='padding: 10px 40px 40px;'>
                    <h2 style='margin: 0 0 15px; font-size: 20px; color: #2d3748; font-weight: 700; text-align: center;'>مرحباً بك المشرف، <span style='color: {$this->primaryColor};'>{$fullName}</span> 👋</h2>
                    <p style='margin: 0 0 25px; font-size: 15px; color: #4a5568; line-height: 1.6; text-align: center;'>لقد تم تسجيلك كمشرف في منصة Darby. إليك بيانات الاعتماد الخاصة بك:</p>
                    <div style='background-color: #f8fafc; border-radius: 12px; padding: 20px; border: 1px solid #eaedf1; margin-bottom: 25px;'>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr><td style='padding: 8px 0; color: #718096; font-size: 14px; width: 40%;'> الايميل:</td><td style='padding: 8px 0; color: #2d3748; font-size: 15px; font-weight: 700; direction: ltr; text-align: right;'>{$email}</td></tr>
                            <tr><td style='padding: 8px 0; color: #718096; font-size: 14px;'>الرقم السري:</td><td style='padding: 8px 0; color: {$this->primaryColor}; font-size: 16px; font-weight: 700; font-family: monospace; direction: ltr; text-align: right;'>{$password}</td></tr>
                        </table>
                    </div>
                </div>
                <div style='background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #eaedf1;'>
                    <p style='margin: 0; font-size: 12px; color: #a0aec0;'>© " . date('Y') . " Darby. جميع الحقوق محفوظة.</p>
                </div>
            </div>
        </div>";
    }


    /**
     * 4️⃣ إرسال روابط الموافقة أو الرفض لتعديل إيميل الأدمن
     */
    public function sendEmailChangeLink(string $to, string $fullName, string $approveUrl, string $rejectUrl): bool
    {
        try {
            $subject = 'تأكيد تغيير البريد الإلكتروني | Darby';
            $htmlContent = $this->getEmailChangeLinkTemplate($fullName, $approveUrl, $rejectUrl);

            Mail::html($htmlContent, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            Log::info("Email Change Links sent successfully to: {$to}");
            return true;
        } catch (Exception $e) {
            Log::error("Email Change Link Error: " . $e->getMessage());
            throw new Exception("تعذر إرسال بريد روابط التفعيل للمشرف.");
        }
    }

    /**
     * قالب بريد تعديل إيميل الأدمن
     */
    private function getEmailChangeLinkTemplate(string $fullName, string $approveUrl, string $rejectUrl): string
    {
        return "
        <div style='background-color: #f8fafc; padding: 60px 20px; font-family: \"Segoe UI\", system-ui, -apple-system, sans-serif; direction: rtl; text-align: right;'>
            <div style='max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03); border: 1px solid #e2e8f0;'>
                <div style='height: 5px; background: linear-gradient(90deg, {$this->primaryColor} 0%, {$this->accentColor} 100%);'></div>
                <div style='padding: 40px 30px 20px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 34px; font-weight: 900; color: {$this->primaryColor}; text-transform: uppercase;'>Darby<span style='color: {$this->accentColor};'>.</span></h1>
                </div>
                <div style='padding: 20px 40px 40px;'>
                    <div style='border-bottom: 1px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 25px;'>
                        <h2 style='margin: 0 0 10px; font-size: 18px; color: #0f172a; font-weight: 700;'>مرحباً بالمشرف، <span style='color: {$this->primaryColor};'>{$fullName}</span> </h2>
                        <p style='margin: 0; font-size: 14px; color: #475569; line-height: 1.6;'>لقد تلقينا طلباً لتحديث البريد الإلكتروني الخاص بحسابك الإداري في تطبيق دربي. لضمان أمان حسابك، يرجى تأكيد هذا الإجراء عبر أحد الخيارات أدناه:</p>
                    </div>
                    <div style='margin-bottom: 25px;'>
                        <a href='{$approveUrl}' target='_blank' style='display: block; background-color: {$this->primaryColor}; color: #ffffff; padding: 14px 24px; margin-bottom: 14px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 15px; text-align: center; box-shadow: 0 4px 12px rgba(0, 122, 153, 0.2); transition: all 0.2s ease;'>تأكيد وتحديث البريد الإلكتروني فوراً</a>
                        <a href='{$rejectUrl}' target='_blank' style='display: block; background-color: #ffffff; color: {$this->accentColor}; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; text-align: center; border: 2px solid {$this->accentColor}; transition: all 0.2s ease;'>إلغاء الطلب والاحتفاظ بالبريد الحالي</a>
                    </div>
                    <div style='background-color: #fffbeb; border-radius: 10px; padding: 14px; border-right: 4px solid {$this->accentColor};'>
                        <p style='margin: 0; font-size: 12px; color: #b45309; line-height: 1.6; font-weight: 500;'>⚠️ <b>ملاحظة أمنية:</b> الروابط أعلاه مشفرة وموقعة رقمياً وتلقائياً، وتنتهي صلاحيتها خلال 30 دقيقة من تاريخ صدور هذا البريد لحماية حسابك.</p>
                    </div>
                </div>
                <div style='background-color: #f8fafc; padding: 24px; text-align: center; border-top: 1px solid #f1f5f9;'>
                    <p style='margin: 0 0 4px; font-size: 11px; color: #94a3b8; font-weight: 400;'>هذا البريد تم إنشاؤه تلقائياً من نظام التنبيهات الأمنية، يرجى عدم الرد عليه.</p>
                    <p style='margin: 0; font-size: 12px; color: #64748b; font-weight: 500;'>© " . date('Y') . " دربي Darby. جميع الحقوق محفوظة.</p>
                </div>
            </div>
        </div>";
    }

    /**
     * 5️⃣ إرسال روابط موافقة أو رفض تغيير البريد الإلكتروني لولي الأمر
     */
    public function sendParentEmailChangeLink(string $to, string $fullName, string $approveUrl, string $rejectUrl): bool
    {
        try {
            $subject = 'تأكيد تغيير البريد الإلكتروني الخاص بحسابك | Darby';
            $htmlContent = $this->getParentEmailChangeLinkTemplate($fullName, $approveUrl, $rejectUrl);

            Mail::html($htmlContent, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            Log::info("Email Change Links sent successfully to parent: {$to}");
            return true;
        } catch (Exception $e) {
            Log::error("Parent Email Change Link Error: " . $e->getMessage());
            throw new Exception("تعذر إرسال بريد تفعيل روابط البريد لولي الأمر.");
        }
    }

    /**
     * قالب بريد تحديث بريد ولي الأمر
     */
    private function getParentEmailChangeLinkTemplate(string $fullName, string $approveUrl, string $rejectUrl): string
    {
        return "
        <div style='background-color: #f8fafc; padding: 60px 20px; font-family: \"Segoe UI\", system-ui, -apple-system, sans-serif; direction: rtl; text-align: right;'>
            <div style='max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03); border: 1px solid #e2e8f0;'>
                <div style='height: 5px; background: linear-gradient(90deg, {$this->primaryColor} 0%, {$this->accentColor} 100%);'></div>
                <div style='padding: 40px 30px 20px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 34px; font-weight: 900; color: {$this->primaryColor}; text-transform: uppercase;'>Darby<span style='color: {$this->accentColor};'>.</span></h1>
                    <p style='margin: 0; font-size: 15px; color: #64748b; font-weight: 500;'>منصة النقل المدرسي الآمن للأبناء</p>
                </div>
                <div style='padding: 20px 40px 40px;'>
                    <div style='border-bottom: 1px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 25px;'>
                        <h2 style='margin: 0 0 10px; font-size: 18px; color: #0f172a; font-weight: 700;'>عزيزي ولي الأمر، <span style='color: {$this->primaryColor};'>{$fullName}</span> 👋</h2>
                        <p style='margin: 0; font-size: 14px; color: #475569; line-height: 1.6;'>لقد تلقينا طلباً لتغيير البريد الإلكتروني الخاص بحسابك في تطبيق دربي لتتبع الأبناء. لحماية بياناتك وضمان استمرار استقبال التنبيهات، يرجى تأكيد العملية عبر الضغط على الخيار المناسب أدناه:</p>
                    </div>
                    <div style='margin-bottom: 25px;'>
                        <a href='{$approveUrl}' target='_blank' style='display: block; background-color: {$this->primaryColor}; color: #ffffff; padding: 14px 24px; margin-bottom: 14px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 15px; text-align: center; box-shadow: 0 4px 12px rgba(0, 122, 153, 0.2); transition: all 0.2s ease;'>تأكيد وتحديث البريد الإلكتروني فوراً</a>
                        <a href='{$rejectUrl}' target='_blank' style='display: block; background-color: #ffffff; color: {$this->accentColor}; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; text-align: center; border: 2px solid {$this->accentColor}; transition: all 0.2s ease;'>إلغاء الطلب والاحتفاظ بالبريد الحالي</a>
                    </div>
                    <div style='background-color: #fffbeb; border-radius: 10px; padding: 14px; border-right: 4px solid {$this->accentColor};'>
                        <p style='margin: 0; font-size: 12px; color: #b45309; line-height: 1.6; font-weight: 500;'>⚠️ <b>ملاحظة أمنية:</b> الروابط أعلاه آمنة تماماً وموقعة، وستنتهي صلاحيتها تلقائياً بعد 30 دقيقة. إذا لم تقم أنت بهذا الطلب، يرجى إلغاء العملية فوراً لحماية حسابك.</p>
                    </div>
                </div>
                <div style='background-color: #f8fafc; padding: 24px; text-align: center; border-top: 1px solid #f1f5f9;'>
                    <p style='margin: 0 0 4px; font-size: 11px; color: #94a3b8; font-weight: 400;'>هذا البريد تم إرساله تلقائياً من نظام حماية المشتركين، يرجى عدم الرد عليه.</p>
                    <p style='margin: 0; font-size: 12px; color: #64748b; font-weight: 500;'>© " . date('Y') . " دربي Darby. جميع الحقوق محفوظة.</p>
                </div>
            </div>
        </div>";
    }

    /**
     * 6️⃣ إرسال روابط موافقة أو رفض تغيير البريد الإلكتروني للسائق
     */
    public function sendDriverEmailChangeLink(string $to, string $fullName, string $approveUrl, string $rejectUrl, ?string $gender = null): bool
    {
        try {
            $subject = 'تأكيد تغيير البريد الإلكتروني الخاص بحسابك كأحد شركاء النجاح | Darby';
            $htmlContent = $this->getDriverEmailChangeLinkTemplate($fullName, $approveUrl, $rejectUrl, $gender);

            Mail::html($htmlContent, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            Log::info("Email Change Links sent successfully to driver: {$to}");
            return true;
        } catch (Exception $e) {
            Log::error("Driver Email Change Link Error: " . $e->getMessage());
            throw new Exception("تعذر إرسال بريد تفعيل روابط البريد للسائق.");
        }
    }

    /**
     * قالب بريد تحديث بريد السائق (تم تعديل الهيدر وإزالة كلمة كابتن)
     */
    private function getDriverEmailChangeLinkTemplate(string $fullName, string $approveUrl, string $rejectUrl, ?string $gender = null): string
    {
        $greeting = ($gender === 'female') ? "عزيزتي السائقة" : "عزيزي السائق";

        return "
        <div style='background-color: #f8fafc; padding: 60px 20px; font-family: \"Segoe UI\", system-ui, -apple-system, sans-serif; direction: rtl; text-align: right;'>
            <div style='max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03); border: 1px solid #e2e8f0;'>
                <div style='height: 5px; background: linear-gradient(90deg, {$this->primaryColor} 0%, {$this->accentColor} 100%);'></div>
                <div style='padding: 40px 30px 20px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 34px; font-weight: 900; color: {$this->primaryColor}; text-transform: uppercase;'>Darby<span style='color: {$this->accentColor};'>.</span></h1>
                    <p style='margin: 0; font-size: 15px; color: #64748b; font-weight: 500;'>مستقبل النقل المدرسي الذكي والآمن</p>
                </div>
                <div style='padding: 20px 40px 40px;'>
                    <div style='border-bottom: 1px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 25px;'>
                        <h2 style='margin: 0 0 10px; font-size: 18px; color: #0f172a; font-weight: 700;'>{$greeting}، <span style='color: {$this->primaryColor};'>{$fullName}</span> 👋</h2>
                        <p style='margin: 0; font-size: 14px; color: #475569; line-height: 1.6;'>لقد تلقينا طلباً لتغيير البريد الإلكتروني الخاص بحسابك المهني في تطبيق دربي للسائقين. لحماية حسابك وضمان استمرار استقبال إشعارات الطلبات والرحلات، يرجى تأكيد هذا الإجراء بالضغط أدناه:</p>
                    </div>
                    <div style='margin-bottom: 25px;'>
                        <a href='{$approveUrl}' target='_blank' style='display: block; background-color: {$this->primaryColor}; color: #ffffff; padding: 14px 24px; margin-bottom: 14px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 15px; text-align: center; box-shadow: 0 4px 12px rgba(0, 122, 153, 0.2); transition: all 0.2s ease;'>تأكيد وتحديث البريد الإلكتروني فوراً</a>
                        <a href='{$rejectUrl}' target='_blank' style='display: block; background-color: #ffffff; color: {$this->accentColor}; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; text-align: center; border: 2px solid {$this->accentColor}; transition: all 0.2s ease;'>إلغاء الطلب والاحتفاظ بالبريد الحالي</a>
                    </div>
                    <div style='background-color: #fffbeb; border-radius: 10px; padding: 14px; border-right: 4px solid {$this->accentColor};'>
                        <p style='margin: 0; font-size: 12px; color: #b45309; line-height: 1.6; font-weight: 500;'>⚠️ <b>ملاحظة أمنية:</b> الروابط أعلاه آمنة وموقعة برمجياً وتنتهي صلاحيتها تلقائياً بعد 30 دقيقة. إذا لم تقم بهذا الإجراء، يرجى إلغاء الطلب فوراً لحماية حسابك.</p>
                    </div>
                </div>
                <div style='background-color: #f8fafc; padding: 24px; text-align: center; border-top: 1px solid #f1f5f9;'>
                    <p style='margin: 0 0 4px; font-size: 11px; color: #94a3b8; font-weight: 400;'>هذا البريد تم إرساله تلقائياً من نظام حماية المشتركين، يرجى عدم الرد عليه.</p>
                    <p style='margin: 0; font-size: 12px; color: #64748b; font-weight: 500;'>© " . date('Y') . " دربي Darby. جميع الحقوق محفوظة.</p>
                </div>
            </div>
        </div>";
    }

    /**
     * 7️⃣ إرسال نتيجة مراجعة حساب السائق (قبول مفعل أو رفض مسبب)
     */
    public function sendDriverReviewResult(string $to, string $fullName, string $status, ?string $rejectionReason = null, ?string $gender = null): bool
    {
        try {
            $greeting = ($gender === 'female') ? "عزيزتي السائقة" : "عزيزي السائق";
            
            if ($status === 'Approved') {
                $subject = '🎉 تهانينا! تم قبول حسابك واعتماده رسمياً | Darby';
                $htmlContent = $this->getDriverApprovedTemplate($fullName, $greeting);
            } else {
                $subject = 'تحديث بشأن طلب انضمامك إلى المنصة | Darby';
                $htmlContent = $this->getDriverRejectedTemplate($fullName, $rejectionReason, $greeting);
            }

            Mail::html($htmlContent, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            Log::info("Driver Review Email ({$status}) sent successfully to: {$to}");
            return true;
        } catch (Exception $e) {
            Log::error("Driver Review Email Error: " . $e->getMessage());
            throw new Exception("تعذر إرسال بريد مراجعة الحساب للسائق.");
        }
    }

    /**
     * قالب القبول (تم تعديله ليحتوي على الهيدر الموحد بتدرج الألوان المعتمد)
     */
    private function getDriverApprovedTemplate(string $fullName, string $greeting): string
    {
        return "
        <div style='background-color: #f4f7f6; padding: 50px 15px; font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; direction: rtl;'>
            <div style='max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; position: relative;'>
                <div style='height: 5px; background: linear-gradient(90deg, {$this->primaryColor} 0%, {$this->accentColor} 100%);'></div>
                <div style='padding: 40px 30px 20px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 34px; font-weight: 900; color: {$this->primaryColor}; text-transform: uppercase;'>Darby<span style='color: {$this->accentColor};'>.</span></h1>
                    <div style='font-size: 55px; margin-top: 15px;'>🎉</div>
                </div>
                <div style='padding: 20px 35px 35px; text-align: center;'>
                    <h2 style='margin: 0 0 15px; font-size: 22px; color: #1e293b; font-weight: 700;'>{$greeting} <span style='color: {$this->primaryColor};'>{$fullName}</span>،</h2>
                    <p style='margin: 0 0 25px; font-size: 15px; color: #475569; line-height: 1.7;'>يسعدنا جداً إبلاغك بأن إدارة منصة <b>Darby</b> قد قامت بمراجعة وثائقك وبيانات مركبتك، وتم <b>الموافقة على حسابك وتفعيله بنجاح!</b></p>
                    
                    <div style='background-color: #f0fdf4; border-radius: 12px; padding: 18px; margin-bottom: 30px; border: 1px solid #bbf7d0;'>
                        <span style='font-size: 14px; color: #166534; font-weight: 600; display: block; margin-bottom: 5px;'>حالة الحساب الحالية:</span>
                        <span style='font-size: 20px; color: #15803d; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;'>Active / نشط ومستعد</span>
                    </div>

                    <p style='margin: 0 0 25px; font-size: 14px; color: #475569; line-height: 1.6;'>يمكنك الآن فتح التطبيق مباشرة، تسجيل الدخول وبدء استقبال رحلات النقل المدرسي وتحقيق الأرباح كشريك نجاح متميز معنا.</p>
                    
                    <a href='#' style='display: inline-block; background-color: {$this->accentColor}; color: #ffffff; padding: 14px 35px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 15px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);'>ابدأ رحلتك الأولى الآن</a>
                </div>
                <div style='background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #eaedf1;'>
                    <p style='margin: 0; font-size: 12px; color: #94a3b8; font-weight: 500;'>© " . date('Y') . " Darby. جميع الحقوق محفوظة.</p>
                </div>
            </div>
        </div>";
    }

    /**
     * قالب الرفض (تم تعديله ليحتوي على الهيدر الموحد بتدرج الألوان المعتمد)
     */
    private function getDriverRejectedTemplate(string $fullName, ?string $rejectionReason, string $greeting): string
    {
        $rejectionReason = $rejectionReason ?? 'تحديث وتعديل المستندات الشخصية المرفقة بملفك الشخصي.';
    
        return "
        <div style='background-color: #f4f7f6; padding: 50px 15px; font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; direction: rtl;'>
            <div style='max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;'>
                <div style='height: 5px; background: linear-gradient(90deg, {$this->primaryColor} 0%, {$this->accentColor} 100%);'></div>
                <div style='padding: 40px 30px 20px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 34px; font-weight: 900; color: {$this->primaryColor}; text-transform: uppercase;'>Darby<span style='color: {$this->accentColor};'>.</span></h1>
                    <div style='font-size: 45px; margin-top: 15px;'>📋</div>
                </div>
                <div style='padding: 10px 35px 35px;'>
                    <h2 style='font-size: 18px; color: #1e293b; font-weight: 700; margin-bottom: 15px; text-align: center;'>{$greeting} <span style='color: {$this->primaryColor};'>{$fullName}</span>،</h2>
                    <p style='margin: 0 0 25px; font-size: 14px; color: #475569; line-height: 1.6; text-align: center;'>نشكرك على رغبتك في الانضمام إلى فريق Darby. بعد مراجعة ملفك الشخصي والوثائق المرفوعة، نأسف لإبلاغك بنقص أو عدم وضوح بعض البيانات مما أدى لرفض الطلب مؤقتاً.</p>
                    
                    <div style='background-color: #fff5f5; border-radius: 12px; padding: 20px; margin-bottom: 25px; border-right: 5px solid #ef4444;'>
                        <h4 style='margin: 0 0 8px; font-size: 14px; color: #991b1b; font-weight: 700;'>سبب الرفض الموضح من الإدارة:</h4>
                        <p style='margin: 0; font-size: 14px; color: #b91c1c; line-height: 1.6;'>{$rejectionReason}</p>
                    </div>
    
                    <div style='background-color: #f8fafc; border-radius: 10px; padding: 15px; margin-bottom: 30px; border: 1px solid #e2e8f0;'>
                        <p style='margin: 0; font-size: 12px; color: #64748b; line-height: 1.6;'>💡 <b>خطوتك القادمة:</b> لا تقلق، يمكنك تصحيح المشكلة المذكورة أعلاه والدخول مجدداً إلى التطبيق لإعادة رفع الوثائق الصحيحة ليقوم فريق الدعم بمراجعتها وتفعيل حسابك على الفور.</p>
                    </div>
                </div>
                <div style='background-color: #f8fafc; padding: 24px; text-align: center; border-top: 1px solid #eaedf1;'>
                    <p style='margin: 0; font-size: 12px; color: #94a3b8;'>إذا واجهت أي صعوبة، يرجى التواصل مع الدعم الفني للتطبيق.</p>
                    <p style='margin: 5px 0 0; font-size: 12px; color: #64748b;'>© " . date('Y') . " Darby. جميع الحقوق محفوظة.</p>
                </div>
            </div>
        </div>";
    }
}