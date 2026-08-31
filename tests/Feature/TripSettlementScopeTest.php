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
use App\Models\Shared\PlatformFinance;
use App\Models\Shared\MasterEscrowVault;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Trip;
use App\Models\Shared\TripStop;
use App\Services\Trip\TripLifecycleService;

/**
 * 🛡️ اختبار انحدار لأخطر خطأ مالي في النظام:
 *
 * كانت دالة settlePlatformFinancesForCompletedTrip تصرف **كل** المبالغ المحجوزة
 * للسائق عند إكمال أي رحلة واحدة، دون أي ربط بالرحلة أو الاشتراك. النتيجة:
 * إكمال رحلة ولي أمر (أ) كان يصرف أيضاً أموال ولي أمر (ب) الذي لم تُنفَّذ رحلته بعد،
 * ويستنزف مسبح الأمانات بمبالغ لا تخص الرحلة المكتملة.
 *
 * هذه الاختبارات تثبت أن التسوية محصورة الآن في اشتراكات الرحلة المكتملة فقط.
 */
class TripSettlementScopeTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->driverUser = User::create([
            'full_name'     => 'سائق اختبار حصر التسوية',
            'email'         => 'driver.settle.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('secret123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);

        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(3)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        $this->school = School::create([
            'name'    => 'مدرسة اختبار حصر التسوية',
            'address' => 'طرابلس',
            'lat'     => 32.8950,
            'lng'     => 13.1950,
            'status'  => 'active',
        ]);
    }

    /**
     * ينشئ ولي أمر + طفل + طلب اشتراك مقبول + مبلغ محجوز (held) للسائق نفسه.
     */
    protected function makeParentWithHeldFunds(string $name, float $amount): array
    {
        $parentUser = User::create([
            'full_name'     => $name,
            'email'         => 'parent.settle.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('secret123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $parent = ParentModel::create(['user_id' => $parentUser->id, 'is_trusted' => 1]);

        $addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $parentUser->id,
            'label'      => 'منزل ' . $name,
            'lat'        => 32.8800,
            'lng'        => 13.1800,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $child = Child::create([
            'parent_id'  => $parent->id,
            'school_id'  => $this->school->id,
            'address_id' => $addressId,
            'full_name'  => 'طفل ' . $name,
            'birth_date' => '2017-01-01',
            'gender'     => 'male',
            'grade'      => 2,
        ]);

        $request = SubscriptionRequest::create([
            'parent_id'                   => $parent->id,
            'driver_id'                   => $this->driver->id,
            'status'                      => SubscriptionRequest::STATUS_ACCEPTED,
            'total_price'                 => $amount,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => $amount,
        ]);

        $commission = round($amount * 0.08, 2);

        PlatformFinance::create([
            'subscription_request_id'    => $request->id,
            'parent_id'                  => $parent->id,
            'driver_id'                  => $this->driver->id,
            'total_amount'               => $amount,
            'platform_commission_rate'   => 8.00,
            'platform_commission_amount' => $commission,
            'driver_net_amount'          => round($amount - $commission, 2),
            'status'                     => PlatformFinance::STATUS_HELD,
            'held_at'                    => now(),
        ]);

        $activeSub = ActiveSubscription::create([
            'subscription_request_id' => $request->id,
            'status'                  => 'active',
            'child_id'                => $child->id,
            'driver_id'               => $this->driver->id,
            'parent_id'               => $parentUser->id,
            'pickup_lat'              => 32.88,
            'pickup_lng'              => 13.18,
            'pickup_label'            => 'منزل',
            'pickup_time'             => '07:00:00',
            'dropoff_lat'             => 32.895,
            'dropoff_lng'             => 13.195,
            'dropoff_label'           => 'مدرسة',
            'dropoff_time'            => '14:00:00',
        ]);

        return compact('parent', 'child', 'request', 'activeSub');
    }

    protected function makeCompletedTripForChild(int $childId): Trip
    {
        $trip = Trip::create([
            'driver_id'    => $this->driver->id,
            'trip_type'    => 'Morning',
            'shift_slot'   => 'morning_go',
            'status'       => 'in_progress',
            'trip_date'    => now()->toDateString(),
            'scheduled_at' => now(),
        ]);

        TripStop::create([
            'trip_id'        => $trip->id,
            'child_id'       => $childId,
            'stop_type'      => 'home',
            'lat'            => 32.88,
            'lng'            => 13.18,
            'label'          => 'منزل',
            'sequence_order' => 1,
            'status'         => TripStop::STATUS_DELIVERED_HOME,
        ]);

        return $trip;
    }

    public function test_completing_one_trip_settles_only_that_trips_subscription(): void
    {
        $a = $this->makeParentWithHeldFunds('ولي أمر أ', 25.00);
        $b = $this->makeParentWithHeldFunds('ولي أمر ب', 40.00);

        $vaultBefore = MasterEscrowVault::getVault();
        $escrowBefore  = (int) $vaultBefore->parents_escrow_pool;
        $revenueBefore = (int) $vaultBefore->platform_revenue_pool;
        $driverBefore  = (int) $this->driver->fresh()->balance;

        // الرحلة تخص طفل ولي الأمر (أ) فقط
        $trip = $this->makeCompletedTripForChild($a['child']->id);

        $settled = app(TripLifecycleService::class)->settlePlatformFinancesForCompletedTrip($trip);

        // سجل مالي واحد فقط تمت تسويته
        $this->assertEquals(1, $settled, 'يجب تسوية اشتراك الرحلة المكتملة فقط، لا كل مبالغ السائق المحجوزة.');

        // اشتراك (أ) اكتمل
        $this->assertDatabaseHas('platform_finances', [
            'subscription_request_id' => $a['request']->id,
            'status'                  => PlatformFinance::STATUS_COMPLETED,
        ]);

        // 🔴 جوهر الاختبار: اشتراك (ب) ما زال محجوزاً ولم يُصرف
        $this->assertDatabaseHas('platform_finances', [
            'subscription_request_id' => $b['request']->id,
            'status'                  => PlatformFinance::STATUS_HELD,
        ]);

        // السائق قبض صافي اشتراك (أ) فقط: 25 - 2 = 23 د.ل = 2300 قرش
        $this->assertEquals($driverBefore + 2300, (int) $this->driver->fresh()->balance);

        // الخزينة تحركت بقيمة اشتراك (أ) فقط
        $vaultAfter = MasterEscrowVault::getVault();
        $this->assertEquals($escrowBefore - 2500, (int) $vaultAfter->parents_escrow_pool);
        $this->assertEquals($revenueBefore + 200, (int) $vaultAfter->platform_revenue_pool);
    }

    public function test_trip_with_no_linkable_subscription_settles_nothing(): void
    {
        $a = $this->makeParentWithHeldFunds('ولي أمر ج', 30.00);

        $driverBefore = (int) $this->driver->fresh()->balance;
        $escrowBefore = (int) MasterEscrowVault::getVault()->parents_escrow_pool;

        // رحلة بلا محطات وبلا مسار → لا يمكن ربطها بأي اشتراك
        $orphanTrip = Trip::create([
            'driver_id'    => $this->driver->id,
            'trip_type'    => 'Morning',
            'shift_slot'   => 'morning_go',
            'status'       => 'in_progress',
            'trip_date'    => now()->toDateString(),
            'scheduled_at' => now(),
        ]);

        $settled = app(TripLifecycleService::class)->settlePlatformFinancesForCompletedTrip($orphanTrip);

        $this->assertEquals(0, $settled, 'رحلة غير مرتبطة بأي اشتراك يجب ألا تصرف أي أموال.');

        $this->assertDatabaseHas('platform_finances', [
            'subscription_request_id' => $a['request']->id,
            'status'                  => PlatformFinance::STATUS_HELD,
        ]);

        $this->assertEquals($driverBefore, (int) $this->driver->fresh()->balance);
        $this->assertEquals($escrowBefore, (int) MasterEscrowVault::getVault()->parents_escrow_pool);
    }

    public function test_settlement_is_not_repeated_when_called_twice(): void
    {
        $a = $this->makeParentWithHeldFunds('ولي أمر د', 25.00);
        $trip = $this->makeCompletedTripForChild($a['child']->id);

        $service = app(TripLifecycleService::class);
        $driverBefore = (int) $this->driver->fresh()->balance;

        $first  = $service->settlePlatformFinancesForCompletedTrip($trip);
        $second = $service->settlePlatformFinancesForCompletedTrip($trip);

        $this->assertEquals(1, $first);
        $this->assertEquals(0, $second, 'إعادة استدعاء التسوية يجب ألا تصرف المبلغ مرتين.');
        $this->assertEquals($driverBefore + 2300, (int) $this->driver->fresh()->balance);
    }
}
