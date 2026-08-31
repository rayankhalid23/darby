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

class ChildActiveSubscriptionAbsenceConflictTest extends TestCase
{
    use DatabaseTransactions;

    protected User $parentUser;
    protected ParentModel $parent;
    protected User $driverUser1;
    protected Driver $driver1;
    protected User $driverUser2;
    protected Driver $driver2;
    protected Child $child1;
    protected Child $child2;
    protected School $school;
    protected Address $homeAddress;
    protected Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. إعدادات الأدوار
        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        // 2. إعدادات التسعير
        PricingSetting::firstOrCreate([], [
            'price_per_km_ac'              => 2.50,
            'price_per_km_non_ac'          => 2.00,
            'discount_one_child'           => 0.00,
            'discount_two_children'        => 10.00,
            'discount_three_plus_children' => 15.00,
            'platform_commission_rate'     => 8.00,
        ]);

        // 3. المنطقة والمدرسة
        $municipality = Municipality::firstOrCreate(['name' => 'طرابلس الكبرى']);
        $subMuni = SubMunicipality::firstOrCreate(['municipality_id' => $municipality->id, 'name' => 'سوق الجمعة']);
        $this->zone = Zone::firstOrCreate(['sub_municipality_id' => $subMuni->id, 'name' => 'الهاني']);

        $this->school = School::create([
            'name'    => 'مدرسة المستقبل',
            'zone_id' => $this->zone->id,
            'lat'     => 32.88500000,
            'lng'     => 13.18500000,
            'address' => 'طرابلس - الهاني',
            'status'  => 'active',
        ]);

        // 4. ولي الأمر
        $this->parentUser = User::create([
            'full_name'     => 'طارق علي المنصوري',
            'email'         => 'parent.conflict.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);
        $this->parent->deposit(500000); // 5000 د.ل

        // عنوان سكن ولي الأمر
        $this->homeAddress = Address::create([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'منزل العائلة',
            'lat'        => 32.88000000,
            'lng'        => 13.18000000,
        ]);

        // أطفال ولي الأمر
        $this->child1 = Child::create([
            'parent_id'  => $this->parent->id,
            'school_id'  => $this->school->id,
            'address_id' => $this->homeAddress->id,
            'full_name'  => 'يوسف طارق المنصوري',
            'gender'     => 'male',
            'grade'      => '5',
            'birth_date' => '2015-05-10',
        ]);

        $this->child2 = Child::create([
            'parent_id'  => $this->parent->id,
            'school_id'  => $this->school->id,
            'address_id' => $this->homeAddress->id,
            'full_name'  => 'سارة طارق المنصوري',
            'gender'     => 'female',
            'grade'      => '3',
            'birth_date' => '2017-08-20',
        ]);

        // 5. السائق الأول
        $this->driverUser1 = User::create([
            'full_name'     => 'خالد ناصر الورفلي',
            'email'         => 'driver1.conflict.' . uniqid() . '@darby.test',
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

        Vehicle::create([
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

        DB::table('driver_zone')->insertOrIgnore([
            'driver_id' => $this->driver1->id,
            'zone_id'   => $this->zone->id,
        ]);

        foreach (DriverSeatSlot::ALL_SLOTS as $slot) {
            DriverSeatSlot::create([
                'driver_id'      => $this->driver1->id,
                'slot'           => $slot,
                'total_seats'    => 10,
                'reserved_seats' => 0,
            ]);
        }

        // 6. السائق الثاني (السائق البديل)
        $this->driverUser2 = User::create([
            'full_name'     => 'محمود عبدالسلام الترهوني',
            'email'         => 'driver2.conflict.' . uniqid() . '@darby.test',
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

        Vehicle::create([
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

        DB::table('driver_zone')->insertOrIgnore([
            'driver_id' => $this->driver2->id,
            'zone_id'   => $this->zone->id,
        ]);

        foreach (DriverSeatSlot::ALL_SLOTS as $slot) {
            DriverSeatSlot::create([
                'driver_id'      => $this->driver2->id,
                'slot'           => $slot,
                'total_seats'    => 10,
                'reserved_seats' => 0,
            ]);
        }
    }

    /**
     * دالة مساعدة لإنشاء اشتراك نشط لطفل
     */
    protected function createActiveSubscriptionForChild(Child $child, Driver $driver, string $startDate, string $endDate): ActiveSubscription
    {
        $req = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $driver->id,
            'status'                      => SubscriptionRequest::STATUS_ACCEPTED,
            'total_price'                 => 100.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 100.00,
        ]);

        $req->children()->attach($child->id, [
            'subscription_type'           => 'multi_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => $startDate,
            'end_date'                    => $endDate,
            'working_days_count'          => 5,
            'price_per_child'             => 100.00,
            'trip_price'                  => 100.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 100.00,
            'driver_net_price'            => 92.00,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        return ActiveSubscription::create([
            'subscription_request_id' => $req->id,
            'child_id'                => $child->id,
            'driver_id'               => $driver->id,
            'parent_id'               => $this->parentUser->id,
            'status'                  => 'active',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
        ]);
    }

    /**
     * 1. طفل بدون أي اشتراك نشط سابق: يتم إرسال طلب الاشتراك بنجاح.
     */
    public function test_parent_can_create_subscription_request_when_child_has_no_active_subscription(): void
    {
        $startDate = Carbon::now()->addDays(2)->startOfWeek(Carbon::SUNDAY)->toDateString();
        $endDate   = Carbon::parse($startDate)->addDays(4)->toDateString(); // Thursday

        $payload = [
            'driver_id' => $this->driver1->id,
            'children'  => [
                [
                    'child_id'          => $this->child1->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => $startDate,
                    'end_date'          => $endDate,
                    'price_per_child'   => 150.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson('/api/parent/requests', $payload);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    /**
     * 2. طفل لديه اشتراك نشط، والسائق غير مسجل لغيابه في تلك الأيام: يُمنع ولي الأمر مع رسالة دقيقة.
     */
    public function test_parent_cannot_create_request_if_child_has_active_subscription_and_driver_not_absent(): void
    {
        $startDate = Carbon::now()->addDays(2)->startOfWeek(Carbon::SUNDAY)->toDateString();
        $endDate   = Carbon::parse($startDate)->addDays(4)->toDateString();

        // تفعيل اشتراك نشط للطفل الأول مع السائق الأول
        $this->createActiveSubscriptionForChild($this->child1, $this->driver1, $startDate, $endDate);

        // محاولة إرسال طلب جديد لنفس الأيام مع السائق الثاني
        $payload = [
            'driver_id' => $this->driver2->id,
            'children'  => [
                [
                    'child_id'          => $this->child1->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => $startDate,
                    'end_date'          => $endDate,
                    'price_per_child'   => 150.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson('/api/parent/requests', $payload);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonFragment([
                'message' => "لا يمكن إرسال طلب الاشتراك: الطفل [{$this->child1->full_name}] لديه اشتراك نشط بالفعل مع السائق [{$this->driverUser1->full_name}] خلال الأيام ({$startDate}, " . Carbon::parse($startDate)->addDay()->toDateString() . ", " . Carbon::parse($startDate)->addDays(2)->toDateString() . ", " . Carbon::parse($startDate)->addDays(3)->toDateString() . ", " . Carbon::parse($startDate)->addDays(4)->toDateString() . ")، والسائق غير مسجل كغائب في هذه الأيام. يُسمح بطلب اشتراك جديد فقط في الأيام التي يُسجل فيها السائق غيابه."
            ]);
    }

    /**
     * 3. طفل لديه اشتراك نشط، ولكن السائق مسجل لغيابه في كل تلك الأيام: يُسمح لولي الأمر بإرسال طلب اشتراك بديل.
     */
    public function test_parent_can_create_request_if_driver_has_logged_absence_for_all_requested_days(): void
    {
        $startDate = Carbon::now()->addDays(2)->startOfWeek(Carbon::SUNDAY)->toDateString();
        $day1 = $startDate;
        $day2 = Carbon::parse($startDate)->addDay()->toDateString();
        $endDate = $day2;

        // اشتراك نشط للطفل مع السائق الأول للأسبوع كاملاً
        $activeEnd = Carbon::parse($startDate)->addDays(4)->toDateString();
        $this->createActiveSubscriptionForChild($this->child1, $this->driver1, $startDate, $activeEnd);

        // السائق الأول يسجل غيابه في اليومين الأولين (day1 و day2)
        DriverAbsence::create(['driver_id' => $this->driver1->id, 'absence_date' => $day1]);
        DriverAbsence::create(['driver_id' => $this->driver1->id, 'absence_date' => $day2]);

        // ولي الأمر يطلب اشتراكاً بديلاً مع السائق الثاني لليومين اللذين يغيب فيهما السائق الأول
        $payload = [
            'driver_id' => $this->driver2->id,
            'children'  => [
                [
                    'child_id'          => $this->child1->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => $day1,
                    'end_date'          => $day2,
                    'price_per_child'   => 60.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson('/api/parent/requests', $payload);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    /**
     * 4. غياب جزئي: السائق مسجل غيابه في يوم واحد فقط من يومين مطلوبين: يتم الرفض مع ذكر اليوم غير المغطى بالغياب.
     */
    public function test_parent_cannot_create_request_if_driver_is_absent_on_some_days_but_present_on_others(): void
    {
        $startDate = Carbon::now()->addDays(2)->startOfWeek(Carbon::SUNDAY)->toDateString();
        $day1 = $startDate;
        $day2 = Carbon::parse($startDate)->addDay()->toDateString();

        $activeEnd = Carbon::parse($startDate)->addDays(4)->toDateString();
        $this->createActiveSubscriptionForChild($this->child1, $this->driver1, $startDate, $activeEnd);

        // السائق الأول مسجل غيابه في day1 فقط وليس day2
        DriverAbsence::create(['driver_id' => $this->driver1->id, 'absence_date' => $day1]);

        // ولي الأمر يطلب يومي day1 و day2
        $payload = [
            'driver_id' => $this->driver2->id,
            'children'  => [
                [
                    'child_id'          => $this->child1->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => $day1,
                    'end_date'          => $day2,
                    'price_per_child'   => 60.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson('/api/parent/requests', $payload);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $data = $response->json();
        $this->assertStringContainsString($day2, $data['message']);
        $this->assertStringNotContainsString($day1, $data['message']); // day1 مسموح لأنه غائب
    }

    /**
     * 5. طلب يحتوي على طفلين: الأول ليس لديه تعارض والثاني لديه تعارض بدون غياب للسائق: يتم الرفض وتحديد الطفل الثاني.
     */
    public function test_multiple_children_fails_if_one_child_has_conflict(): void
    {
        $startDate = Carbon::now()->addDays(2)->startOfWeek(Carbon::SUNDAY)->toDateString();
        $endDate   = Carbon::parse($startDate)->addDays(4)->toDateString();

        // الطفل الثاني فقط لديه اشتراك نشط مع السائق الأول بدون غياب
        $this->createActiveSubscriptionForChild($this->child2, $this->driver1, $startDate, $endDate);

        $payload = [
            'driver_id' => $this->driver2->id,
            'children'  => [
                [
                    'child_id'          => $this->child1->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => $startDate,
                    'end_date'          => $endDate,
                    'price_per_child'   => 150.00,
                ],
                [
                    'child_id'          => $this->child2->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => $startDate,
                    'end_date'          => $endDate,
                    'price_per_child'   => 150.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson('/api/parent/requests', $payload);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $data = $response->json();
        $this->assertStringContainsString($this->child2->full_name, $data['message']);
    }

    /**
     * 6. طلب لأيام مستقبلية بعد انتهاء الاشتراك النشط: يتم الإرسال بنجاح لعدم وجود تداخل.
     */
    public function test_parent_can_create_request_for_future_dates_after_active_subscription_ends(): void
    {
        $activeStart = Carbon::now()->addDays(2)->startOfWeek(Carbon::SUNDAY)->toDateString();
        $activeEnd   = Carbon::parse($activeStart)->addDays(4)->toDateString();

        $this->createActiveSubscriptionForChild($this->child1, $this->driver1, $activeStart, $activeEnd);

        // طلب للأسبوع التالي
        $nextWeekStart = Carbon::parse($activeStart)->addWeek()->toDateString();
        $nextWeekEnd   = Carbon::parse($nextWeekStart)->addDays(4)->toDateString();

        $payload = [
            'driver_id' => $this->driver2->id,
            'children'  => [
                [
                    'child_id'          => $this->child1->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => $nextWeekStart,
                    'end_date'          => $nextWeekEnd,
                    'price_per_child'   => 150.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson('/api/parent/requests', $payload);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    }
}
