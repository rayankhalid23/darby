<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComplaintAndReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. إضافة بيانات وهمية لجدول الشكاوي (complaints)
        DB::table('complaints')->insert([
            [
                'submitted_by'   => 1, 
                'against_type'   => 'DRIVER',
                'against_id'     => 21,
                'driver_id'      => 21,
                'trip_id'        => null,          // رحلة غير محددة (تجنباً للمشاكل)
                'description'    => 'هذه شكوى تجريبية وهمية للتأكد من النظام.',
                'status'         => 'PENDING',
                'resolved_by'    => null,          // لا يوجد أدمن قام بالحل بعد
                'resolution_note'=> null,          
                'action_taken'   => 'PENDING',     
                'action_details' => 'جاري متابعة الحالة', 
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);

        // 2. إضافة بيانات وهمية لجدول التعليقات/التقييمات (driver_reviews)
        DB::table('driver_reviews')->insert([
            [
                'parent_id'  => 1, 
                'driver_id'  => 21, 
                'contract_id'=> null, // تم جعلها null حصراً لكي لا يطلب عقداً برقم 1 غير موجود
                'rating'     => 5,
                'comment'    => 'تعليق تجريبي: السائق ممتاز وملتزم بالمواعيد.',
                'status'     => 'APPROVED',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}