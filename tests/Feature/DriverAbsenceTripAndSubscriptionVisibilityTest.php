<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\DriverSeatSlot;
use App\Models\Driver\Vehicle;
use App\Models\Driver\DriverAbsence;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Parent\School;
use App\Models\Parent\Address;
use App\Models\Shared\Zone;
use App\Models\Shared\SubMunicipality;
use App\Models\Shared\Municipality;
use App\Models\Shared\PricingSetting;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Route;
use App\Services\Trip\DailyTripGenerationService;
use App\Services\Trip\ParentTripService;

class DriverAbsenceTripAndSubscriptionVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $parentUser;
    protected ParentModel $parent;
    protected User $driverUser1;
    protected Driver $driver1;
    protected User $driverUser2;
    protected Driver $driver2;
    protected Child $child;
    protected School $school;
    protected Address $homeAddress;
    protected Zone $zone;
    protected Route $routeDriver1;
    protected Route $routeDriver2;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        PricingSetting::firstOrCreate([], [
            'price_per_km_ac'              => 2.50,
            'price_per_km_non_ac'          => 2.00,
            'discount_one_child'           => 0.00,
            'discount_two_children'        => 10.00,
            'discount_three_plus_children' => 15.00,
            'platform_commission_rate'     => 8.00,
        ]);

        $municipality = Municipality::firstOrCreate(['name' => 'طرابلس الكبرى']);
        $subMuni = SubMunicipality::firstOrCreate(['municipality_id' => $municipality->id, 'name' => 'النوفليين']);
        $this->zone = Zone::firstOrCreate(['sub_municipality_id' => $subMuni->id, 'name' => 'شارع بن عاشور']);

        $this->school = School::create([
            'name'    => 'مدرسة النوفليين المركزية',
            'zone_id' => $this->zone->id,
            'lat'     => 32.88500000,
            'lng'     => 13.18500000,
            'address' => 'طرابلس - النوفليين',
            'status'  => 'active',
        ]);

        // ولي الأمر
        $this->parentUser = User::create([
            'full_name'     => 'فوزي رمضان السويحلي',
            'email'         => 'parent.abs.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);
        $this->parent->deposit(500000);

        $this->homeAddress = Address::create([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'منزل العائلة',
            'lat'        => 32.88000000,
            'lng'        => 13.18000000,
        ]);

        $this->child = Child::create([
            'parent_id'  => $this->parent->id,
            'school_id'  => $this->school->id,
            'address_id' => $this->homeAddress->id,
            'full_name'  => 'أحمد فوزي السويحلي',
            'gender'     => 'male',
            'grade'      => '4',
            'birth_date' => '2016-04-12',
        ]);

        // السائق الأساسي (driver 1)
        $this->driverUser1 = User::create([
            'full_name'     => 'السائق الأساسي عثمان',
            'email'         => 'driver1.abs.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);

        $this->driver1 = Driver::create([
            'user_id'                 => $this->driverUser1->id,
            'national_id'             => 'NAT' . rand(100000, 999999),
            'license_number'          => 'LIC' . rand(100000, 999999),
            'license_expiry'          => now()->addYears(2)->toDateString(),
            'status'                  => 'Approved',
            'gender'                  => 'male',
            'accepted_gender'         => 'both',
            'subscription_type'       => 'both',
            'shift_morning_both'      => true,
            'shift_morning_one_way'   => true,
            'shift_evening_both'      => true,
            'shift_evening_one_way'   => true,
            'morning_go'              => 1,
            'morning_return'          => 1,
            'evening_go'              => 1,
            'evening_return'          => 1,
            'current_lat'             => 32.86000000,
            'current_lng'             => 13.16000000,
            'school_stages'           => '["primary"]',
        ]);

        $v1 = Vehicle::create([
            'driver_id'       => $this->driver1->id,
            'brand'           => 'تويوتا',
            'model'           => 'هاي آس',
            'year'            => 2023,
            'color'           => 'أبيض',
            'type'            => 'Van',
            'plate_number'    => 'LY-' . rand(1000, 9999),
            'capacity_manual' => 10,
            'has_ac'          => 1,
            'status'          => 'Active',
            'is_verified'     => 1,
        ]);

        $this->routeDriver1 = Route::create([
            'driver_id'          => $this->driver1->id,
            'vehicle_id'         => $v1->id,
            'route_name'         => 'مسار صباحي - الأساسي',
            'route_type'         => 'Morning',
            'shift_slot'         => 'morning_go',
            'start_time'         => '07:00:00',
            'status'             => 'Active',
            'total_distance'     => 5.5,
            'estimated_duration' => 20,
        ]);

        // السائق البديل (driver 2)
        $this->driverUser2 = User::create([
            'full_name'     => 'السائق البديل سالم',
            'email'         => 'driver2.abs.' . uniqid() . '@darby.test',
            'phone_number'  => '093' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);

        $this->driver2 = Driver::create([
            'user_id'                 => $this->driverUser2->id,
            'national_id'             => 'NAT' . rand(100000, 999999),
            'license_number'          => 'LIC' . rand(100000, 999999),
            'license_expiry'          => now()->addYears(2)->toDateString(),
            'status'                  => 'Approved',
            'gender'                  => 'male',
            'accepted_gender'         => 'both',
            'subscription_type'       => 'both',
            'shift_morning_both'      => true,
            'shift_morning_one_way'   => true,
            'shift_evening_both'      => true,
            'shift_evening_one_way'   => true,
            'morning_go'              => 1,
            'morning_return'          => 1,
            'evening_go'              => 1,
            'evening_return'          => 1,
            'current_lat'             => 32.86000000,
            'current_lng'             => 13.16000000,
            'school_stages'           => '["primary"]',
        ]);

        $v2 = Vehicle::create([
            'driver_id'       => $this->driver2->id,
            'brand'           => 'هيونداي',
            'model'           => 'H1',
            'year'            => 2022,
            'color'           => 'فضي',
            'type'            => 'Van',
            'plate_number'    => 'LY-' . rand(1000, 9999),
            'capacity_manual' => 10,
            'has_ac'          => 1,
            'status'          => 'Active',
            'is_verified'     => 1,
        ]);

        $this->routeDriver2 = Route::create([
            'driver_id'          => $this->driver2->id,
            'vehicle_id'         => $v2->id,
            'route_name'         => 'مسار صباحي - البديل',
            'route_type'         => 'Morning',
            'shift_slot'         => 'morning_go',
            'start_time'         => '07:00:00',
            'status'             => 'Active',
            'total_distance'     => 5.5,
            'estimated_duration' => 20,
        ]);
    }

    /**
     * اختبار: السائق الأساسي في يوم غيابه لا تظهر رحلاته وتظهر حالة اشتراكه متوقفة مؤقتاً،
     * وعندما ينتهي الغياب (اليوم التالي) تعود الرحلات والاشتراك للظهور بشكل طبيعي.
     */
    public function test_primary_driver_trips_hidden_on_absence_day_and_restored_after(): void
    {
        $today = Carbon::today()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();

        // 1. اشتراك نشط للطفل مع السائق الأساسي يغطي اليوم وغداً
        $req1 = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver1->id,
            'status'                      => SubscriptionRequest::STATUS_ACCEPTED,
            'total_price'                 => 100.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 100.00,
        ]);
        $req1->children()->attach($this->child->id, [
            'subscription_type'           => 'multi_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => $today,
            'end_date'                    => $tomorrow,
            'working_days_count'          => 2,
            'price_per_child'             => 100.00,
            'trip_price'                  => 100.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 100.00,
            'driver_net_price'            => 92.00,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);
        $activeSub1 = ActiveSubscription::create([
            'subscription_request_id' => $req1->id,
            'child_id'                => $this->child->id,
            'driver_id'               => $this->driver1->id,
            'route_id'                => $this->routeDriver1->id,
            'parent_id'               => $this->parentUser->id,
            'status'                  => 'active',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
        ]);

        // 2. السائق الأساسي مسجل غياباً اليوم فقط ($today)
        DriverAbsence::create(['driver_id' => $this->driver1->id, 'absence_date' => $today]);

        // 3. التحقق من DailyTripGenerationService في يوم الغياب:
        $generationService = app(DailyTripGenerationService::class);
        $tripToday = $generationService->generateForRoute($this->routeDriver1, Carbon::today());
        $this->assertNull($tripToday, 'يجب أن لا يتم توليد رحلة للسائق الأساسي في يوم غيابه');

        // 4. التحقق من رحلات السائق اليومية اليوم (todayTrips):
        $responseDriver = $this->actingAs($this->driverUser1, 'sanctum')
            ->getJson('/api/v1/driver/trips/today');
        $responseDriver->assertStatus(200);
        $this->assertEmpty($responseDriver->json('data'), 'رحلات السائق الأساسي يجب أن تكون فارغة في يوم غيابه');

        // 5. التحقق من رحلات ولي الأمر القادمة (upcoming trips) اليوم:
        $parentTripService = app(ParentTripService::class);
        $upcomingTrips = $parentTripService->getUpcomingTrips($this->parentUser->id);
        $this->assertEmpty($upcomingTrips, 'رحلات السائق الأساسي القادمة يجب أن لا تظهر لولي الأمر في يوم غياب السائق');

        // 6. التحقق من حالة الاشتراك (resolveState) في يوم الغياب:
        $stateOnAbsenceDay = $req1->resolveState($this->child, $activeSub1);
        $this->assertEquals('driver_absent', $stateOnAbsenceDay['state']);
        $this->assertEquals('غياب السائق (متوقف مؤقتاً)', $stateOnAbsenceDay['state_label']);
        $this->assertFalse($stateOnAbsenceDay['is_active']);

        // 7. التحقق بعد انتهاء الغياب (اليوم التالي $tomorrow):
        $tripTomorrow = $generationService->generateForRoute($this->routeDriver1, Carbon::tomorrow());
        $this->assertNotNull($tripTomorrow, 'يجب أن يتم توليد رحلة السائق الأساسي بشكل طبيعي بعد انتهاء الغياب');

        // عند انتهاء الغياب تعود حالة الاشتراك إلى ساري ومفعل (active)
        DriverAbsence::where('driver_id', $this->driver1->id)->whereDate('absence_date', $today)->delete();
        $stateAfterAbsence = $req1->resolveState($this->child, $activeSub1);
        $this->assertEquals('active', $stateAfterAbsence['state']);
        $this->assertEquals('ساري ومفعل', $stateAfterAbsence['state_label']);
        $this->assertTrue($stateAfterAbsence['is_active']);
    }

    /**
     * اختبار: السائق البديل تظهر رحلاته واشتراكه في الأيام التي اشترك فيها ولي الأمر معه.
     */
    public function test_alternative_driver_trips_and_subscription_appear_on_subscribed_days(): void
    {
        $today = Carbon::today()->toDateString();

        // 1. السائق الأساسي مسجل غياباً اليوم
        DriverAbsence::create(['driver_id' => $this->driver1->id, 'absence_date' => $today]);

        // 2. اشتراك بديل نشط للطفل مع السائق البديل (driver 2) لليوم
        $req2 = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver2->id,
            'status'                      => SubscriptionRequest::STATUS_ACCEPTED,
            'total_price'                 => 50.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 50.00,
        ]);
        $req2->children()->attach($this->child->id, [
            'subscription_type'           => 'single_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => $today,
            'end_date'                    => $today,
            'working_days_count'          => 1,
            'price_per_child'             => 50.00,
            'trip_price'                  => 50.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 50.00,
            'driver_net_price'            => 46.00,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);
        $activeSub2 = ActiveSubscription::create([
            'subscription_request_id' => $req2->id,
            'child_id'                => $this->child->id,
            'driver_id'               => $this->driver2->id,
            'route_id'                => $this->routeDriver2->id,
            'parent_id'               => $this->parentUser->id,
            'status'                  => 'active',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
        ]);

        // 3. التحقق من حالة اشتراك السائق البديل: نشط وساري
        $state = $req2->resolveState($this->child, $activeSub2);
        $this->assertEquals('active', $state['state']);
        $this->assertEquals('ساري ومفعل', $state['state_label']);
        $this->assertTrue($state['is_active']);

        // 4. التحقق من توليد رحلة السائق البديل اليوم:
        $generationService = app(DailyTripGenerationService::class);
        $tripAlternative = $generationService->generateForRoute($this->routeDriver2, Carbon::today());
        $this->assertNotNull($tripAlternative, 'يجب أن يتم توليد رحلة السائق البديل اليوم بنجاح');

        // 5. التحقق من ظهور رحلات اليوم للسائق البديل (todayTrips):
        $responseDriver = $this->actingAs($this->driverUser2, 'sanctum')
            ->getJson('/api/v1/driver/trips/today');
        $responseDriver->assertStatus(200);
        $this->assertNotEmpty($responseDriver->json('data'), 'رحلات السائق البديل يجب أن تظهر اليوم في قائمة رحلاته');

        // 6. التحقق من ظهور الرحلات القادمة لولي الأمر مع السائق البديل:
        $parentTripService = app(ParentTripService::class);
        $upcoming = $parentTripService->getUpcomingTrips($this->parentUser->id);
        $this->assertNotEmpty($upcoming, 'رحلات السائق البديل يجب أن تظهر في الرحلات القادمة لولي الأمر');
        $driverNames = collect($upcoming)->pluck('driver.name')->toArray();
        $this->assertContains($this->driverUser2->full_name, $driverNames);
    }
}
