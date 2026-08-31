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
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Parent\Address;
use App\Models\Parent\School;
use App\Models\Shared\Zone;
use App\Models\Shared\SubMunicipality;
use App\Models\Shared\Municipality;
use App\Models\Shared\PricingSetting;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\MasterEscrowVault;
use App\Models\Shared\PlatformFinance;
use App\Models\Shared\Route;
use App\Models\Shared\RouteStop;

/**
 * Shared fixture for the trip-scenario audit tests.
 * Not a test class itself (abstract), so PHPUnit will not execute it directly.
 */
abstract class TripAuditFixture extends TestCase
{
    use DatabaseTransactions;

    protected User $parentUser;
    protected ParentModel $parent;
    protected User $driverUser;
    protected Driver $driver;
    protected Vehicle $vehicle;
    protected School $school;
    protected Address $homeAddress;
    protected Zone $zone;
    protected PricingSetting $pricing;

    const HOME_LAT = 32.88000000;
    const HOME_LNG = 13.18000000;
    const SCHOOL_LAT = 32.89000000;
    const SCHOOL_LNG = 13.19000000;

    protected function out(string $msg): void
    {
        fwrite(STDERR, "\n" . $msg . "\n");
    }

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'driver'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'parent'],
        ]);

        $this->pricing = PricingSetting::firstOrCreate([], [
            'price_per_km_ac'              => 2.50,
            'price_per_km_non_ac'          => 2.00,
            'discount_one_child'           => 0.00,
            'discount_two_children'        => 10.00,
            'discount_three_plus_children' => 15.00,
            'platform_commission_rate'     => 8.00,
        ]);

        $municipality = Municipality::firstOrCreate(['name' => 'Tripoli']);
        $subMuni = SubMunicipality::firstOrCreate(['municipality_id' => $municipality->id, 'name' => 'SoukAlgomaa']);
        $this->zone = Zone::firstOrCreate(['sub_municipality_id' => $subMuni->id, 'name' => 'Shat']);

        $this->school = School::create([
            'name'    => 'Shat Primary School',
            'zone_id' => $this->zone->id,
            'lat'     => self::SCHOOL_LAT,
            'lng'     => self::SCHOOL_LNG,
            'address' => 'Tripoli - Shat',
            'status'  => 'active',
        ]);

        $this->parentUser = User::create([
            'full_name'     => 'Salem Parent',
            'email'         => 'p.' . uniqid() . '@audit.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);
        $this->parent = ParentModel::create(['user_id' => $this->parentUser->id, 'is_trusted' => 1]);
        $this->parent->deposit(100000);

        $this->homeAddress = Address::create([
            'parent_id' => $this->parentUser->id,
            'label'     => 'Family home',
            'lat'       => self::HOME_LAT,
            'lng'       => self::HOME_LNG,
        ]);

        $this->driverUser = User::create([
            'full_name'     => 'Captain Mahmoud',
            'email'         => 'd.' . uniqid() . '@audit.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);
        $this->driver = Driver::create([
            'user_id'           => $this->driverUser->id,
            'national_id'       => 'NAT' . rand(100000, 999999),
            'license_number'    => 'LIC' . rand(100000, 999999),
            'license_expiry'    => now()->addYears(2)->toDateString(),
            'status'            => 'Approved',
            'gender'            => 'male',
            'accepted_gender'   => 'both',
            'subscription_type' => 'both',
            'morning_go'        => 1,
            'morning_return'    => 1,
            'current_lat'       => 32.87500000,
            'current_lng'       => 13.17500000,
        ]);

        $this->vehicle = Vehicle::create([
            'driver_id'       => $this->driver->id,
            'brand'           => 'Toyota', 'model' => 'HiAce', 'year' => 2023,
            'color'           => 'silver', 'type' => 'Van',
            'plate_number'    => 'LY-' . rand(1000, 9999),
            'capacity_manual' => 10, 'has_ac' => 1, 'status' => 'Active', 'is_verified' => 1,
        ]);

        DB::table('driver_zone')->insertOrIgnore(['driver_id' => $this->driver->id, 'zone_id' => $this->zone->id]);

        foreach (['morning_go', 'morning_return', 'afternoon_go', 'afternoon_return'] as $slot) {
            DriverSeatSlot::create([
                'driver_id' => $this->driver->id, 'slot' => $slot,
                'total_seats' => 10, 'reserved_seats' => 0,
            ]);
        }
    }

    protected function makeSubscription(string $name, string $qr = 'x', string $type = 'single_day', int $days = 0, float $price = 100.00): array
    {
        $child = Child::create([
            'parent_id'     => $this->parent->id,
            'school_id'     => $this->school->id,
            'address_id'    => $this->homeAddress->id,
            'full_name'     => $name,
            'birth_date'    => '2016-03-20',
            'gender'        => 'male',
            'grade'         => 3,
        ]);

        $start = Carbon::today()->format('Y-m-d');
        $end   = Carbon::today()->addDays($days)->format('Y-m-d');

        $req = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'status'                      => 'accepted',
            'subscription_type'           => $type,
            'total_price'                 => $price,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => $price,
        ]);

        $req->children()->attach($child->id, [
            'subscription_type'           => $type,
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => $start,
            'end_date'                    => $end,
            'working_days_count'          => max(1, $days),
            'distance_km'                 => 4.5,
            'trip_price'                  => $price,
            'price_per_child'             => $price,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => $price,
            'driver_net_price'            => round($price * 0.92, 2),
            'created_at'                  => now(), 'updated_at' => now(),
        ]);

        $route = Route::firstOrCreate(
            ['driver_id' => $this->driver->id, 'shift_slot' => 'morning_go'],
            [
                'vehicle_id'              => $this->vehicle->id,
                'subscription_request_id' => $req->id,
                'route_name'              => 'Morning go route',
                'route_type'              => 'Morning',
                'start_time'              => '07:00:00',
                'status'                  => 'Active',
            ]
        );

        $sub = ActiveSubscription::create([
            'subscription_request_id' => $req->id,
            'route_id'                => $route->id,
            'child_id'                => $child->id,
            'driver_id'               => $this->driver->id,
            'parent_id'               => $this->parentUser->id,
            'status'                  => 'active',
            'pickup_lat'              => self::HOME_LAT,
            'pickup_lng'              => self::HOME_LNG,
            'pickup_label'            => 'Child home',
            'dropoff_lat'             => self::SCHOOL_LAT,
            'dropoff_lng'             => self::SCHOOL_LNG,
            'dropoff_label'           => 'School',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
        ]);

        RouteStop::firstOrCreate(
            ['route_id' => $route->id, 'stop_type' => RouteStop::TYPE_HOME, 'child_id' => $child->id],
            ['lat' => self::HOME_LAT, 'lng' => self::HOME_LNG, 'label' => 'Home ' . $name, 'sequence_order' => 1]
        );
        RouteStop::firstOrCreate(
            ['route_id' => $route->id, 'stop_type' => RouteStop::TYPE_SCHOOL, 'school_id' => $this->school->id],
            ['lat' => self::SCHOOL_LAT, 'lng' => self::SCHOOL_LNG, 'label' => $this->school->name, 'sequence_order' => 99]
        );

        return compact('child', 'req', 'route', 'sub');
    }

    /**
     * يحاكي بالضبط ما تفعله holdSubscriptionFundsOnAcceptance عند قبول السائق للطلب.
     */
    protected function holdEscrow(SubscriptionRequest $req): PlatformFinance
    {
        $amountDinar = (float) $req->total_amount_after_discount;
        $cents = (int) round($amountDinar * 100);
        $this->parent->withdraw($cents);
        MasterEscrowVault::getVault()->increment('parents_escrow_pool', $cents);
        $rate = (float) $this->pricing->platform_commission_rate;
        $commission = round(($amountDinar * $rate) / 100, 2);

        return PlatformFinance::create([
            'subscription_request_id'    => $req->id,
            'parent_id'                  => $this->parent->id,
            'driver_id'                  => $this->driver->id,
            'total_amount'               => $amountDinar,
            'platform_commission_rate'   => $rate,
            'platform_commission_amount' => $commission,
            'driver_net_amount'          => round($amountDinar - $commission, 2),
            'expected_trips_count'       => $this->expectedTripsFor($req),
            'settled_trips_count'        => 0,
            'settled_amount'             => 0,
            'status'                     => PlatformFinance::STATUS_HELD,
            'held_at'                    => now(),
        ]);
    }

    /** أيام العمل × عدد الرحلات في اليوم — نفس صيغة الخدمة الحقيقية. */
    protected function expectedTripsFor(SubscriptionRequest $req): int
    {
        $total = 0;
        foreach ($req->children as $child) {
            $pivot       = $child->pivot;
            $workingDays = max(1, (int) ($pivot->working_days_count ?? 1));
            $direction   = strtolower((string) ($pivot->trip_direction ?? 'both'));
            $tripsPerDay = in_array($direction, ['one_way_morning', 'one_way_evening', 'go', 'return'], true) ? 1 : 2;
            $total = max($total, $workingDays * $tripsPerDay);
        }
        return max(1, $total);
    }

    protected function makeOtherDriver(): array
    {
        $u = User::create([
            'full_name'     => 'Captain Other',
            'email'         => 'd2.' . uniqid() . '@audit.test',
            'phone_number'  => '093' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);
        $d = Driver::create([
            'user_id'           => $u->id,
            'national_id'       => 'NAT' . rand(100000, 999999),
            'license_number'    => 'LIC' . rand(100000, 999999),
            'license_expiry'    => now()->addYears(2)->toDateString(),
            'status'            => 'Approved',
            'gender'            => 'male',
            'accepted_gender'   => 'both',
            'subscription_type' => 'both',
            'morning_go'        => 1,
            'current_lat'       => 32.10,
            'current_lng'       => 13.10,
        ]);
        return ['user' => $u, 'driver' => $d];
    }

    protected function makeOtherParent(): User
    {
        $u = User::create([
            'full_name'     => 'Other Parent',
            'email'         => 'p2.' . uniqid() . '@audit.test',
            'phone_number'  => '094' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);
        ParentModel::create(['user_id' => $u->id, 'is_trusted' => 1]);
        return $u;
    }

    protected function asDriver() { return $this->actingAs($this->driverUser, 'sanctum'); }
    protected function asParent() { return $this->actingAs($this->parentUser, 'sanctum'); }

    /** Generate today's trip through the driver API and return its id. */
    protected function generateTodayTrip(): int
    {
        $r = $this->asDriver()->getJson('/api/driver/trips/today');
        $r->assertStatus(200);
        return (int) $r->json('data.0.trip_id');
    }
}
