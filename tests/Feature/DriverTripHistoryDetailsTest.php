<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Parent\School;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Trip;
use App\Models\Shared\TripStop;
use App\Models\Shared\TripEvent;
use App\Models\Shared\Route;
use App\Models\Shared\Municipality;
use App\Models\Shared\SubMunicipality;
use App\Models\Shared\Zone;

class DriverTripHistoryDetailsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;
    protected School $school;
    protected Route $route;
    protected Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $municipality = Municipality::firstOrCreate(['name' => 'طرابلس المركز']);
        $subMuni = SubMunicipality::firstOrCreate(['municipality_id' => $municipality->id, 'name' => 'الظهرة']);
        $zone = Zone::firstOrCreate(['sub_municipality_id' => $subMuni->id, 'name' => 'وسط المدينة']);

        $this->school = School::firstOrCreate(
            ['name' => 'مدرسة طرابلس المركزية'],
            ['zone_id' => $zone->id, 'lat' => 32.8900, 'lng' => 13.1900]
        );

        $this->driverUser = User::create([
            'full_name'     => 'أحمد السائق',
            'email'         => 'driver.history.test@example.com',
            'phone_number'  => '0911002233',
            'role_id'       => 2,
            'password_hash' => Hash::make('password123'),
        ]);

        $this->driver = Driver::create([
            'user_id'                 => $this->driverUser->id,
            'approval_status'         => 'approved',
            'identity_card_number'    => 'ID-DRV-TEST',
            'driving_license_number'  => 'LIC-DRV-TEST',
            'shift_slots'             => ['morning_go_school'],
            'current_lat'             => 32.8800,
            'current_lng'             => 13.1800,
        ]);

        $this->parentUser = User::create([
            'full_name'     => 'محمد ولي الأمر',
            'email'         => 'parent.history.test@example.com',
            'phone_number'  => '0922003344',
            'role_id'       => 3,
            'password_hash' => Hash::make('password123'),
        ]);

        $vehicle = \App\Models\Driver\Vehicle::create([
            'driver_id'       => $this->driver->id,
            'brand'           => 'تويوتا',
            'model'           => 'هاي آس',
            'year'            => 2023,
            'plate_number'    => 'TRIP-1234',
            'type'            => 'van',
            'capacity_manual' => 14,
            'color'           => 'أبيض',
            'has_ac'          => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id' => $this->parentUser->id,
        ]);

        $this->route = Route::create([
            'driver_id'          => $this->driver->id,
            'vehicle_id'         => $vehicle->id,
            'route_name'         => 'مسار طرابلس التعليمي',
            'status'             => 'Active',
            'route_type'         => 'Morning',
            'shift_slot'         => 'morning_go',
            'start_time'         => '07:00:00',
            'estimated_duration' => 45,
            'total_distance'     => 15.5,
        ]);

        $this->trip = Trip::create([
            'driver_id'         => $this->driver->id,
            'route_id'          => $this->route->id,
            'trip_date'         => Carbon::today()->toDateString(),
            'trip_type'         => 'Morning',
            'shift_slot'        => 'morning_go',
            'started_at'        => Carbon::today()->setTime(7, 15, 0),
            'completed_at'      => Carbon::today()->setTime(7, 55, 0),
            'actual_start_time' => Carbon::today()->setTime(7, 15, 0),
            'status'            => 'completed',
        ]);
    }

    public function test_driver_trip_history_details_returns_reasons_actual_times_and_summary(): void
    {
        // 1. إنشاء 3 طلاب:
        // - الطالب 1: تم ركوبه وتسليمه (Completed / Picked up)
        // - الطالب 2: تم تخطيه بسبب إغلاق الطريق (Skipped with reason)
        // - الطالب 3: غائب مع ذكر السبب (Absent with reason)

        $child1 = Child::create([
            'parent_id'       => $this->parent->id,
            'full_name'       => 'علي محمد',
            'school_id'       => $this->school->id,
            'birth_date'      => '2016-03-20',
            'gender'          => 'male',
            'grade'           => 3,
            'qr_code_token'   => 'QR-CHILD-1',
        ]);

        $child2 = Child::create([
            'parent_id'       => $this->parent->id,
            'full_name'       => 'سارة محمد',
            'school_id'       => $this->school->id,
            'birth_date'      => '2017-05-15',
            'gender'          => 'female',
            'grade'           => 2,
            'qr_code_token'   => 'QR-CHILD-2',
        ]);

        $child3 = Child::create([
            'parent_id'       => $this->parent->id,
            'full_name'       => 'عمر محمد',
            'school_id'       => $this->school->id,
            'birth_date'      => '2015-08-10',
            'gender'          => 'male',
            'grade'           => 4,
            'qr_code_token'   => 'QR-CHILD-3',
        ]);

        $req = \App\Models\Shared\SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'status'                      => 'accepted',
            'total_price'                 => 540.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 540.00,
        ]);

        $sub1 = ActiveSubscription::create([
            'subscription_request_id' => $req->id,
            'driver_id'   => $this->driver->id,
            'parent_id'   => $this->parentUser->id,
            'child_id'    => $child1->id,
            'route_id'    => $this->route->id,
            'school_id'   => $this->school->id,
            'status'      => 'active',
            'pickup_lat'  => 32.8810,
            'pickup_lng'  => 13.1810,
            'dropoff_lat' => 32.8900,
            'dropoff_lng' => 13.1900,
        ]);

        $sub2 = ActiveSubscription::create([
            'subscription_request_id' => $req->id,
            'driver_id'   => $this->driver->id,
            'parent_id'   => $this->parentUser->id,
            'child_id'    => $child2->id,
            'route_id'    => $this->route->id,
            'school_id'   => $this->school->id,
            'status'      => 'active',
            'pickup_lat'  => 32.8820,
            'pickup_lng'  => 13.1820,
            'dropoff_lat' => 32.8900,
            'dropoff_lng' => 13.1900,
        ]);

        $sub3 = ActiveSubscription::create([
            'subscription_request_id' => $req->id,
            'driver_id'   => $this->driver->id,
            'parent_id'   => $this->parentUser->id,
            'child_id'    => $child3->id,
            'route_id'    => $this->route->id,
            'school_id'   => $this->school->id,
            'status'      => 'active',
            'pickup_lat'  => 32.8830,
            'pickup_lng'  => 13.1830,
            'dropoff_lat' => 32.8900,
            'dropoff_lng' => 13.1900,
        ]);

        // Stops
        TripStop::create([
            'trip_id'        => $this->trip->id,
            'child_id'       => $child1->id,
            'stop_type'      => 'home',
            'status'         => TripStop::STATUS_DROPPED_OFF_SCHOOL,
            'lat'            => 32.8810,
            'lng'            => 13.1810,
            'sequence_order' => 1,
        ]);

        TripStop::create([
            'trip_id'        => $this->trip->id,
            'child_id'       => $child2->id,
            'stop_type'      => 'home',
            'status'         => TripStop::STATUS_SKIPPED_UNRESPONSIVE,
            'reason'         => 'الشارع مغلق بسبب أعمال صيانة',
            'lat'            => 32.8820,
            'lng'            => 13.1820,
            'sequence_order' => 2,
        ]);

        TripStop::create([
            'trip_id'        => $this->trip->id,
            'child_id'       => $child3->id,
            'stop_type'      => 'home',
            'status'         => TripStop::STATUS_ABSENT_LATE,
            'reason'         => 'تم الإبلاغ عن الغياب من ولي الأمر',
            'lat'            => 32.8830,
            'lng'            => 13.1830,
            'sequence_order' => 3,
        ]);

        // Events
        TripEvent::create([
            'trip_id'         => $this->trip->id,
            'child_id'        => $child1->id,
            'subscription_id' => $sub1->id,
            'action_type'     => 'picked_up',
            'trip_type'       => 'ذهاب',
            'scanned_at'      => Carbon::today()->setTime(7, 20, 0),
            'location_lat'    => 32.8810,
            'location_lng'    => 13.1810,
            'trip_cost'       => 0,
        ]);

        TripEvent::create([
            'trip_id'         => $this->trip->id,
            'child_id'        => $child1->id,
            'subscription_id' => $sub1->id,
            'action_type'     => 'dropped_off',
            'trip_type'       => 'ذهاب',
            'scanned_at'      => Carbon::today()->setTime(7, 45, 0),
            'location_lat'    => 32.8900,
            'location_lng'    => 13.1900,
            'trip_cost'       => 0,
        ]);

        TripEvent::create([
            'trip_id'         => $this->trip->id,
            'child_id'        => $child2->id,
            'subscription_id' => $sub2->id,
            'action_type'     => 'skipped',
            'trip_type'       => 'ذهاب',
            'reason'          => 'الشارع مغلق بسبب أعمال صيانة',
            'scanned_at'      => Carbon::today()->setTime(7, 25, 0),
            'location_lat'    => 32.8820,
            'location_lng'    => 13.1820,
            'trip_cost'       => 0,
        ]);

        TripEvent::create([
            'trip_id'         => $this->trip->id,
            'child_id'        => $child3->id,
            'subscription_id' => $sub3->id,
            'action_type'     => 'absent',
            'trip_type'       => 'ذهاب',
            'reason'          => 'تم الإبلاغ عن الغياب من ولي الأمر',
            'scanned_at'      => Carbon::today()->setTime(7, 30, 0),
            'location_lat'    => 32.8830,
            'location_lng'    => 13.1830,
            'trip_cost'       => 0,
        ]);

        // طلب الاندبوينت عبر /api/v1/driver/trips/history/{tripId}
        $response = $this->actingAs($this->driverUser, 'sanctum')
            ->getJson("/api/v1/driver/trips/history/{$this->trip->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'trip_id'             => $this->trip->id,
                    'status'              => 'completed',
                    'actual_started_at'   => Carbon::today()->setTime(7, 15, 0)->format('Y-m-d H:i:s'),
                    'actual_completed_at' => Carbon::today()->setTime(7, 55, 0)->format('Y-m-d H:i:s'),
                    'duration'            => 40,
                    'summary'             => [
                        'total_students' => 3,
                        'picked_up'      => 1,
                        'absent'         => 2,
                    ],
                ]
            ]);

        $responseData = $response->json('data');

        // فحص بيانات الأطفال وأسباب التخطي والغياب
        $children = collect($responseData['children']);

        $c1 = $children->firstWhere('child_id', $child1->id);
        $this->assertEquals('completed', $c1['status']);
        $this->assertNull($c1['reason']);

        $c2 = $children->firstWhere('child_id', $child2->id);
        $this->assertEquals('skipped', $c2['status']);
        $this->assertEquals('الشارع مغلق بسبب أعمال صيانة', $c2['reason']);

        $c3 = $children->firstWhere('child_id', $child3->id);
        $this->assertEquals('absent', $c3['status']);
        $this->assertEquals('تم الإبلاغ عن الغياب من ولي الأمر', $c3['reason']);

        // طلب الاندبوينت عبر /api/driver/trips/history/{tripId}
        $responseV2 = $this->actingAs($this->driverUser, 'sanctum')
            ->getJson("/api/driver/trips/history/{$this->trip->id}");
        $responseV2->assertStatus(200);

        // فحص اندبوينت قائمة السجل /api/v1/driver/trips/history
        $listResponse = $this->actingAs($this->driverUser, 'sanctum')
            ->getJson("/api/v1/driver/trips/history");
        $listResponse->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'trip_id',
                        'trip_date',
                        'route_name',
                        'status',
                        'actual_started_at',
                        'actual_completed_at',
                        'duration',
                    ]
                ]
            ]);
    }
}
