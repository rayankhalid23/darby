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
