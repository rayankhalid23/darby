<?php

namespace Tests\Feature;

use App\Jobs\EvaluateDriverPolicyJob;
use App\Models\Admin\AdminAlert;
use App\Models\Driver\Driver;
use App\Models\Shared\DriverReview;
use App\Models\User;
use App\Services\AiClassifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiPolicyIntegrationTest extends TestCase
{
    /**
     * اختبار الربط الدقيق بين لارافيل و FastAPI مع فحص مصفوفة الأفعال وتقييمات آخر 14 يوماً فقط
     */
    public function test_evaluate_driver_policy_job_handles_fastapi_response_actions(): void
    {
        // 1. إعداد حساب مستخدم وسائق
        $user = User::factory()->create();

        $driver = Driver::create([
            'user_id'       => $user->id,
            'status'        => 'Approved',
            'is_searchable' => true,
            'rating_avg'    => 4.5,
        ]);

        $parentUser1 = User::factory()->create();
        $parentUser2 = User::factory()->create();

        // 2. إنشاء تقييم حديث (ضمن آخر 14 يوماً)
        DriverReview::create([
            'driver_id'  => $driver->id,
            'parent_id'  => $parentUser1->id,
            'rating'     => 1,
            'comment'    => 'السائق تأخر وشتم الطفل في الطريق',
            'created_at' => now()->subDays(2),
        ]);

        // 3. إنشاء تقييم قديم (أكثر من 14 يوماً) - يجب أن يُستبعد من الـ Payload
        $oldReview = DriverReview::create([
            'driver_id'  => $driver->id,
            'parent_id'  => $parentUser2->id,
            'rating'     => 1,
            'comment'    => 'تقييم قديم جداً من السنة الماضية',
        ]);
        $oldReview->created_at = now()->subDays(30);
        $oldReview->save();

        // 4. محاكاة استجابة FastAPI بنقاط الأفعال المعتمدة
        Http::fake([
            '*' => Http::response([
                'driver_id'     => (string) $driver->id,
                'actions'       => ['BLOCK_FROM_SEARCH', 'ADJUST_RATING', 'SEND_ADMIN_ALERT'],
                'rating_change' => -0.5,
                'message_ar'    => 'تم رصد سلوك خطير وتقرر حظر السائق وتعديل التقييم.',
            ], 200),
        ]);

        // 5. تنفيذ الـ Job بشكل متزامن
        EvaluateDriverPolicyJob::dispatchSync($driver->id);

        // 6. التحقق من تطبيق التغييرات على قاعدة البيانات
        $driver->refresh();

        // أ) التحقق من الحظر وتغيير الحالة
        $this->assertEquals('Suspended', $driver->status);
        $this->assertFalse((bool) $driver->is_searchable);

        // ب) التحقق من تعديل التقييم (4.5 - 0.5 = 4.0)
        $this->assertEquals(4.0, (float) $driver->rating_avg);

        // ج) التحقق من إنشاء سجل التنبيه في admin_alerts
        $this->assertDatabaseHas('admin_alerts', [
            'driver_id'  => $driver->id,
            'alert_type' => 'suspend_driver',
            'severity'   => 3,
        ]);

        // د) التأكد من إرسال طلب HTTP إلى FastAPI بالحمولة والتصنيف الصحيحين لـ 14 يوماً فقط
        Http::assertSent(function ($request) use ($driver) {
            $body = $request->data();
            $reviews = $body['reviews'] ?? [];

            return str_contains($request->url(), '/api/v1/make-decision')
                && $body['driver_id'] === (string) $driver->id
                && count($reviews) === 1
                && $reviews[0]['text'] === 'السائق تأخر وشتم الطفل في الطريق';
        });
    }
}
