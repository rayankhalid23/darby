<?php

namespace App\Http\Resources\Api\Parent;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class SubscriptionRequestResource extends JsonResource
{
    /**
     * تحويل بيانات طلب الاشتراك بالشكل الشامل والمفصل
     */
    public function toArray($request)
    {
        // جلب بيانات مستخدم ولي الأمر والسائق بشكل آمن
        $parentUser = $this->parent->user ?? $this->user ?? null;
        $driverUser = $this->driver->user ?? null;

        // جلب تواريخ الاشتراك (من الطلب أو العقد)
        $startDate = $this->start_date ?? $this->contract->start_date ?? null;
        $endDate   = $this->end_date ?? $this->contract->end_date ?? null;

        // حساب عدد الأيام الفعلية بدون الجمعة والسبت
        $workingDaysCount = $this->calculateWorkingDays($startDate, $endDate);

        // استخراج وتجهيز بيانات الأطفال
        $childrenData  = $this->getFormattedChildrenDetails();
        $childrenCount = count($childrenData) > 0 ? count($childrenData) : ($this->children_count ?? 1);
        $totalAmount   = (float) ($this->total_price ?? $this->total_amount ?? $this->contract->total_price ?? 0);

        return [
            'id'                 => $this->id,
            'status'             => $this->status,

            // 📅 التواريخ وعدد أيام العمل الفعلية (بدون جمعة وسبت)
            'start_date'         => $startDate ? Carbon::parse($startDate)->format('Y-m-d') : null,
          
            'working_days_count' => $workingDaysCount, // 👈 عدد الأيام بدون الجمعة والسبت

            'total_amount'       => $totalAmount,
            'children_count'     => $childrenCount,
      

            // 👨‍👩‍👦 بيانات ولي الأمر
            'parent' => [
                'id'    => $this->parent_id ?? $parentUser?->id,
                'name'  => $parentUser?->full_name ?? $parentUser?->name ?? 'غير محدد',
                'phone' => $parentUser?->phone_number ?? $parentUser?->phone ?? 'غير محدد',
            ],

            // 🚘 بيانات السائق
            'driver' => [
                'id'    => $this->driver_id ?? $this->driver?->id,
                'name'  => $driverUser?->full_name ?? $driverUser?->name ?? 'غير محدد',
                'phone' => $driverUser?->phone_number ?? $driverUser?->phone ?? null,
            ],

            // 👶 تفاصيل الأطفال المشمولين بالطلب (السعر وبيانات الاشتراك المخصصة داخل كل طفل)
            'children' => $childrenData,

            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->format('Y-m-d H:i:s') : null,
        ];
    }

    /**
     * دالة حساب أيام العمل الفعلية (استثناء الجمعة والسبت)
     */
    protected function calculateWorkingDays($startDateStr, $endDateStr): int
    {
        if (!$startDateStr || !$endDateStr) {
            return 0;
        }

        $start = Carbon::parse($startDateStr);
        $end   = Carbon::parse($endDateStr);

        if ($start->gt($end)) {
            return 0;
        }

        $workingDays = 0;
        $period = CarbonPeriod::create($start, $end);

        foreach ($period as $date) {
            // الاستثناء: ليس جمعة وليس سبت
            if (!$date->isFriday() && !$date->isSaturday()) {
                $workingDays++;
            }
        }

        return $workingDays;
    }

    /**
     * دالة تنسيق تفاصيل كل طفل مع السعر وبيانات الاشتراك المخصصة له
     */
    protected function getFormattedChildrenDetails(): array
    {
        $items = $this->requestChildren ?? $this->children ?? collect();
        $totalAmount = (float) ($this->total_price ?? $this->total_amount ?? $this->contract->total_price ?? 0);
        $count = $items->count() > 0 ? $items->count() : 1;

        return $items->map(function ($item) use ($totalAmount, $count) {
            $child = $item->child ?? $item;

            // 1. حساب تواريخ ومدد اشتراك الطفل المحددة (مع Fallback لتواريخ الطلب الرئيسية)
            $childStartDate = $item->start_date ?? $child->start_date ?? $this->start_date ?? $this->contract->start_date ?? null;
            $childEndDate   = $item->end_date ?? $child->end_date ?? $this->end_date ?? $this->contract->end_date ?? null;
            $childWorkingDays = $this->calculateWorkingDays($childStartDate, $childEndDate);

            // 2. نوع الاشتراك لكل طفل (شهري، فصلي، سنوي، إلخ)
            $subscriptionType = $item->subscription_type 
                ?? $item->plan_type 
                ?? $item->package_type 
                ?? $child->subscription_type 
                ?? $this->subscription_type 
                ?? $this->plan_type 
                ?? 'غير محدد';

            // 3. طبيعة الرحلة لكل طفل (ذهاب فقط / إياد فقط / ذهاب وإياب)
            $tripType = $item->trip_type 
                ?? $item->direction 
                ?? $item->service_type 
                ?? $child->trip_type 
                ?? $this->trip_type 
                ?? 'two_way'; // الافتراضي: ذهاب وإياب

            // 4. جلب سعر الطفل المباشر أو حسابه تقسيماً
            $childPrice = (float) (
                $item->price 
                ?? $item->child_price 
                ?? $item->cost 
                ?? $item->pivot?->price 
                ?? $child->subscription_price 
                ?? ($totalAmount / $count)
            );

            return [
                'id'          => $child->id ?? null,
                'name'        => $child->name ?? $child->full_name ?? 'غير محدد',
                'price'       => round($childPrice, 2),
                'gender'      => $child->gender ?? null,
                'age'         => $child->age ?? null,
                'photo_url'   => !empty($child->photo_url) ? asset($child->photo_url) : null,

                // 📌 تفاصيل بيانات اشتراك هذا الطفل بحد ذاته
                'subscription' => [
                    'type'               => $subscriptionType, // نوع الاشتراك (monthly, term, yearly...)
                    'trip_type'          => $tripType,         // طبيعة الرحلة (one_way_go, one_way_return, two_way)
                    'start_date'         => $childStartDate ? Carbon::parse($childStartDate)->format('Y-m-d') : null,
                    'end_date'           => $childEndDate ? Carbon::parse($childEndDate)->format('Y-m-d') : null,
                    'working_days_count' => $childWorkingDays, // مدة الاشتراك بالأيام الفعلية لهذا الطفل
                ],

                // 🏫 تفاصيل المدرسة
                'school' => [
                    'id'        => $child->school->id ?? $item->school_id ?? null,
                    'name'      => $child->school->name ?? $item->school_name ?? 'غير محدد',
                    'address'   => $child->school->address ?? null,
                    'latitude'  => (float) ($child->school->latitude ?? $item->dropoff_lat ?? 0),
                    'longitude' => (float) ($child->school->longitude ?? $item->dropoff_lng ?? 0),
                ],

                // 🏠 تفاصيل البيت (مكان الركوب)
                'home' => [
                    'address'   => $item->pickup_label ?? $child->address ?? $child->home_address ?? 'غير محدد',
                    'latitude'  => (float) ($item->pickup_lat ?? $child->latitude ?? 0),
                    'longitude' => (float) ($item->pickup_lng ?? $child->longitude ?? 0),
                    'notes'     => $item->pickup_notes ?? $child->notes ?? null,
                ],

                'pickup_time'  => isset($item->pickup_time) ? Carbon::parse($item->pickup_time)->format('H:i') : null,
                'dropoff_time' => isset($item->dropoff_time) ? Carbon::parse($item->dropoff_time)->format('H:i') : null,
            ];
        })->toArray();
    }
}