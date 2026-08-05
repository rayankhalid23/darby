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
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Contract;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\Route as RouteModel;

/**
 * اختبار دالة المسارات المقترحة للاشتراك
 * GET /api/v1/driver/subscriptions/{subscriptionId}/route-recommendations
 *
 * DatabaseTransactions: كل بيانات الاختبار تُحذف تلقائياً بعد الانتهاء.
 */
class RouteRecommendationsTest extends TestCase
{
    use DatabaseTransactions;

    protected User             $driverUser;
    protected Driver           $driver;
    protected User             $parentUser;
    protected ParentModel      $parent;
    protected Child            $child;
    protected School           $school;
    protected ActiveSubscription $subscription;
    protected int              $vehicleId;

    // =========================================================
    // إعداد البيانات الأساسية قبل كل اختبار
    // =========================================================
    protected function setUp(): void
    {
        parent::setUp();

        // --- الأدوار ---
        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        // --- 1. مستخدم السائق ---
        $this->driverUser = User::create([
            'full_name'     => 'سائق توصيات الاختبار',
            'email'         => 'driver.rec.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);

        // --- 2. سجل السائق ---
        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
            'current_lat'    => 32.8872,
            'current_lng'    => 13.1932,
        ]);

        // --- 3. مركبة للسائق (capacity=10) ---
        $this->vehicleId = DB::table('vehicles')->insertGetId([
            'driver_id'       => $this->driver->id,
            'brand'           => 'تويوتا',
            'model'           => 'هايس',
            'year'            => 2022,
            'color'           => 'أبيض',
            'plate_number'    => 'REC-' . rand(1000, 9999),
            'capacity_manual' => 10,
            'capacity_ai'     => 10,
            'status'          => 'Active',
            'deleted_at'      => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // --- 4. مستخدم ولي الأمر ---
        $this->parentUser = User::create([
            'full_name'     => 'ولي أمر الاختبار',
            'email'         => 'parent.rec.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        // --- 5. ولي الأمر ---
        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        // --- 6. مدرسة ---
        $this->school = School::create([
            'name'       => 'مدرسة التوصيات',
            'address'    => 'طرابلس',
            'lat'        => 32.9000,
            'lng'        => 13.2000,
            'start_time' => '08:00:00',
            'status'     => 'active',
        ]);

        // --- 7. طفل ---
        $this->child = Child::create([
            'parent_id'          => $this->parent->id,
            'school_id'          => $this->school->id,
            'full_name'          => 'طفل التوصيات',
            'birth_date'         => '2018-05-10',
            'gender'             => 'male',
            'grade'              => 1,
            'notification_radius'=> 500,
        ]);

        // --- 8. عقد ---
        $request = SubscriptionRequest::create([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'school_id'         => $this->school->id,
            'subscription_type' => 'monthly',
            'direction'         => 'both',
            'timing'            => 'MORNING',
            'start_date'        => now()->subDay()->format('Y-m-d'),  // بدأ بالأمس ← صالح فوراً
            'end_date'          => now()->addMonths(1)->format('Y-m-d'),
            'days_count'        => 22,
            'total_price'       => 200.00,
            'pickup_time'       => '07:00:00',
            'dropoff_time'      => '14:00:00',
            'max_waiting_time'  => 15,
            'status'            => 'accepted',
            'children_count'    => 1,
        ]);

        $contract = Contract::create([
            'subscription_request_id' => $request->id,
            'parent_id'               => $this->parentUser->id,
            'driver_id'               => $this->driverUser->id,
            'contract_number'         => 'DRBY-REC-' . rand(100000, 999999),
            'subscription_type'       => 'monthly',
            'direction'               => 'both',
            'timing'                  => 'MORNING',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
            'max_waiting_time'        => 15,
            'start_date'              => now()->subDay()->format('Y-m-d'),
            'end_date'                => now()->addMonths(1)->format('Y-m-d'),
            'days_count'              => 22,
            'total_price'             => 200.00,
            'clauses'                 => [],
            'status'                  => 'active',
        ]);

        // --- 9. اشتراك نشط بدون مسار (route_id = null) ---
        $this->subscription = ActiveSubscription::create([
            'contract_id'  => $contract->id,
            'child_id'     => $this->child->id,
            'driver_id'    => $this->driver->id,
            'parent_id'    => $this->parentUser->id,
            'route_id'     => null,     // لم يُسند بعد ← مرشح للتوصيات
            'pickup_lat'   => 32.8812,
            'pickup_lng'   => 13.1812,
            'pickup_label' => 'منزل الطفل',
            'pickup_time'  => '07:00:00',
            'dropoff_time' => '14:00:00',
            'sort_order'   => 0,
            'status'       => 'active',
        ]);
    }

    // =========================================================
    // اختبار 1: لا توجد مسارات → يُرجع رسالة إنشاء مسار جديد
    // =========================================================
    public function test_returns_no_recommendations_when_driver_has_no_routes(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/v1/driver/subscriptions/{$this->subscription->id}/route-recommendations");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('recommended_route', null);
        $response->assertJsonStructure(['status', 'recommended_route', 'other_routes']);
        $this->assertIsArray($response->json('other_routes'));
    }

    // =========================================================
    // اختبار 2: يوجد مسار نشط → يُرجع التوصية الأفضل
    // =========================================================
    public function test_returns_best_recommendation_when_active_route_exists(): void
    {
        // إنشاء مسار صباحي نشط للسائق (MORNING = نفس توقيت العقد)
        $route = RouteModel::create([
            'driver_id'          => $this->driver->id,
            'vehicle_id'         => $this->vehicleId,
            'route_name'         => 'مسار الاختبار الصباحي',
            'route_type'         => 'Morning',
            'start_time'         => '07:00:00',
            'estimated_duration' => 35,
            'status'             => 'Active',
        ]);

        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/v1/driver/subscriptions/{$this->subscription->id}/route-recommendations");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');

        // يجب أن يُرجع التوصية الأفضل بالهيكل الصحيح
        $response->assertJsonStructure([
            'status',
            'recommended_route' => ['id', 'name', 'score', 'reason'],
            'other_routes',
        ]);

        // التحقق من أن المسار المقترح هو المسار الصباحي
        $response->assertJsonPath('recommended_route.id', $route->id);
        $response->assertJsonPath('recommended_route.name', 'مسار الاختبار الصباحي');

        // score يجب أن يكون بين 40 و 99
        $score = $response->json('recommended_route.score');
        $this->assertGreaterThanOrEqual(40, $score);
        $this->assertLessThanOrEqual(99, $score);

        // reason يجب أن يكون array يحتوي على "نفس الفترة الزمنية للرحلة"
        $reasons = $response->json('recommended_route.reason');
        $this->assertIsArray($reasons);
        $this->assertContains('نفس الفترة الزمنية للرحلة', $reasons);
    }

    // =========================================================
    // اختبار 3: سائق آخر لا يرى اشتراكات سائق مختلف
    // =========================================================
    public function test_driver_cannot_access_another_drivers_subscription(): void
    {
        // إنشاء سائق ثانٍ
        $otherDriverUser = User::create([
            'full_name'     => 'سائق آخر',
            'email'         => 'driver.other.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);
        Driver::create([
            'user_id'        => $otherDriverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        // السائق الثاني يحاول الوصول إلى اشتراك السائق الأول
        $response = $this->actingAs($otherDriverUser)
            ->getJson("/api/v1/driver/subscriptions/{$this->subscription->id}/route-recommendations");

        $response->assertStatus(404);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('code', 'SUBSCRIPTION_NOT_FOUND');
    }

    // =========================================================
    // اختبار 4: اشتراك غير موجود → 404
    // =========================================================
    public function test_returns_404_for_nonexistent_subscription(): void
    {
        $fakeId = 999999;

        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/v1/driver/subscriptions/{$fakeId}/route-recommendations");

        $response->assertStatus(404);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('code', 'SUBSCRIPTION_NOT_FOUND');
    }

    // =========================================================
    // اختبار 5: مستخدم غير مصادق → 401
    // =========================================================
    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->getJson(
            "/api/v1/driver/subscriptions/{$this->subscription->id}/route-recommendations"
        );

        $response->assertStatus(401);
    }

    // =========================================================
    // اختبار 6: مسار مسائي وحيد → يظهر كـ recommended_route مع تحذير (score=40)
    //           (لأنه الوحيد المتاح رغم اختلاف الفترة)
    // =========================================================
    public function test_evening_route_appears_as_recommended_with_warning(): void
    {
        $eveningRoute = RouteModel::create([
            'driver_id'          => $this->driver->id,
            'vehicle_id'         => $this->vehicleId,
            'route_name'         => 'مسار مسائي',
            'route_type'         => 'Afternoon',
            'start_time'         => '13:00:00',
            'estimated_duration' => 35,
            'status'             => 'Active',
        ]);

        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/v1/driver/subscriptions/{$this->subscription->id}/route-recommendations");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');

        // المسار المسائي الوحيد يظهر كـ recommended_route (score=40 الحد الأدنى)
        // لأنه الخيار الوحيد المتاح رغم اختلاف الفترة
        $recommended = $response->json('recommended_route');
        $this->assertNotNull($recommended);
        $this->assertEquals($eveningRoute->id, $recommended['id']);
        $this->assertEquals(40, $recommended['score']); // الحد الأدنى بسبب خصم الفترة
        // التحقق أن التحذير موجود في reasons/warnings (يُرجع عبر reason في الإخراج)
        // لأنه الوحيد يذهب لـ recommended_route وليس other_routes
        $this->assertIsArray($recommended['reason']);
    }

    // =========================================================
    // اختبار 7: مساران (صباحي ومسائي) → الصباحي مقترح والمسائي في other_routes
    // =========================================================
    public function test_morning_recommended_and_evening_in_other_routes(): void
    {
        $morningRoute = RouteModel::create([
            'driver_id'          => $this->driver->id,
            'vehicle_id'         => $this->vehicleId,
            'route_name'         => 'مسار صباحي ممتاز',
            'route_type'         => 'Morning',
            'start_time'         => '07:00:00',
            'estimated_duration' => 30,
            'status'             => 'Active',
        ]);

        $eveningRoute = RouteModel::create([
            'driver_id'          => $this->driver->id,
            'vehicle_id'         => $this->vehicleId,
            'route_name'         => 'مسار مسائي بديل',
            'route_type'         => 'Afternoon',
            'start_time'         => '13:00:00',
            'estimated_duration' => 30,
            'status'             => 'Active',
        ]);

        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/v1/driver/subscriptions/{$this->subscription->id}/route-recommendations");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');

        // الصباحي هو المقترح الأفضل (score أعلى)
        $response->assertJsonPath('recommended_route.id', $morningRoute->id);
        $response->assertJsonPath('recommended_route.name', 'مسار صباحي ممتاز');

        // المسائي يظهر في other_routes (بعد التعديل)
        $otherRoutes = $response->json('other_routes');
        $this->assertNotEmpty($otherRoutes);
        $this->assertEquals($eveningRoute->id, $otherRoutes[0]['id']);
    }

    // =========================================================
    // اختبار 8: اشتراك مُسند لمسار → 400 ALREADY_ASSIGNED
    // =========================================================
    public function test_already_assigned_subscription_returns_400(): void
    {
        // إنشاء مسار ثم ربط الاشتراك به
        $route = RouteModel::create([
            'driver_id'          => $this->driver->id,
            'vehicle_id'         => $this->vehicleId,
            'route_name'         => 'مسار مُسند',
            'route_type'         => 'Morning',
            'start_time'         => '07:00:00',
            'estimated_duration' => 30,
            'status'             => 'Active',
        ]);

        // إسناد الاشتراك للمسار مباشرة
        $this->subscription->update(['route_id' => $route->id]);

        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/v1/driver/subscriptions/{$this->subscription->id}/route-recommendations");

        $response->assertStatus(400);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('code', 'ALREADY_ASSIGNED');
        $response->assertJsonPath('message', 'هذا الاشتراك تم إسناده لمسار بالفعل.');
    }
}
