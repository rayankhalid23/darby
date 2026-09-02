<?php

namespace Tests\Feature;

use App\Models\Driver\Driver;
use App\Models\Driver\DriverSeatSlot;
use App\Models\Parent\Address;
use App\Models\Parent\Child;
use App\Models\Parent\ParentModel;
use App\Models\Parent\School;
use App\Models\Shared\PricingSetting;
use App\Models\User;
use App\Services\Shared\SubscriptionRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * يتحقق من تخزين اسم وإحداثيات المنزل والمدرسة في جدول request_children لحظة
 * إنشاء طلب الاشتراك، وتعبئتها تلقائياً من بيانات الطفل المربوطة به.
 */
class RequestChildLocationSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    protected User $parentUser;
    protected ParentModel $parent;
    protected Driver $driver;
    protected School $school;
    protected Address $address;
    protected Child $child;
    protected Child $childWithoutLocation;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        PricingSetting::query()->delete();
        PricingSetting::create([
            'discount_one_child'           => 0.00,
            'discount_two_children'        => 10.00,
            'discount_three_plus_children' => 15.00,
            'platform_commission_rate'     => 8.00,
            'price_per_km_ac'              => 2.50,
            'price_per_km_non_ac'          => 2.00,
        ]);

        $driverUser = User::create([
            'full_name'     => 'سائق اللقطة',
            'email'         => 'driver.snapshot.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);

        $this->driver = Driver::create([
            'user_id'          => $driverUser->id,
            'national_id'      => 'NAT' . rand(100000, 999999),
            'license_number'   => 'LIC' . rand(100000, 999999),
            'license_expiry'   => now()->addYears(2)->format('Y-m-d'),
            'status'           => 'Approved',
            'current_lat'      => 32.8872,
            'current_lng'      => 13.1932,
            'morning_go'       => 1,
            'morning_return'   => 1,
            'afternoon_go'     => 1,
            'afternoon_return' => 1,
        ]);

        DB::table('vehicles')->insert([
            'driver_id'       => $this->driver->id,
            'brand'           => 'تويوتا',
            'model'           => 'هايس',
            'year'            => 2023,
            'color'           => 'أبيض',
            'plate_number'    => 'SN-' . rand(1000, 9999),
            'capacity_manual' => 14,
            'capacity_ai'     => 14,
            'status'          => 'Active',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        foreach (DriverSeatSlot::ALL_SLOTS as $slot) {
            DriverSeatSlot::create([
                'driver_id'      => $this->driver->id,
                'slot'           => $slot,
                'total_seats'    => 14,
                'reserved_seats' => 0,
            ]);
        }

        $this->parentUser = User::create([
            'full_name'     => 'ولي أمر اللقطة',
            'email'         => 'parent.snapshot.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        $this->parent->deposit(500000);

        $this->school = School::create([
            'name'    => 'مدرسة الأمل النموذجية',
            'address' => 'طرابلس - حي الأندلس',
            'lat'     => 32.8870,
            'lng'     => 13.1890,
            'status'  => 'active',
        ]);

        $this->address = Address::create([
            'parent_id' => $this->parentUser->id,
            'label'     => 'المنزل - شارع النصر',
            'lat'       => 32.8752,
            'lng'       => 13.1654,
        ]);

        $this->child = Child::create([
            'parent_id'  => $this->parent->id,
            'school_id'  => $this->school->id,
            'address_id' => $this->address->id,
            'full_name'  => 'طفل مربوط بعنوان ومدرسة',
            'birth_date' => '2016-01-01',
            'gender'     => 'male',
            'grade'      => 3,
        ]);

        // طفل بلا عنوان ولا مدرسة — يجب ألا يُعطِّل إنشاء الطلب
        $this->childWithoutLocation = Child::create([
            'parent_id'  => $this->parent->id,
            'school_id'  => null,
            'address_id' => null,
            'full_name'  => 'طفل بلا عنوان ولا مدرسة',
            'birth_date' => '2017-03-03',
            'gender'     => 'female',
            'grade'      => 2,
        ]);
    }

    private function childPayload(Child $child, array $overrides = []): array
    {
        return array_merge([
            'child_id'          => $child->id,
            'subscription_type' => 'multi_day',
            'trip_direction'    => 'both',
            'timing'            => 'MORNING',
            'start_date'        => now()->addDay()->format('Y-m-d'),
            'end_date'          => now()->addMonth()->format('Y-m-d'),
            'price_per_child'   => 150.00,
            'trip_price'        => 10.00,
        ], $overrides);
    }

    /** الأعمدة الجديدة موجودة فعلاً في قاعدة البيانات */
    public function test_request_children_table_has_home_and_school_location_columns(): void
    {
        foreach (['home_label', 'home_lat', 'home_lng', 'school_label', 'school_lat', 'school_lng'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('request_children', $column),
                "العمود [{$column}] مفقود من جدول request_children"
            );
        }
    }

    /** التعبئة التلقائية من عنوان الطفل ومدرسته عند عدم إرسال أي موقع */
    public function test_creation_auto_fills_home_and_school_from_child_relations(): void
    {
        $request = app(SubscriptionRequestService::class)->createRequest([
            'driver_id' => $this->driver->id,
            'children'  => [$this->childPayload($this->child)],
        ], $this->parentUser);

        $pivot = DB::table('request_children')
            ->where('request_id', $request->id)
            ->where('child_id', $this->child->id)
            ->first();

        $this->assertNotNull($pivot, 'لم يُنشأ سجل request_children للطفل');

        $this->assertSame($this->address->label, $pivot->home_label);
        $this->assertEqualsWithDelta(32.8752, (float) $pivot->home_lat, 0.0000001);
        $this->assertEqualsWithDelta(13.1654, (float) $pivot->home_lng, 0.0000001);

        $this->assertSame($this->school->name, $pivot->school_label);
        $this->assertEqualsWithDelta(32.8870, (float) $pivot->school_lat, 0.0000001);
        $this->assertEqualsWithDelta(13.1890, (float) $pivot->school_lng, 0.0000001);
    }

    /** القيم المرسلة صراحة من الفرونت لها الأولوية على التعبئة التلقائية */
    public function test_explicitly_sent_location_overrides_auto_fill(): void
    {
        $request = app(SubscriptionRequestService::class)->createRequest([
            'driver_id' => $this->driver->id,
            'children'  => [$this->childPayload($this->child, [
                'home_label'   => 'منزل الجدة - سوق الجمعة',
                'home_lat'     => 32.9000,
                'home_lng'     => 13.2000,
                'school_label' => 'مدرسة مؤقتة',
                'school_lat'   => 32.9100,
                'school_lng'   => 13.2100,
            ])],
        ], $this->parentUser);

        $pivot = DB::table('request_children')
            ->where('request_id', $request->id)
            ->where('child_id', $this->child->id)
            ->first();

        $this->assertSame('منزل الجدة - سوق الجمعة', $pivot->home_label);
        $this->assertEqualsWithDelta(32.9000, (float) $pivot->home_lat, 0.0000001);
        $this->assertEqualsWithDelta(13.2000, (float) $pivot->home_lng, 0.0000001);
        $this->assertSame('مدرسة مؤقتة', $pivot->school_label);
        $this->assertEqualsWithDelta(32.9100, (float) $pivot->school_lat, 0.0000001);
        $this->assertEqualsWithDelta(13.2100, (float) $pivot->school_lng, 0.0000001);
    }

    /** طفل بلا عنوان ولا مدرسة: تُحفظ الأعمدة null دون تعطيل إنشاء الطلب */
    public function test_child_without_address_or_school_still_creates_request_with_null_location(): void
    {
        $request = app(SubscriptionRequestService::class)->createRequest([
            'driver_id' => $this->driver->id,
            'children'  => [$this->childPayload($this->childWithoutLocation)],
        ], $this->parentUser);

        $pivot = DB::table('request_children')
            ->where('request_id', $request->id)
            ->where('child_id', $this->childWithoutLocation->id)
            ->first();

        $this->assertNotNull($pivot);
        $this->assertNull($pivot->home_lat);
        $this->assertNull($pivot->home_lng);
        $this->assertNull($pivot->home_label);
        $this->assertNull($pivot->school_lat);
        $this->assertNull($pivot->school_lng);
        $this->assertNull($pivot->school_label);
    }

    /** اللقطة مجمّدة: تعديل عنوان الطفل أو مدرسته لاحقاً لا يُغيّر الطلب القديم */
    public function test_snapshot_is_frozen_when_child_address_or_school_changes_later(): void
    {
        $request = app(SubscriptionRequestService::class)->createRequest([
            'driver_id' => $this->driver->id,
            'children'  => [$this->childPayload($this->child)],
        ], $this->parentUser);

        // ولي الأمر ينتقل لمنزل جديد ويغيّر مدرسة طفله بعد إرسال الطلب
        $newAddress = Address::create([
            'parent_id' => $this->parentUser->id,
            'label'     => 'المنزل الجديد - قرقارش',
            'lat'       => 32.8000,
            'lng'       => 13.1000,
        ]);

        $newSchool = School::create([
            'name'    => 'مدرسة أخرى تماماً',
            'address' => 'طرابلس - قرقارش',
            'lat'     => 32.8100,
            'lng'     => 13.1100,
            'status'  => 'active',
        ]);

        $this->child->update([
            'address_id' => $newAddress->id,
            'school_id'  => $newSchool->id,
        ]);

        $pivot = DB::table('request_children')
            ->where('request_id', $request->id)
            ->where('child_id', $this->child->id)
            ->first();

        $this->assertSame('المنزل - شارع النصر', $pivot->home_label, 'اللقطة القديمة يجب ألا تتأثر بتغيير العنوان');
        $this->assertEqualsWithDelta(32.8752, (float) $pivot->home_lat, 0.0000001);
        $this->assertSame('مدرسة الأمل النموذجية', $pivot->school_label, 'اللقطة القديمة يجب ألا تتأثر بتغيير المدرسة');
        $this->assertEqualsWithDelta(32.8870, (float) $pivot->school_lat, 0.0000001);
    }

    /** الأعمدة الجديدة متاحة عبر علاقة children()->pivot دون withPivot إضافي */
    public function test_pivot_exposes_location_columns_on_relation(): void
    {
        $request = app(SubscriptionRequestService::class)->createRequest([
            'driver_id' => $this->driver->id,
            'children'  => [$this->childPayload($this->child)],
        ], $this->parentUser);

        $pivot = $request->fresh()->children()->where('children.id', $this->child->id)->first()->pivot;

        $this->assertSame($this->address->label, $pivot->home_label);
        $this->assertSame($this->school->name, $pivot->school_label);
        $this->assertEqualsWithDelta(32.8752, (float) $pivot->home_lat, 0.0000001);
        $this->assertEqualsWithDelta(13.1890, (float) $pivot->school_lng, 0.0000001);
    }

    /** المسار الفعلي POST /api/parent/requests يحفظ اللقطة (قواعد التحقق لا تُسقِطها) */
    public function test_store_endpoint_persists_location_snapshot(): void
    {
        $response = $this->actingAs($this->parentUser)->postJson('/api/parent/requests', [
            'driver_id' => $this->driver->id,
            'children'  => [$this->childPayload($this->child)],
        ]);

        $response->assertStatus(201);

        $requestId = $response->json('data.id') ?? $response->json('data.subscription_request_id');
        $this->assertNotNull($requestId, 'لم يُرجَع معرف الطلب في الاستجابة');

        $this->assertDatabaseHas('request_children', [
            'request_id'   => $requestId,
            'child_id'     => $this->child->id,
            'home_label'   => $this->address->label,
            'school_label' => $this->school->name,
        ]);
    }

    /** المسار POST /api/parent/requests يقبل موقعاً صريحاً مرسلاً من الفرونت */
    public function test_store_endpoint_accepts_explicit_location_from_payload(): void
    {
        $response = $this->actingAs($this->parentUser)->postJson('/api/parent/requests', [
            'driver_id' => $this->driver->id,
            'children'  => [$this->childPayload($this->child, [
                'home_label' => 'منزل مؤقت',
                'home_lat'   => 32.9500,
                'home_lng'   => 13.2500,
            ])],
        ]);

        $response->assertStatus(201);

        $requestId = $response->json('data.id') ?? $response->json('data.subscription_request_id');

        $pivot = DB::table('request_children')
            ->where('request_id', $requestId)
            ->where('child_id', $this->child->id)
            ->first();

        $this->assertSame('منزل مؤقت', $pivot->home_label);
        $this->assertEqualsWithDelta(32.9500, (float) $pivot->home_lat, 0.0000001);
        // المدرسة لم تُرسل، فتُعبأ تلقائياً كالمعتاد
        $this->assertSame($this->school->name, $pivot->school_label);
    }

    /** GET /api/parent/requests/{id} يُرجع اللقطة المحفوظة في اسم وإحداثيات المنزل والمدرسة */
    public function test_show_request_endpoint_returns_snapshot_in_home_and_school_blocks(): void
    {
        $request = app(SubscriptionRequestService::class)->createRequest([
            'driver_id' => $this->driver->id,
            'children'  => [$this->childPayload($this->child)],
        ], $this->parentUser);

        $response = $this->actingAs($this->parentUser)->getJson("/api/parent/requests/{$request->id}");

        $response->assertStatus(200);

        $child = $response->json('data.children.0');
        $this->assertNotNull($child, 'لم تُرجَع بيانات الطفل في الاستجابة');

        $this->assertSame($this->address->label, $child['Home']['name']);
        $this->assertEqualsWithDelta(32.8752, (float) $child['Home']['latitude'], 0.0000001);
        $this->assertEqualsWithDelta(13.1654, (float) $child['Home']['longitude'], 0.0000001);

        $this->assertSame($this->school->name, $child['School']['name']);
        $this->assertEqualsWithDelta(32.8870, (float) $child['School']['latitude'], 0.0000001);
        $this->assertEqualsWithDelta(13.1890, (float) $child['School']['longitude'], 0.0000001);
    }

    /**
     * اللقطة تُغذّي active_subscriptions عند قبول السائق، فتصل الإحداثيات إلى
     * route_stops و trip_stops بدل أن تُخزَّن null كما كان يحدث قبل إضافة الأعمدة.
     */
    public function test_accepted_request_copies_snapshot_into_active_subscription(): void
    {
        $service = app(SubscriptionRequestService::class);

        $request = $service->createRequest([
            'driver_id' => $this->driver->id,
            'children'  => [$this->childPayload($this->child)],
        ], $this->parentUser);

        $service->updateStatus($request, \App\Models\Shared\SubscriptionRequest::STATUS_ACCEPTED);

        $activeSub = \App\Models\Shared\ActiveSubscription::where('subscription_request_id', $request->id)
            ->where('child_id', $this->child->id)
            ->first();

        $this->assertNotNull($activeSub, 'لم يُنشأ اشتراك نشط بعد القبول');
        $this->assertEqualsWithDelta(32.8752, (float) $activeSub->pickup_lat, 0.0000001);
        $this->assertEqualsWithDelta(13.1654, (float) $activeSub->pickup_lng, 0.0000001);
        $this->assertSame($this->address->label, $activeSub->pickup_label);
        $this->assertEqualsWithDelta(32.8870, (float) $activeSub->dropoff_lat, 0.0000001);
        $this->assertEqualsWithDelta(13.1890, (float) $activeSub->dropoff_lng, 0.0000001);
        $this->assertSame($this->school->name, $activeSub->dropoff_label);
    }

    /** إحداثيات خارج المدى المسموح تُرفض بخطأ تحقق لا بخطأ قاعدة بيانات */
    public function test_store_endpoint_rejects_out_of_range_coordinates(): void
    {
        $response = $this->actingAs($this->parentUser)->postJson('/api/parent/requests', [
            'driver_id' => $this->driver->id,
            'children'  => [$this->childPayload($this->child, [
                'home_lat' => 999,
                'home_lng' => -999,
            ])],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['children.0.home_lat', 'children.0.home_lng']);
    }
}
