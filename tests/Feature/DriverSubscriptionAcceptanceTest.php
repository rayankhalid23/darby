<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Parent\School;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\Contract;
use App\Models\Shared\ActiveSubscription;
use App\Services\Shared\SubscriptionRequestService;
use App\Services\Shared\ContractService;
use Mockery;

/**
 * اختبار دالة قبول/رفض طلب اشتراك من قِبَل السائق
 *
 * يستخدم DatabaseTransactions: كل البيانات المُنشأة خلال الاختبار
 * تُحذف تلقائياً بعد انتهائه ← لا ضرر على قاعدة البيانات الحقيقية.
 */
class DriverSubscriptionAcceptanceTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================
    // بيانات مشتركة بين جميع الاختبارات
    // =========================================================
    protected User              $driverUser;
    protected Driver            $driver;
    protected User              $parentUser;
    protected ParentModel       $parent;
    protected Child             $child;
    protected School            $school;
    protected SubscriptionRequest $subscriptionRequest;

    // =========================================================
    // setUp: إعداد البيانات الأساسية قبل كل اختبار
    // =========================================================
    protected function setUp(): void
    {
        parent::setUp();

        // --- إدراج الأدوار إذا لم تكن موجودة ---
        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver',  'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent',  'display_name' => 'ولي أمر'],
        ]);

        // ---- 1. مستخدم السائق ----
        $this->driverUser = User::create([
            'full_name'    => 'سائق الاختبار',
            'email'        => 'driver.test.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('password123'),
            'role_id'      => 2,
            'is_active'    => 1,
        ]);

        // ---- 2. سجل السائق في جدول drivers ----
        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
            'current_lat'    => 32.8872,
            'current_lng'    => 13.1932,
        ]);

        // ---- 3. إدراج مركبة نشطة للسائق ----
        DB::table('vehicles')->insert([
            'driver_id'       => $this->driver->id,
            'brand'           => 'تويوتا',
            'model'           => 'هايس',
            'year'            => 2022,
            'color'           => 'أبيض',
            'plate_number'    => 'TEST-' . rand(1000, 9999),
            'capacity_manual' => 10,
            'capacity_ai'     => 10,
            'status'          => 'Active',
            'deleted_at'      => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // ---- 4. مستخدم ولي الأمر ----
        $this->parentUser = User::create([
            'full_name'    => 'ولي أمر الاختبار',
            'email'        => 'parent.test.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('password123'),
            'role_id'      => 3,
            'is_active'    => 1,
        ]);

        // ---- 5. سجل ولي الأمر في جدول parents ----
        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        // ---- 6.1 مدرسة الاختبار ----
        $this->school = School::create([
            'name'    => 'مدرسة الاختبار',
            'address' => 'شارع الاختبار',
            'lat'     => 32.9000,
            'lng'     => 13.2000,
            'status'  => 'active',
        ]);

        // ---- 6.2 عنوان ولي الأمر ----
        $addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'منزل ولي الأمر',
            'lat'        => 32.88,
            'lng'        => 13.19,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ---- 6. الطفل ----
        $this->child = Child::create([
            'parent_id' => $this->parent->id,
            'full_name' => 'طفل الاختبار',
            'birth_date'=> '2018-05-10',
            'gender'    => 'male',
            'grade'     => 1,
            'notification_radius' => 500,
        ]);

        // ---- 7. طلب الاشتراك المعلق ----
        $this->subscriptionRequest = SubscriptionRequest::create([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'school_id'         => $this->school->id,
            'subscription_type' => 'monthly',
            'direction'         => 'both',
            'timing'            => 'MORNING',
            'start_date'        => now()->addDays(1)->format('Y-m-d'),
            'end_date'          => now()->addMonths(1)->format('Y-m-d'),
            'days_count'        => 22,
            'total_price'       => 200.00,
            'pickup_time'       => '07:00:00',
            'dropoff_time'      => '14:00:00',
            'max_waiting_time'  => 15,
            'status'            => SubscriptionRequest::STATUS_PENDING,
            'children_count'    => 1,
        ]);

        // ---- 8. ربط الطفل بالطلب في جدول request_children ----
        DB::table('request_children')->insert([
            'request_id'         => $this->subscriptionRequest->id,
            'child_id'           => $this->child->id,
            'pickup_address_id'  => $addressId,
            'dropoff_address_id' => $this->school->id,
            'home_lat'           => 32.88,
            'home_lng'           => 13.19,
            'home_label'         => 'المنزل',
            'school_lat'         => 32.90,
            'school_lng'         => 13.20,
            'school_label'       => 'المدرسة',
            'price_per_child'    => 200.00,
        ]);
    }

    protected function tearDown(): void
    {
        // تأكد من إغلاق كل mock لتفادي تسرب الحالة بين الاختبارات
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================
    // اختبار 1: السائق يقبل الطلب بنجاح
    // =========================================================
    public function test_driver_can_accept_subscription_request(): void
    {
        // --- Mock لـ ContractService لتجنب PDF/OSRM الخارجيين ---
        $fakeContract = Contract::create([
            'subscription_request_id' => $this->subscriptionRequest->id,
            'parent_id'               => $this->parentUser->id,
            'driver_id'               => $this->driverUser->id,
            'contract_number'         => 'DRBY-TEST-' . rand(100000, 999999),
            'subscription_type'       => 'monthly',
            'direction'               => 'both',
            'timing'                  => 'MORNING',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
            'max_waiting_time'        => 15,
            'start_date'              => now()->addDays(1)->format('Y-m-d'),
            'end_date'                => now()->addMonths(1)->format('Y-m-d'),
            'days_count'              => 22,
            'total_price'             => 200.00,
            'clauses'                 => [],
            'status'                  => 'active',
            'signed_at'               => now(),
        ]);

        $mockContractService = Mockery::mock(ContractService::class);
        $mockContractService->shouldReceive('generateContract')
            ->once()
            ->andReturn($fakeContract->load(['subscriptionRequest', 'parent', 'driver', 'activeSubscriptions']));

        $this->app->instance(ContractService::class, $mockContractService);

        // --- إرسال طلب القبول ---
        $response = $this->actingAs($this->driverUser)
            ->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
                'status' => 'accepted',
            ]);

        // --- التحققات ---
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // تحقق أن حالة الطلب تغيّرت إلى accepted
        $this->assertDatabaseHas('requests', [
            'id'     => $this->subscriptionRequest->id,
            'status' => 'accepted',
        ]);

        // تحقق من إنشاء سجلات active_subscriptions
        $this->assertDatabaseHas('active_subscriptions', [
            'driver_id' => $this->driver->id,
            'child_id'  => $this->child->id,
            'status'    => 'active',
        ]);

        // جلب معرف الاشتراك النشط من قاعدة البيانات للتحقق من تطابقه مع الاستجابة
        $activeSub = ActiveSubscription::where('driver_id', $this->driver->id)
            ->where('child_id', $this->child->id)
            ->first();
        
        $this->assertNotNull($activeSub);

        // التحقق من وجود المعرّف (id) وتطابقه داخل تفاصيل اشتراك الطفل في مصفوفة الإخراج
        $response->assertJsonPath('data.children.0.subscription.id', $activeSub->id);

        // التحقق من وجود صورة الطفل (photo_url) في مصفوفة الإخراج
        $response->assertJsonStructure([
            'data' => [
                'children' => [
                    '*' => [
                        'photo_url',
                    ]
                ]
            ]
        ]);
    }

    // =========================================================
    // اختبار 1.5: قبول الطلب ينشئ مساراً ويخصم المقاعد المتاحة (بزيادة الاشتراكات النشطة)
    // =========================================================
    public function test_accepting_subscription_creates_route_and_deducts_available_seats(): void
    {
        $service = app(SubscriptionRequestService::class);

        $beforeActiveCount = ActiveSubscription::where('driver_id', $this->driver->id)->where('status', 'active')->count();

        // إكمال قبول الطلب
        $updatedRequest = $service->updateStatus($this->subscriptionRequest, 'accepted');

        // 1. تحقق من تغير حالة الطلب إلى accepted
        $this->assertEquals('accepted', $updatedRequest->status);

        // 2. تحقق من زيادة المقاعد المحجوزة (عدد الاشتراكات النشطة) بناءً على عدد الأطفال
        $afterActiveCount = ActiveSubscription::where('driver_id', $this->driver->id)->where('status', 'active')->count();
        $this->assertEquals($beforeActiveCount + $this->subscriptionRequest->children_count, $afterActiveCount);

        // 3. تحقق من إنشاء المسار في جدول routes
        $this->assertDatabaseHas('routes', [
            'driver_id'   => $this->driver->id,
            'contract_id' => $updatedRequest->contract->id,
            'status'      => 'Active',
        ]);

        // 4. تحقق من ربط active_subscriptions بـ route_id المولد
        $route = DB::table('routes')->where('contract_id', $updatedRequest->contract->id)->first();
        $this->assertNotNull($route);
        $this->assertDatabaseHas('active_subscriptions', [
            'driver_id'   => $this->driver->id,
            'contract_id' => $updatedRequest->contract->id,
            'route_id'    => $route->id,
            'status'      => 'active',
        ]);
    }

    // =========================================================
    // اختبار 1.6: السائق لا يمكنه قبول طلب إذا توازت أو تجاوزت سعة المركبة المتاحة
    // =========================================================
    public function test_accepting_subscription_fails_when_vehicle_capacity_is_exceeded(): void
    {
        // تحديد سعة المركبة بـ 1 مقعد
        DB::table('vehicles')->where('driver_id', $this->driver->id)->update(['capacity_manual' => 1]);

        $existingContract = Contract::create([
            'subscription_request_id' => $this->subscriptionRequest->id,
            'parent_id'               => $this->parentUser->id,
            'driver_id'               => $this->driverUser->id,
            'contract_number'         => 'DRBY-TEST-' . rand(100000, 999999),
            'subscription_type'       => 'monthly',
            'direction'               => 'both',
            'timing'                  => 'MORNING',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
            'max_waiting_time'        => 15,
            'start_date'              => now()->addDays(1)->format('Y-m-d'),
            'end_date'                => now()->addMonths(1)->format('Y-m-d'),
            'days_count'              => 22,
            'total_price'             => 200.00,
            'clauses'                 => [],
            'status'                  => 'active',
            'signed_at'               => now(),
        ]);

        // حجز المقعد المتاح باشتراك نشط سابق
        ActiveSubscription::create([
            'contract_id'   => $existingContract->id,
            'status'        => 'active',
            'child_id'      => $this->child->id,
            'driver_id'     => $this->driver->id,
            'parent_id'     => $this->parentUser->id,
            'pickup_lat'    => 32.88,
            'pickup_lng'    => 13.19,
            'pickup_label'  => 'منزل',
            'pickup_time'   => '07:00:00',
            'dropoff_lat'   => 32.90,
            'dropoff_lng'   => 13.20,
            'dropoff_label' => 'مدرسة',
            'dropoff_time'  => '14:00:00',
        ]);

        // محاولة قبول طلب جديد يتطلب مقعداً يفشل بسبب تجاوز السعة
        $response = $this->actingAs($this->driverUser)
            ->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
                'status' => 'accepted',
            ]);

        $response->assertStatus(500);
        $this->assertStringContainsString('أقل من عدد الأطفال', $response->json('message'));
    }

    // =========================================================
    // اختبار 2: السائق يرفض الطلب بنجاح مع سبب الرفض
    // =========================================================
    public function test_driver_can_reject_subscription_request_with_reason(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
                'status'           => 'rejected',
                'rejection_reason' => 'المنطقة بعيدة عن مساري اليومي.',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // تحقق من تحديث الحالة وسبب الرفض في قاعدة البيانات
        $this->assertDatabaseHas('requests', [
            'id'               => $this->subscriptionRequest->id,
            'status'           => 'rejected',
            'rejection_reason' => 'المنطقة بعيدة عن مساري اليومي.',
        ]);
    }

    // =========================================================
    // اختبار 3: رفض بدون سبب يفشل بـ 422
    // =========================================================
    public function test_reject_without_reason_fails_validation(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
                'status' => 'rejected',
                // rejection_reason مفقود عمداً
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['rejection_reason']);
    }

    // =========================================================
    // اختبار 4: طلب بـ status غير صالح يفشل بـ 422
    // =========================================================
    public function test_invalid_status_fails_validation(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
                'status' => 'pending', // غير مسموح به
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    // =========================================================
    // اختبار 5: محاولة قبول طلب ليس مخصصاً للسائق تُرفض بـ 403
    // =========================================================
    public function test_driver_cannot_accept_another_drivers_request(): void
    {
        // إنشاء سائق آخر
        $anotherDriverUser = User::create([
            'full_name'    => 'سائق آخر',
            'email'        => 'another.driver.' . uniqid() . '@darby.test',
            'phone_number' => '095' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('password123'),
            'role_id'      => 2,
            'is_active'    => 1,
        ]);

        Driver::create([
            'user_id'        => $anotherDriverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        // السائق الآخر يحاول قبول طلب ليس له
        $response = $this->actingAs($anotherDriverUser)
            ->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
                'status' => 'accepted',
            ]);

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
    }

    // =========================================================
    // اختبار 6: محاولة قبول طلب غير موجود تُرجع 404
    // =========================================================
    public function test_accepting_nonexistent_request_returns_404(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->putJson("/api/driver/999999/status", [
                'status' => 'accepted',
            ]);

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
    }

    // =========================================================
    // اختبار 7: مستخدم ليس سائقاً يُرجع 403
    // =========================================================
    public function test_non_driver_user_gets_403(): void
    {
        // ولي الأمر يحاول استخدام مسار السائق
        $response = $this->actingAs($this->parentUser)
            ->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
                'status' => 'accepted',
            ]);

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
    }

    // =========================================================
    // اختبار 8: مستخدم غير مسجّل دخوله يُرجع 401
    // =========================================================
    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
            'status' => 'accepted',
        ]);

        $response->assertStatus(401);
    }
}
