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

/**
 * اختبار عرض واسترجاع طلبات الاشتراكات الخاصة بالسائق
 *
 * يستخدم DatabaseTransactions: جميع العمليات تُلغى تلقائياً بعد الاختبار لحماية قاعدة البيانات الحقيقية.
 */
class DriverSubscriptionListingTest extends TestCase
{
    use DatabaseTransactions;

    protected User              $driverUser;
    protected Driver            $driver;
    protected User              $parentUser;
    protected ParentModel       $parent;
    protected Child             $child;
    protected School            $school;
    protected SubscriptionRequest $pendingRequest;
    protected SubscriptionRequest $rejectedRequest;

    protected function setUp(): void
    {
        parent::setUp();

        // إدراج الأدوار
        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        // 1. حساب السائق
        $this->driverUser = User::create([
            'full_name'    => 'سائق العرض',
            'email'        => 'driver.listing.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('password123'),
            'role_id'      => 2,
            'is_active'    => 1,
        ]);

        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        // 2. حساب ولي الأمر
        $this->parentUser = User::create([
            'full_name'    => 'ولي أمر العرض',
            'email'        => 'parent.listing.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('password123'),
            'role_id'      => 3,
            'is_active'    => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        // 3. مدرسة الاختبار
        $this->school = School::create([
            'name'    => 'مدرسة النور',
            'address' => 'حي الأندلس، طرابلس',
            'lat'     => 32.8870,
            'lng'     => 13.1890,
            'status'  => 'active',
        ]);

        // 4. عنوان ولي الأمر
        $addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'البيت الرئيسي',
            'lat'        => 32.8810,
            'lng'        => 13.1850,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. الطفل
        $this->child = Child::create([
            'parent_id' => $this->parent->id,
            'full_name' => 'سارة أحمد',
            'birth_date'=> '2017-03-15',
            'gender'    => 'female',
            'grade'     => 2,
            'notification_radius' => 500,
        ]);

        // 6. طلب معلّق
        $this->pendingRequest = SubscriptionRequest::create([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'school_id'         => $this->school->id,
            'subscription_type' => 'monthly',
            'direction'         => 'both',
            'timing'            => 'MORNING',
            'start_date'        => now()->addDays(1)->format('Y-m-d'),
            'end_date'          => now()->addMonths(1)->format('Y-m-d'),
            'days_count'        => 22,
            'total_price'       => 250.00,
            'pickup_time'       => '07:15:00',
            'dropoff_time'      => '14:30:00',
            'max_waiting_time'  => 15,
            'status'            => SubscriptionRequest::STATUS_PENDING,
            'children_count'    => 1,
        ]);

        DB::table('request_children')->insert([
            'request_id'         => $this->pendingRequest->id,
            'child_id'           => $this->child->id,
            'pickup_address_id'  => $addressId,
            'dropoff_address_id' => $this->school->id,
            'home_lat'           => 32.8810,
            'home_lng'           => 13.1850,
            'home_label'         => 'البيت الرئيسي',
            'school_lat'         => 32.8870,
            'school_lng'         => 13.1890,
            'school_label'       => 'مدرسة النور',
            'price_per_child'    => 250.00,
        ]);

        // 7. طلب مرفوض
        $this->rejectedRequest = SubscriptionRequest::create([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'school_id'         => $this->school->id,
            'subscription_type' => 'monthly',
            'direction'         => 'go',
            'timing'            => 'EVENING',
            'start_date'        => now()->addDays(1)->format('Y-m-d'),
            'end_date'          => now()->addMonths(1)->format('Y-m-d'),
            'days_count'        => 22,
            'total_price'       => 150.00,
            'status'            => SubscriptionRequest::STATUS_REJECTED,
            'rejection_reason'  => 'خارج التغطية',
            'children_count'    => 1,
        ]);
    }

    // =========================================================
    // 1. اختبار عرض قائمة الطلبات للسائق
    // =========================================================
    public function test_driver_can_list_all_their_subscription_requests(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/driver/requests');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'count',
            'data' => [
                '*' => [
                    'id',
                    'status',
                    'start_date',
                    'working_days_count',
                    'total_amount',
                    'children_count',
                    'parent' => ['id', 'name', 'phone'],
                    'driver' => ['id', 'name', 'phone'],
                    'children',
                ]
            ]
        ]);

        $this->assertGreaterThanOrEqual(2, $response->json('count'));
    }

    // =========================================================
    // 2. اختبار فلترة الطلبات حسب الحالة (pending / rejected)
    // =========================================================
    public function test_driver_can_filter_subscription_requests(): void
    {
        // فلترة الطلبات المعلقة
        $pendingResponse = $this->actingAs($this->driverUser)
            ->getJson('/api/driver/requests?filter=pending');

        $pendingResponse->assertStatus(200);
        $pendingResponse->assertJsonPath('success', true);
        
        $pendingData = $pendingResponse->json('data');
        foreach ($pendingData as $item) {
            $this->assertEquals('pending', $item['status']);
        }

        // فلترة الطلبات المرفوضة
        $rejectedResponse = $this->actingAs($this->driverUser)
            ->getJson('/api/driver/requests?filter=rejected');

        $rejectedResponse->assertStatus(200);
        $rejectedResponse->assertJsonPath('success', true);

        $rejectedData = $rejectedResponse->json('data');
        foreach ($rejectedData as $item) {
            $this->assertEquals('rejected', $item['status']);
        }
    }

    // =========================================================
    // 3. اختبار عرض تفاصيل طلب معين
    // =========================================================
    public function test_driver_can_view_single_request_details(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/driver/requests/{$this->pendingRequest->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $this->pendingRequest->id);
        $response->assertJsonPath('data.parent.name', 'ولي أمر العرض');
    }

    // =========================================================
    // 4. اختبار منع السائق من عرض تفاصيل طلب غير مخصص له
    // =========================================================
    public function test_driver_cannot_view_request_belonging_to_another_driver(): void
    {
        $otherDriverUser = User::create([
            'full_name'    => 'سائق ثاني',
            'email'        => 'other.driver.' . uniqid() . '@darby.test',
            'phone_number' => '096' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('password123'),
            'role_id'      => 2,
            'is_active'    => 1,
        ]);

        Driver::create([
            'user_id'        => $otherDriverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        $response = $this->actingAs($otherDriverUser)
            ->getJson("/api/driver/requests/{$this->pendingRequest->id}");

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
    }

    // =========================================================
    // 5. اختبار عرض تفاصيل الرحلة لطلب الاشتراك
    // =========================================================
    public function test_driver_can_view_trip_details_of_request(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/driver/requests/{$this->pendingRequest->id}/trip-details");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.request_id', $this->pendingRequest->id);
        $response->assertJsonPath('data.parent.name', 'ولي أمر العرض');
        $response->assertJsonPath('data.school.name', 'مدرسة النور');
        $response->assertJsonPath('data.school.latitude', 32.8870);
        $response->assertJsonPath('data.children.0.name', 'سارة أحمد');
    }

    // =========================================================
    // 6. اختبار منع غير السائق من عرض قائمة الطلبات
    // =========================================================
    public function test_non_driver_cannot_list_driver_requests(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->getJson('/api/driver/requests');

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
    }

    // =========================================================
    // 7. اختبار رفض الطلبات غير الموثوقة (Unauthenticated)
    // =========================================================
    public function test_unauthenticated_user_cannot_list_requests(): void
    {
        $response = $this->getJson('/api/driver/requests');

        $response->assertStatus(401);
    }
}
