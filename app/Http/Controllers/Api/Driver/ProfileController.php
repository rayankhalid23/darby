<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\ProfileUpdateRequest;
use App\Services\Driver\DriverProfileService;
use App\Http\Resources\Api\Driver\DriverResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ProfileController extends Controller
{
    protected DriverProfileService $profileService;

    public function __construct(DriverProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    /**
     * 1. تحديث بيانات الملف الشخصي للسائق (PATCH/POST)
     */
    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $uploadedPaths = [];
        try {
            $user = auth()->user();
            $validatedData = $request->validated();

            if ($request->hasFile('avatar')) {
                if ($user->avatar_url) {
                    $oldPath = str_replace('storage/', '', $user->avatar_url);
                    Storage::disk('public')->delete($oldPath);
                }

                $avatarPath = $request->file('avatar')->store('drivers/avatars', 'public');
                $validatedData['avatar_url'] = 'storage/' . $avatarPath; 
                $uploadedPaths[] = $avatarPath;
            }

            // استدعاء الخدمة لتحديث البيانات الشخصية الفورية أو حجز الحساسة
            $result = $this->profileService->updateDriverProfile($user->id, $validatedData);

            return response()->json([
                'status'  => true,
                'message' => $result['message'],
                'data'    => new DriverResource($result['driver'])
            ], 200);

        } catch (Exception $e) {
            foreach ($uploadedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            Log::error("Driver Profile Update Error: " . $e->getMessage(), ['user_id' => auth()->id()]);

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage() ?: 'تعذر تحديث البيانات بسبب مشكلة تقنية.',
            ], 500);
        }
    }

    /**
     * 2. عرض بيانات الملف الشخصي للسائق بالكامل مع ملحقاته
     */
    public function show(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $driver = $user->driver()->with(['vehicles', 'documents'])->first();

            if (!$driver) {
                return response()->json([
                    'status'  => false,
                    'message' => 'عذراً، هذا الحساب غير مرتبط بملف سائق نشط على النظام.'
                ], 404);
            }

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب البيانات بنجاح.',
                'data'    => new DriverResource($driver)
            ], 200);

        } catch (Exception $e) {
            Log::error("Driver Profile Fetch Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'حدث خطأ أثناء جلب الملف الشخصي.'], 500);
        }
    }

    /**
     * 3. تفعيل واعتماد البريد الإلكتروني الجديد من الرابط الموقّع
     */
    public function approveEmailChange(int $id): JsonResponse
    {
        try {
            $this->profileService->approveEmailChange($id);
            
            return response()->json([
                'status'  => true,
                'message' => 'تم تأكيد وتحديث البريد الإلكتروني بنجاح في النظام.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage() ?: 'رابط التأكيد غير صالح أو منتهي الصلاحية.'
            ], 400);
        }
    }

    /**
     * 4. إلغاء طلب تعديل البريد الإلكتروني القادم من الرابط الموقّع
     */
    public function rejectEmailChange(int $id): JsonResponse
    {
        try {
            $this->profileService->rejectEmailChange($id);
            
            return response()->json([
                'status'  => true,
                'message' => 'تم إلغاء طلب تغيير البريد الإلكتروني بنجاح.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 5. تحديث وتجديد المستندات والوثائق الرسمية للسائق
     */
    public function updateLegalData(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'national_id'               => 'required|string',
                'license_number'             => 'required|string',
                'license_expiry'             => 'required|date',
                'doc_license_path'           => 'required|string',
                'doc_logbook_path'           => 'required|string',
                'doc_insurance_path'         => 'required|string',
                'doc_criminal_record_path'   => 'required|string',
            ]);

            $user = auth()->user();
            $result = $this->profileService->updateLegalDocuments($user->id, $validatedData);

            return response()->json([
                'status'  => true,
                'message' => $result['message']
            ], 200);

        } catch (Exception $e) {
            Log::error("Driver Legal Data Update Error: " . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage() ?: 'حدث خطأ أثناء تحديث البيانات القانونية.'
            ], 500);
        }
    }

    /**
     * 6. تحديث بيانات وتفاصيل المركبة وتجميدها لحين مراجعة الأدمن
     */
    public function updateVehicle(Request $request, $vehicleId): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'has_ac'             => 'nullable|boolean',
                'plate_number'       => 'nullable|string',
                'brand'              => 'nullable|string',
                'model'              => 'nullable|string',
                'year'               => 'nullable|integer',
                'color'              => 'nullable|string',
                'type'               => 'nullable|string',
                'capacity_manual'    => 'nullable|integer',
                'vehicle_image_path' => 'nullable|string',
            ]);

            $user = auth()->user();
            $vehicle = $this->profileService->updateVehicleDetails($user->id, (int)$vehicleId, $validatedData);

            return response()->json([
                'status'  => true,
                'message' => 'تم تحديث تفاصيل المركبة بنجاح، وهي قيد المراجعة والتدقيق الآن من قبل الإدارة.',
                'data'    => $vehicle
            ], 200);

        } catch (Exception $e) {
            Log::error("Driver Vehicle Update Error: " . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage() ?: 'حدث خطأ أثناء تحديث بيانات المركبة.'
            ], 500);
        }
    }
/**
 * عرض المستندات الحالية للسائق مع ملفات الوثائق المرفوعة
 * بناءً على جداول النظام الموضحة في image_075cae.png و image_075cae.png
 */
public function showLegalData(Request $request)
{
    // جلب المستخدم وتحميل بيانات السائق والوثائق المرتبطة به دفعة واحدة
    $user = $request->user()->load(['driver.documents']);

    // التحقق من وجود حساب سائق
    if (!$user->driver) {
        return response()->json([
            'status'  => false,
            'message' => 'لم يتم العثور على بيانات رسمية لهذا الحساب.'
        ], 404);
    }

    return response()->json([
        'status'  => true,
        'message' => 'تم استرجاع الوثائق الرسمية بنجاح.',
        'data'    => [
            // 1. البيانات الأساسية من جدول drivers
            'national_id'    => $user->driver->national_id,
            'license_number' => $user->driver->license_number,
            'license_expiry' => $user->driver->license_expiry,
            'driver_status'  => $user->driver->status, // حالة السائق العامة

            'uploaded_files' => $user->driver->documents->map(function ($document) {
                return [
                    'id'                    => $document->id,
                    'vehicle_id'            => $document->vehicle_id,
                    'doc_type'              => $document->doc_type,
                    'file_url'              => $document->file_url,
                    'license_expiry_date'   => $document->license_expiry_date,
                    'insurance_expiry_date' => $document->insurance_expiry_date,
                    'document_status'       => $document->status,
                    'feedback'              => $document->feedback,
                    
                    // التعديل هنا: نستخدم Carbon::parse لمعالجة النص القادم من قاعدة البيانات بأمان
                    'uploaded_at'           => $document->uploaded_at 
                        ? \Carbon\Carbon::parse($document->uploaded_at)->toDateTimeString() 
                        : null,
                ];
            })
        ]
    ], 200);
}

/**
 * عرض بيانات وتفاصيل المركبة الحالية للسائق
 * بناءً علىโครงสร้าง جدول vehicles في image_074e49.png
 */
public function showVehicle(Request $request)
{
    // جلب المستخدم وتحميل السائق مع مركبته (Nested Eager Loading)
    $user = $request->user()->load('driver.vehicle');

    // التحقق من وجود السائق ووجود مركبة مرتبطة به
    if (!$user->driver || !$user->driver->vehicle) {
        return response()->json([
            'status'     => false,
            'error_code' => 'VEHICLE_NOT_FOUND',
            'message'    => 'لم يتم تسجيل أي مركبة لهذا السائق حتى الآن.'
        ], 404);
    }

    $vehicle = $user->driver->vehicle;

    return response()->json([
        'status'  => true,
        'message' => 'تم استرجاع بيانات المركبة بنجاح.',
        'data'    => [
            'id'                => $vehicle->id,
            'plate_number'      => $vehicle->plate_number,
            'brand'             => $vehicle->brand,
            'model'             => $vehicle->model,
            'year'              => $vehicle->year,
            'color'             => $vehicle->color,
            'type'              => $vehicle->type,
            'capacity_manual'   => $vehicle->capacity_manual,
            'capacity_ai'       => $vehicle->capacity_ai,
            'is_verified'       => (bool) $vehicle->is_verified,
            'vehicle_image_url' => $vehicle->vehicle_image_url,
            'has_ac'            => (bool) $vehicle->has_ac,
            'status'            => $vehicle->status,
        ]
    ], 200);
}
    
}