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
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Route as RouteModel;
use App\Models\Shared\RouteStop;
use App\Services\Shared\SubscriptionRequestService;

/**
 * اختبار مزامنة المسار الرئيسي (Master Route / route_stops) عند قبول طلب اشتراك
 * وعند إلغاء اشتراك نشط.
 */
class MasterRouteStopSyncTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;
    protected Child $child;
    protected School $school;
    protected SubscriptionRequest $subscriptionRequest;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->driverUser = User::create([
            'full_name'    => 'سائق مزامنة المسار',
            'email'        => 'driver.sync.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 2,
            'is_active'    => 1,
        ]);

        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
            'current_lat'    => 32.8872,
            'current_lng'    => 13.1932,
        ]);

        DB::table('vehicles')->insert([
            'driver_id'       => $this->driver->id,
            'brand'           => 'تويوتا',
            'model'           => 'هايس',
            'year'            => 2022,
            'color'           => 'أبيض',
            'plate_number'    => 'SYNC-' . rand(1000, 9999),
            'capacity_manual' => 10,
            'capacity_ai'     => 10,
            'status'          => 'Active',
            'deleted_at'      => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->parentUser = User::create([
            'full_name'    => 'ولي أمر مزامنة المسار',
            'email'        => 'parent.sync.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 3,
            'is_active'    => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        $this->school = School::create([
            'name'    => 'مدرسة مزامنة المسار',
            'address' => 'شارع الاختبار',
            'lat'     => 32.9000,
            'lng'     => 13.2000,
            'status'  => 'active',
        ]);

        $this->child = Child::create([
            'parent_id'            => $this->parent->id,
            'school_id'            => $this->school->id,
            'full_name'            => 'طفل مزامنة المسار',
            'birth_date'           => '2018-05-10',
            'gender'               => 'male',
            'grade'                => 1,
            'notification_radius' => 500,
        ]);

        $addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'منزل ولي الأمر',
            'lat'        => 32.88,
            'lng'        => 13.19,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->subscriptionRequest = SubscriptionRequest::create([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'school_id'         => $this->school->id,
            'subscription_type' => 'monthly',
            'direction'         => 'both',
            'timing'            => 'MORNING',
            'start_date'        => now()->addDay()->format('Y-m-d'),
            'end_date'          => now()->addMonths(1)->format('Y-m-d'),
            'days_count'        => 22,
            'total_price'       => 200.00,
            'pickup_time'       => '07:00:00',
            'dropoff_time'      => '14:00:00',
            'max_waiting_time'  => 15,
            'status'            => SubscriptionRequest::STATUS_PENDING,
            'children_count'    => 1,
        ]);

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

    /**
     * ينشئ طفلاً جديداً وطلب اشتراك جديداً (بحالة pending) لنفس السائق/الأب في هذا الاختبار،
     * بفترة/اتجاه محددين، ويُرجعهما معاً [child, request].
     */
    private function makeChildAndRequest(string $direction, string $timing, string $label): array
    {
        $child = Child::create([
            'parent_id'            => $this->parent->id,
            'school_id'            => $this->school->id,
            'full_name'            => 'طفل مزامنة المسار ' . $label,
            'birth_date'           => '2019-01-01',
            'gender'               => 'female',
            'grade'                => 1,
            'notification_radius' => 500,
        ]);

        $addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'منزل ' . $label,
            'lat'        => 32.885,
            'lng'        => 13.195,
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = SubscriptionRequest::create([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'school_id'         => $this->school->id,
            'subscription_type' => 'monthly',
            'direction'         => $direction,
            'timing'            => $timing,
            'start_date'        => now()->addDay()->format('Y-m-d'),
            'end_date'          => now()->addMonths(1)->format('Y-m-d'),
            'days_count'        => 22,
            'total_price'       => 200.00,
            'pickup_time'       => '07:00:00',
            'dropoff_time'      => '14:00:00',
            'max_waiting_time'  => 15,
            'status'            => SubscriptionRequest::STATUS_PENDING,
            'children_count'    => 1,
        ]);

        DB::table('request_children')->insert([
            'request_id'         => $request->id,
            'child_id'           => $child->id,
            'pickup_address_id'  => $addressId,
            'dropoff_address_id' => $this->school->id,
            'home_lat'           => 32.885,
            'home_lng'           => 13.195,
            'home_label'         => 'المنزل ' . $label,
            'school_lat'         => 32.90,
            'school_lng'         => 13.20,
            'school_label'       => 'المدرسة',
            'price_per_child'    => 200.00,
        ]);

        return [$child, $request];
    }

    // =========================================================
    // اختبار: طلبان منفصلان بنفس السائق، بنفس الفترة ونفس الاتجاه المفرد
    // (صباحية - ذهاب فقط)، يجب أن يشتركا في نفس المسار الثابت.
    // =========================================================
    public function test_two_requests_with_same_single_direction_and_period_share_one_route(): void
    {
        $service = app(SubscriptionRequestService::class);

        [$childA, $requestA] = $this->makeChildAndRequest('go', 'MORNING', 'أ');
        $service->updateStatus($requestA, 'accepted');

        [$childB, $requestB] = $this->makeChildAndRequest('go', 'MORNING', 'ب');
        $service->updateStatus($requestB, 'accepted');

        $this->assertEquals(
            1,
            RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_go')->count(),
            'تم إنشاء أكثر من مسار [صباحية - ذهاب] رغم أن الطلبين لهما نفس الفترة ونفس الاتجاه.'
        );

        $route = RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_go')->first();

        $this->assertDatabaseHas('route_stops', ['route_id' => $route->id, 'stop_type' => 'home', 'child_id' => $childA->id]);
        $this->assertDatabaseHas('route_stops', ['route_id' => $route->id, 'stop_type' => 'home', 'child_id' => $childB->id]);

        $this->assertDatabaseHas('active_subscriptions', ['child_id' => $childA->id, 'route_id' => $route->id]);
        $this->assertDatabaseHas('active_subscriptions', ['child_id' => $childB->id, 'route_id' => $route->id]);
    }

    // =========================================================
    // اختبار: طلب أول باتجاه واحد (ذهاب فقط) ثم طلب ثانٍ لطفل آخر باتجاهين (ذهاب وإياب)
    // لنفس السائق ونفس الفترة الصباحية. يجب أن يُعاد استخدام مسار [ذهاب] الموجود
    // وأن يُنشأ مسار [إياب] جديد لمرة واحدة فقط، دون تكرار أيّ منهما لاحقاً.
    // =========================================================
    public function test_mixed_direction_requests_reuse_shared_slot_and_create_missing_slot_once(): void
    {
        $service = app(SubscriptionRequestService::class);

        [$childA, $requestA] = $this->makeChildAndRequest('go', 'MORNING', 'أ');
        $service->updateStatus($requestA, 'accepted');
        $goRouteAfterA = RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_go')->first();
        $this->assertNotNull($goRouteAfterA);
        $this->assertNull(RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_return')->first());

        [$childB, $requestB] = $this->makeChildAndRequest('both', 'MORNING', 'ب');
        $service->updateStatus($requestB, 'accepted');

        [$childC, $requestC] = $this->makeChildAndRequest('return', 'MORNING', 'ج');
        $service->updateStatus($requestC, 'accepted');

        // ما زال هناك مسار واحد فقط [ذهاب] ومسار واحد فقط [إياب] لهذا السائق
        $this->assertEquals(1, RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_go')->count());
        $this->assertEquals(1, RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_return')->count());

        $goRouteFinal     = RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_go')->first();
        $returnRouteFinal = RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_return')->first();

        // مسار [ذهاب] الذي أُنشئ مع الطفل الأول هو نفسه المُستخدم لاحقاً مع الطفل الثاني
        $this->assertEquals($goRouteAfterA->id, $goRouteFinal->id);

        // الأطفال الثلاثة موزعون بشكل صحيح: أ+ب على مسار الذهاب، ب+ج على مسار الإياب
        $this->assertDatabaseHas('route_stops', ['route_id' => $goRouteFinal->id, 'stop_type' => 'home', 'child_id' => $childA->id]);
        $this->assertDatabaseHas('route_stops', ['route_id' => $goRouteFinal->id, 'stop_type' => 'home', 'child_id' => $childB->id]);
        $this->assertDatabaseMissing('route_stops', ['route_id' => $goRouteFinal->id, 'stop_type' => 'home', 'child_id' => $childC->id]);

        $this->assertDatabaseHas('route_stops', ['route_id' => $returnRouteFinal->id, 'stop_type' => 'home', 'child_id' => $childB->id]);
        $this->assertDatabaseHas('route_stops', ['route_id' => $returnRouteFinal->id, 'stop_type' => 'home', 'child_id' => $childC->id]);
        $this->assertDatabaseMissing('route_stops', ['route_id' => $returnRouteFinal->id, 'stop_type' => 'home', 'child_id' => $childA->id]);
    }

    public function test_acceptance_creates_route_stops_for_home_and_school(): void
    {
        $service = app(SubscriptionRequestService::class);
        $service->updateStatus($this->subscriptionRequest, 'accepted');

        $route = RouteModel::where('driver_id', $this->driver->id)
            ->where('shift_slot', 'morning_go')
            ->first();

        $this->assertNotNull($route, 'لم يتم إنشاء مسار morning_go الرئيسي.');

        $this->assertDatabaseHas('route_stops', [
            'route_id'  => $route->id,
            'stop_type' => 'home',
            'child_id'  => $this->child->id,
        ]);

        $this->assertDatabaseHas('route_stops', [
            'route_id'  => $route->id,
            'stop_type' => 'school',
            'school_id' => $this->school->id,
        ]);
    }

    public function test_direction_both_creates_second_slot_route_with_same_child(): void
    {
        $service = app(SubscriptionRequestService::class);
        $service->updateStatus($this->subscriptionRequest, 'accepted');

        $goRoute = RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_go')->first();
        $returnRoute = RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_return')->first();

        $this->assertNotNull($goRoute);
        $this->assertNotNull($returnRoute);
        $this->assertNotEquals($goRoute->id, $returnRoute->id);

        $this->assertDatabaseHas('route_stops', [
            'route_id'  => $returnRoute->id,
            'stop_type' => 'home',
            'child_id'  => $this->child->id,
        ]);
    }

    // =========================================================
    // اختبار: قبول طلب اشتراك ثانٍ لنفس السائق ونفس الفترة/الاتجاه
    // يجب أن يُضيف الطفل الجديد إلى المسار الرئيسي الموجود بدلاً من
    // إنشاء مسار جديد في كل مرة (هذا هو السلوك المطلوب إصلاحه).
    // =========================================================
    public function test_second_subscription_for_same_driver_and_slot_reuses_existing_route(): void
    {
        $service = app(SubscriptionRequestService::class);

        // --- قبول الطلب الأول (طفل 1) ---
        $service->updateStatus($this->subscriptionRequest, 'accepted');

        $goRouteAfterFirst     = RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_go')->first();
        $returnRouteAfterFirst = RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_return')->first();
        $this->assertNotNull($goRouteAfterFirst);
        $this->assertNotNull($returnRouteAfterFirst);

        // --- إنشاء طفل ثانٍ وطلب اشتراك ثانٍ لنفس السائق بنفس الفترة والاتجاه (صباحية - ذهاب وإياب) ---
        $secondChild = Child::create([
            'parent_id'            => $this->parent->id,
            'school_id'            => $this->school->id,
            'full_name'            => 'طفل مزامنة المسار الثاني',
            'birth_date'           => '2019-01-01',
            'gender'               => 'female',
            'grade'                => 1,
            'notification_radius' => 500,
        ]);

        $secondAddressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'منزل ولي الأمر الثاني',
            'lat'        => 32.885,
            'lng'        => 13.195,
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondRequest = SubscriptionRequest::create([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'school_id'         => $this->school->id,
            'subscription_type' => 'monthly',
            'direction'         => 'both',
            'timing'            => 'MORNING',
            'start_date'        => now()->addDay()->format('Y-m-d'),
            'end_date'          => now()->addMonths(1)->format('Y-m-d'),
            'days_count'        => 22,
            'total_price'       => 200.00,
            'pickup_time'       => '07:00:00',
            'dropoff_time'      => '14:00:00',
            'max_waiting_time'  => 15,
            'status'            => SubscriptionRequest::STATUS_PENDING,
            'children_count'    => 1,
        ]);

        DB::table('request_children')->insert([
            'request_id'         => $secondRequest->id,
            'child_id'           => $secondChild->id,
            'pickup_address_id'  => $secondAddressId,
            'dropoff_address_id' => $this->school->id,
            'home_lat'           => 32.885,
            'home_lng'           => 13.195,
            'home_label'         => 'المنزل الثاني',
            'school_lat'         => 32.90,
            'school_lng'         => 13.20,
            'school_label'       => 'المدرسة',
            'price_per_child'    => 200.00,
        ]);

        // --- قبول الطلب الثاني (طفل 2) ---
        $service->updateStatus($secondRequest, 'accepted');

        // 1. لا يجب إنشاء أي مسار جديد: يبقى مسار واحد فقط لكل slot لهذا السائق
        $this->assertEquals(
            1,
            RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_go')->count(),
            'تم إنشاء أكثر من مسار [صباحية - ذهاب] لنفس السائق بدلاً من إعادة استخدام المسار الموجود.'
        );
        $this->assertEquals(
            1,
            RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_return')->count(),
            'تم إنشاء أكثر من مسار [صباحية - إياب] لنفس السائق بدلاً من إعادة استخدام المسار الموجود.'
        );

        // 2. نفس سجلات المسار (بنفس الـ id) هي التي أُعيد استخدامها
        $goRouteAfterSecond     = RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_go')->first();
        $returnRouteAfterSecond = RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_return')->first();
        $this->assertEquals($goRouteAfterFirst->id, $goRouteAfterSecond->id);
        $this->assertEquals($returnRouteAfterFirst->id, $returnRouteAfterSecond->id);

        // 3. الطفلان مضافان معاً على نفس المسارين (ذهاب وإياب)
        $this->assertDatabaseHas('route_stops', [
            'route_id'  => $goRouteAfterSecond->id,
            'stop_type' => 'home',
            'child_id'  => $this->child->id,
        ]);
        $this->assertDatabaseHas('route_stops', [
            'route_id'  => $goRouteAfterSecond->id,
            'stop_type' => 'home',
            'child_id'  => $secondChild->id,
        ]);
        $this->assertDatabaseHas('route_stops', [
            'route_id'  => $returnRouteAfterSecond->id,
            'stop_type' => 'home',
            'child_id'  => $secondChild->id,
        ]);

        // 4. الاشتراكان النشطان الخاصان بالطفلين مرتبطان بنفس مسار الـ slot الأساسي (morning_go)
        $this->assertDatabaseHas('active_subscriptions', [
            'child_id' => $this->child->id,
            'route_id' => $goRouteAfterSecond->id,
        ]);
        $this->assertDatabaseHas('active_subscriptions', [
            'child_id' => $secondChild->id,
            'route_id' => $goRouteAfterSecond->id,
        ]);
    }

    public function test_cancelling_active_subscription_removes_its_route_stops(): void
    {
        $service = app(SubscriptionRequestService::class);
        $service->updateStatus($this->subscriptionRequest, 'accepted');

        $activeSub = ActiveSubscription::where('driver_id', $this->driver->id)
            ->where('child_id', $this->child->id)
            ->first();
        $this->assertNotNull($activeSub);

        $route = RouteModel::where('driver_id', $this->driver->id)->where('shift_slot', 'morning_go')->first();
        $this->assertDatabaseHas('route_stops', ['route_id' => $route->id, 'child_id' => $this->child->id]);

        $service->updateActiveSubscriptionStatus($activeSub->id, 'cancelled');

        $this->assertDatabaseMissing('route_stops', [
            'route_id'  => $route->id,
            'stop_type' => 'home',
            'child_id'  => $this->child->id,
        ]);

        // كان الطفل الوحيد المرتبط بهذه المدرسة على هذا المسار → يجب حذف محطة المدرسة أيضاً
        $this->assertDatabaseMissing('route_stops', [
            'route_id'  => $route->id,
            'stop_type' => 'school',
            'school_id' => $this->school->id,
        ]);

        $route->refresh();
        $this->assertEquals('Inactive', $route->status);
    }
}
