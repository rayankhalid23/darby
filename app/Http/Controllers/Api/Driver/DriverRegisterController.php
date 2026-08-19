<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\RegisterAccountRequest;
use App\Http\Requests\Api\Driver\CompleteProfileRequest;
use App\Http\Requests\Api\Driver\ProfileUpdateRequest; 
use App\Http\Requests\Api\Shared\OtpRequest;
use App\Services\Driver\DriverRegisterService;
use App\Services\Shared\OtpService;
use App\Http\Resources\Api\Driver\DriverResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class DriverRegisterController extends Controller
{
    protected DriverRegisterService $registerService;
    protected OtpService $otpService;

    public function __construct(DriverRegisterService $registerService, OtpService $otpService)
    {
        $this->registerService = $registerService;
        $this->otpService = $otpService;
    }

    /**
     * الخطوة 1: طلب تسجيل الحساب وإرسال الـ OTP (دون الحفظ في قاعدة البيانات)
     */
    public function registerAccount(RegisterAccountRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // استدعاء الدالة الجديدة لإرسال الـ OTP فقط لحماية النظام من البيانات الوهمية
            $this->registerService->sendVerificationOtp($data);

            return response()->json([
                'status'  => true,
                'message' => 'تم إرسال رمز التحقق (OTP) إلى بريدك الإلكتروني بنجاح.',
            ], 200);

        } catch (Exception $e) {
            Log::error("Account Registration OTP Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'فشل إرسال رمز التحقق.'], 500);
        }
    }

    /**
     * الخطوة 2: التحقق من رمز OTP وإنشاء الحساب وتفعيله فوراً
     */
    public function verifyOtp(OtpRequest $request): JsonResponse
    {
        try {
            // 1. التحقق من صحة الـ OTP
            $result = $this->otpService->verify($request->email, $request->otp, 'REGISTER');
            
            if (!$result['success']) {
                return response()->json([
                    'status'  => false,
                    'message' => $result['message']
                ], 400);
            }

            // 2. إذا كان الرمز صحيحاً، نقوم بإنشاء الحساب وتفعيله مباشرة
            // ملاحظة تهمك كمطور: يجب على تطبيق الموبايل إرسال بيانات التسجيل كاملة مع الـ OTP لتتم العملية في خطوة واحدة
            $user = $this->registerService->registerAccountAfterOtp($request->all());
            
            // 3. إنشاء توكن الدخول المباشر للحساب الجديد المفعّل
            $token = $user->createToken('driver_token')->plainTextToken;

            return response()->json([
                'status'  => true,
                'message' => 'تم تفعيل الحساب وإنشاؤه بنجاح.',
                'user_id' => $user->id,
                'token'   => $token
            ], 201);

        } catch (Exception $e) {
            Log::error("OTP Verification & Creation Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'فشل التحقق وإنشاء الحساب.'], 500);
        }
    }

    /**
     * المرحلة الثانية: إكمال ملف السائق (المركبة + الوثائق) لأول مرة
     */
    public function completeProfile(CompleteProfileRequest $request, int $userId): JsonResponse
    {
        $uploadedPaths = [];
        try {
            $data = $request->validated();

            // رفع صورة المركبة
            $path = $request->file('vehicle_image')->store('drivers/vehicles', 'public');
            $data['vehicle_image_path'] = 'storage/' . $path;
            $uploadedPaths[] = $path;

            // رفع صور المستندات
            $docFileMap = [
                'doc_license'   => 'doc_license_path',
                'doc_logbook'   => 'doc_logbook_path',
                'doc_insurance' => 'doc_insurance_path',
            ];

            foreach ($docFileMap as $fileField => $pathKey) {
                $path = $request->file($fileField)->store('drivers/documents', 'public');
                $data[$pathKey] = 'storage/' . $path;
                $uploadedPaths[] = $path;
            }

            $driver = $this->registerService->completeProfile($userId, $data);

            return response()->json([
                'status'  => true,
                'message' => 'تم رفع البيانات بنجاح، بانتظار مراجعة الإدارة.',
                'data'    => new DriverResource($driver)
            ], 200);

        } catch (Exception $e) {
            foreach ($uploadedPaths as $storedPath) {
                Storage::disk('public')->delete($storedPath);
            }
            Log::error("Complete Profile Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'فشل إكمال الملف الشخصي للمركبة والمستندات.'], 500);
        }
    }
}