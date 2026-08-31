<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\DriverAbsence;
use App\Models\Driver\Vehicle;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Parent\School;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\Route as RouteModel;
use App\Models\Shared\Trip;
use App\Models\Shared\TripBreakdownDispatch;
use Carbon\Carbon;

class DriverTripSpecificAbsenceTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $driverUser;
    protected Driver $driver;
    protected User $otherDriverUser;
    protected Driver $otherDriver;
    protected RouteModel $route1;
    protected RouteModel $route2;
    protected Trip $trip1;
    protected Trip $trip2;
    protected Trip $trip3;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin',  'display_name' => 'مدير'],
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        // 1. إنشاء الأدمن
        $this->adminUser = User::create([
            'full_name'     => 'مدير النظام التجريبي',
            'email'         => 'admin.abs.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);
        DB::table('admins')->insertOrIgnore([
            'user_id'    => $this->adminUser->id,
        ]);

        // 2. إنشاء السائق الأساسي
        $this->driverUser = User::create([
            'full_name'     => 'الكابتن محمد علي',
            'email'         => 'driver1.abs.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);
        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
            'current_lat'    => 32.8872,
            'current_lng'    => 13.1913,
        ]);

        // 3. إنشاء سائق آخر
        $this->otherDriverUser = User::create([
            'full_name'     => 'الكابتن طارق عمر',
            'email'         => 'driver2.abs.' . uniqid() . '@darby.test',
            'phone_number'  => '093' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);
        $this->otherDriver = Driver::create([
            'user_id'        => $this->otherDriverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
            'current_lat'    => 32.8900,
            'current_lng'    => 13.1950,
        ]);

        $vehicle = Vehicle::create([
            'driver_id'       => $this->driver->id,
            'plate_number'    => '5-' . rand(10000, 99999),
            'brand'           => 'Toyota',
            'model'           => 'HiAce',
            'year'            => 2023,
            'color'           => 'White',
            'type'            => 'Van',
            'capacity_manual' => 14,
            'status'          => 'active',
        ]);

        $this->route1 = RouteModel::create([
            'driver_id'          => $this->driver->id,
            'vehicle_id'         => $vehicle->id,
            'route_name'         => 'مسار الصباح حي الأندلس',
            'route_type'         => 'Morning',
            'shift_slot'         => 'morning_go',
            'status'             => 'Active',
            'start_time'         => '07:00',
            'estimated_duration' => 40,
        ]);

        $this->route2 = RouteModel::create([
            'driver_id'          => $this->driver->id,
            'vehicle_id'         => $vehicle->id,
            'route_name'         => 'مسار الظهيرة حي الأندلس',
            'route_type'         => 'Afternoon',
            'shift_slot'         => 'afternoon_return',
            'status'             => 'Active',
            'start_time'         => '13:30',
            'estimated_duration' => 40,
        ]);

        $targetDate = Carbon::tomorrow()->toDateString();

        // الرحلة الأولى للسائق غداً (Morning)
        $this->trip1 = Trip::create([
            'driver_id'            => $this->driver->id,
            'route_id'             => $this->route1->id,
            'trip_type'            => 'Morning',
            'shift_slot'           => 'morning_go',
            'status'               => 'pending',
            'scheduled_start_time' => Carbon::parse($targetDate . ' 07:00:00'),
            'trip_date'            => $targetDate,
        ]);

        // الرحلة الثانية للسائق غداً (Afternoon)
        $this->trip2 = Trip::create([
            'driver_id'            => $this->driver->id,
            'route_id'             => $this->route2->id,
            'trip_type'            => 'Afternoon',
            'shift_slot'           => 'afternoon_return',
            'status'               => 'pending',
            'scheduled_start_time' => Carbon::parse($targetDate . ' 13:30:00'),
            'trip_date'            => $targetDate,
        ]);

        // رحلة ثالثة للسائق في يوم آخر بعد غد
        $afterTomorrow = Carbon::tomorrow()->addDay()->toDateString();
        $this->trip3 = Trip::create([
            'driver_id'            => $this->driver->id,
            'route_id'             => $this->route1->id,
            'trip_type'            => 'Morning',
            'shift_slot'           => 'morning_go',
            'status'               => 'pending',
            'scheduled_start_time' => Carbon::parse($afterTomorrow . ' 07:00:00'),
            'trip_date'            => $afterTomorrow,
        ]);
    }

    /**
     * 1. نجاح تقديم طلب غياب برحلات محددة والسبب والتاريخ: يُطبَّق فوراً بدون موافقة إدارية،
     *    يُنزَع السائق من الرحلتين المحددتين فقط، وتُخزَّن الحالة كـ "approved" مباشرة.
     */
    public function test_driver_can_request_absence_for_specific_trips_with_reason(): void
    {
        $targetDate = Carbon::tomorrow()->toDateString();

        $payload = [
            'date'     => $targetDate,
            'trip_ids' => [$this->trip1->id, $this->trip2->id],
            'reason'   => 'عطل في محرك السيارة',
        ];

        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/driver/trips/register-absence', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.driver_id', $this->driver->id);
        $response->assertJsonPath('data.absence_date', $targetDate);
        $response->assertJsonPath('data.reason', 'عطل في محرك السيارة');
        $response->assertJsonPath('data.status', 'approved');
        $response->assertJsonPath('data.trip_ids', [$this->trip1->id, $this->trip2->id]);

        // التحقق من قاعدة البيانات: الطلب مُعتمد فوراً بلا مراجعة إدارية
        $this->assertDatabaseHas('driver_absences', [
            'driver_id'    => $this->driver->id,
            'absence_date' => $targetDate,
            'reason'       => 'عطل في محرك السيارة',
            'status'       => 'approved',
        ]);

        $absenceId = $response->json('data.absence_id');
        $this->assertDatabaseHas('driver_absence_trips', [
            'driver_absence_id' => $absenceId,
            'trip_id'           => $this->trip1->id,
        ]);
        $this->assertDatabaseHas('driver_absence_trips', [
            'driver_absence_id' => $absenceId,
            'trip_id'           => $this->trip2->id,
        ]);

        // السائق يُنزَع فوراً من الرحلتين المحددتين فقط...
        $this->assertNull($this->trip1->fresh()->driver_id);
        $this->assertNull($this->trip2->fresh()->driver_id);
        // ...بينما رحلته الثالثة (يوم آخر، غير مطلوب الغياب عنها) تبقى مسندة له.
        $this->assertEquals($this->driver->id, $this->trip3->fresh()->driver_id);
    }

    /**
     * 2. التحقق الأمني: رفض الطلب إذا كانت إحدى الرحلات لا تخص السائق
     */
    public function test_validation_fails_if_trip_belongs_to_another_driver(): void
    {
        $targetDate = Carbon::tomorrow()->toDateString();

        // رحلة تخص سائقاً آخر
        $otherDriverTrip = Trip::create([
            'driver_id'            => $this->otherDriver->id,
            'route_id'             => null,
            'trip_type'            => 'Morning',
            'status'               => 'pending',
            'scheduled_start_time' => Carbon::parse($targetDate . ' 07:00:00'),
            'trip_date'            => $targetDate,
        ]);

        $payload = [
            'date'     => $targetDate,
            'trip_ids' => [$this->trip1->id, $otherDriverTrip->id],
            'reason'   => 'ظرف طارئ',
        ];

        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/driver/trips/register-absence', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['trip_ids']);
    }

    /**
     * 3. التحقق الأمني: رفض الطلب إذا كان تاريخ الرحلة لا يطابق تاريخ الغياب المطلوب
     */
    public function test_validation_fails_if_trip_date_does_not_match_request_date(): void
    {
        $targetDate = Carbon::tomorrow()->toDateString();

        // trip3 مجدولة بعد غد وليس غداً
        $payload = [
            'date'     => $targetDate,
            'trip_ids' => [$this->trip3->id],
            'reason'   => 'ظرف طارئ',
        ];

        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/driver/trips/register-absence', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['trip_ids']);
    }

    /**
     * 4. التحقق الأمني: رفض الطلب إذا كانت الرحلة معينة مسبقاً لسائق بديل
     */
    public function test_validation_fails_if_trip_already_assigned_to_substitute_driver(): void
    {
        $targetDate = Carbon::tomorrow()->toDateString();

        // إنشاء سجل تكليف بديل مكتمل أو مقبول للرحلة trip1
        TripBreakdownDispatch::create([
            'trip_id'              => $this->trip1->id,
            'original_driver_id'   => $this->driver->id,
            'substitute_driver_id' => $this->otherDriver->id,
            'status'               => 'accepted',
            'breakdown_lat'        => 32.8872,
            'breakdown_lng'        => 13.1913,
            'breakdown_reason'     => 'عطل سابق',
        ]);

        $payload = [
            'date'     => $targetDate,
            'trip_ids' => [$this->trip1->id],
            'reason'   => 'طلب غياب متأخر',
        ];

        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/driver/trips/register-absence', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['trip_ids']);
    }

    /**
     * 5. موافقة الإدارة: يتم نزع السائق فقط من الرحلات المحددة
     */
    public function test_admin_approval_unassigns_driver_from_only_specified_trips(): void
    {
        $targetDate = Carbon::tomorrow()->toDateString();

        // 1. السائق يقدم غياب فقط عن trip1 (رحلة الصباح)، بينما trip2 (رحلة المساء) و trip3 تظلان له
        $absence = DriverAbsence::create([
            'driver_id'    => $this->driver->id,
            'absence_date' => $targetDate,
            'reason'       => 'عطل في محرك السيارة',
            'status'       => 'pending',
        ]);
        $absence->trips()->attach([$this->trip1->id]);

        // التأكد قبل الموافقة: السائق مرتبط بكافة الرحلات الثلاث
        $this->assertEquals($this->driver->id, $this->trip1->fresh()->driver_id);
        $this->assertEquals($this->driver->id, $this->trip2->fresh()->driver_id);
        $this->assertEquals($this->driver->id, $this->trip3->fresh()->driver_id);

        // 2. الأدمن يوافق على الطلب
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/driver-absences/{$absence->id}/approve", [
                'notes' => 'تمت الموافقة وتكليف السائق الاحتياطي للرحلة الصباحية',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.status', 'approved');

        // 3. التحقق الحاسم: تم نزع السائق من trip1 فقط!
        $this->assertNull($this->trip1->fresh()->driver_id);
        $this->assertEquals('pending', $this->trip1->fresh()->status);

        // 4. الرحلات الأخرى ما زالت مسندة للسائق ولم تُمس!
        $this->assertEquals($this->driver->id, $this->trip2->fresh()->driver_id);
        $this->assertEquals($this->driver->id, $this->trip3->fresh()->driver_id);
    }

    /**
     * 6. رفض الإدارة: يبقى السائق مسنداً لرحلاته وتتحول حالة الطلب إلى rejected
     */
    public function test_admin_rejection_keeps_driver_assigned_to_trips(): void
    {
        $targetDate = Carbon::tomorrow()->toDateString();

        $absence = DriverAbsence::create([
            'driver_id'    => $this->driver->id,
            'absence_date' => $targetDate,
            'reason'       => 'إرهاق خفيف',
            'status'       => 'pending',
        ]);
        $absence->trips()->attach([$this->trip1->id]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/driver-absences/{$absence->id}/reject", [
                'reason' => 'عذراً، لا يتوفر سائق بديل في منطقتك حالياً.',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.status', 'rejected');

        // السائق يظل مسنداً للرحلة
        $this->assertEquals($this->driver->id, $this->trip1->fresh()->driver_id);
    }

    /**
     * 7. استعراض الإدارة لقائمة طلبات الغياب وتفاصيلها
     */
    public function test_admin_can_list_and_view_absence_requests(): void
    {
        $targetDate = Carbon::tomorrow()->toDateString();

        $absence = DriverAbsence::create([
            'driver_id'    => $this->driver->id,
            'absence_date' => $targetDate,
            'reason'       => 'صيانة دورية للمركبة',
            'status'       => 'pending',
        ]);
        $absence->trips()->attach([$this->trip1->id, $this->trip2->id]);

        // قائمة الطلبات
        $listResponse = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/driver-absences');

        $listResponse->assertStatus(200);
        $listResponse->assertJsonPath('status', true);

        // تفاصيل الطلب
        $showResponse = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/driver-absences/{$absence->id}");

        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('status', true);
        $showResponse->assertJsonPath('data.id', $absence->id);
        $showResponse->assertJsonPath('data.reason', 'صيانة دورية للمركبة');
    }
}
