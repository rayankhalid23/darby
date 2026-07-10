<?php

namespace App\Services\Parent;

use App\Models\Parent\Child;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class ChildService
{
    /**
     * إنشاء طفل جديد في النظام مع معالجة رفع الصورة المخصصة له.
     */
    public function createChild(array $data): Child
    {
        // 1. التحقق من التكرار
        $exists = Child::where('parent_id', $data['parent_id'])
                       ->where('full_name', $data['full_name'])
                       ->exists();
    
        if ($exists) {
            throw new \Illuminate\Validation\ValidationException(
                \Illuminate\Support\Facades\Validator::make([], []), 
                response()->json([
                    'status' => false, 
                    'message' => 'هذا الطفل مضاف مسبقاً في حسابك.'
                ], 422)
            );
        }
    
        // 2. استخدام Transaction
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            
            // معالجة الصورة
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $data['photo_url'] = $this->uploadPhoto($data['photo']);
                unset($data['photo']);
            }
    
            // إنشاء الطفل (تأكد أن حقول logistics ليست ضمن $data الأساسية للطفل)
            // نقوم بفلترة البيانات أو استخدام المصفوفة المباشرة
            $child = Child::create($data);
    
            // 3. إضافة بيانات الـ Logistics مع الحقول الجديدة
            $child->logistics()->create([
                'preferred_time_slot' => $data['preferred_time_slot'],
            'trip_direction'      => $data['trip_direction'],
            'pickup_time'         => $data['pickup_time'] ?? null,
            'dropoff_time'        => $data['dropoff_time'] ?? null,
            'start_date'          => $data['start_date'],
            'end_date'            => $data['end_date'],
            'subscription_type'   => $data['subscription_type'],
            'is_active'           => true,
            ]);
    
            return $child;
        });
    }

   /**
     * تحديث بيانات الطفل وبيانات اشتراكه اللوجستي في نفس الوقت.
     */
   public function updateChild(Child $child, array $data): Child
   {
       // 1. تأمين البيانات
       unset($data['qr_code_token']);

       // 2. معالجة تحديث الصورة
       if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
           $this->deletePhoto($child->photo_url);
           $data['photo_url'] = $this->uploadPhoto($data['photo']);
           unset($data['photo']);
       }

       // 3. تحديث بيانات الطفل الأساسية
       $child->update($data);

       // 4. تحديث بيانات الاشتراك (Logistics) إذا تم إرسال أي منها في الطلب
       // نتحقق من وجود أي حقل خاص بالاشتراك قبل التحديث
       $logisticsFields = ['preferred_time_slot', 'trip_direction', 'pickup_time', 'dropoff_time', 'start_date', 'end_date', 'subscription_type', 'is_active'];
       
       $logisticsData = array_intersect_key($data, array_flip($logisticsFields));

       if (!empty($logisticsData)) {
           $child->logistics()->update($logisticsData);
       }

       return $child;
   }

    /**
     * حذف طفل من النظام نهائياً مع حذف صورته المرفقة.
     */
    public function deleteChild(Child $child): bool
    {
        // حذف الملف المادي للصورة باستخدام الحقل الصحيح photo_url
        $this->deletePhoto($child->photo_url);

        // حذف السجل من قاعدة البيانات
        return $child->delete();
    }

    /**
     * دالة مساعدة خاصة برفع صور الأطفال بشكل آمن ومنظم
     */
    private function uploadPhoto(UploadedFile $photo): string
    {
        // تخزين الصورة في مجلد 'children_photos' داخل قرص الـ public
        return $photo->store('children_photos', 'public');
    }

    /**
     * دالة مساعدة خاصة بحذف صور الأطفال من قرص التخزين
     */
    private function deletePhoto(?string $photoPath): void
    {
        if ($photoPath && Storage::disk('public')->exists($photoPath)) {
            Storage::disk('public')->delete($photoPath);
        }
    }
}