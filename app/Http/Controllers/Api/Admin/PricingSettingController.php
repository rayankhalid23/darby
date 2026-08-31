<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shared\PricingSetting;
use App\Http\Requests\Api\Admin\UpdatePricingSettingRequest;

use App\Http\Resources\Api\Admin\PricingSettingResource;
use Illuminate\Http\Request;

class PricingSettingController extends Controller
{
    /**
     * Endpoint موحد لعرض وتعديل إعدادات التسعير
     * GET  -> يعرض الإعدادات الحالية
     * POST / PUT -> يقبل التعديلات ويحدث الإعدادات فوراً
     */
    public function manage(Request $request)
    {
        // 1. جلب السجل الوحيد للتسعير أو إنشائه بالقيم الافتراضية إذا لم يكن موجوداً
        $settings = PricingSetting::firstOrCreate([], [
            'discount_one_child'           => 0.00,
            'discount_two_children'        => 10.00,
            'discount_three_plus_children' => 15.00,
            'platform_commission_rate'    => 8.00,
            'price_per_km_ac'              => 2.50,
            'price_per_km_non_ac'          => 2.00,
            'location_change_fee'           => PricingSetting::DEFAULT_LOCATION_CHANGE_FEE,
            'location_change_fee_under_2km' => PricingSetting::DEFAULT_TIER_FEES[PricingSetting::TIER_UNDER_2KM],
            'location_change_fee_2_to_6km'  => PricingSetting::DEFAULT_TIER_FEES[PricingSetting::TIER_2_TO_6KM],
            'location_change_fee_6_to_10km' => PricingSetting::DEFAULT_TIER_FEES[PricingSetting::TIER_6_TO_10KM],
        ]);

        // 2. في حالة كان الطلب جلب بيانات (GET)
        if ($request->isMethod('get')) {
            return response()->json([
                'success' => true,
                'message' => 'تم جلب إعدادات التسعير بنجاح',
                'data'    => new PricingSettingResource($settings)
            ], 200);
        }

        // 3. في حالة كان الطلب تحديث البيانات (POST أو PUT)
        $formRequest = app(UpdatePricingSettingRequest::class);
        $validated = array_filter($formRequest->validated(), fn($value) => !is_null($value));

        if (!empty($validated)) {
            $settings->update($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث إعدادات التسعير بنجاح',
            'data'    => new PricingSettingResource($settings->fresh())
        ], 200);
    }
}