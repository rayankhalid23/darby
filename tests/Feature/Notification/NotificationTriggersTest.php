<?php

namespace Tests\Feature\Notification;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Parent\School;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\Contract;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Trip;
use App\Services\Shared\SubscriptionRequestService;
use App\Services\Trip\TripLifecycleService;
use App\Services\Admin\AdminDriverService;
use App\Jobs\SendFcmNotificationJob;
use Illuminate\Notifications\DatabaseNotification;
use Mockery;

/**
 * يتحقق أن نقاط الإشعار الأساسية (بعد ترحيلها إلى NotificationService) تُنشئ فعلياً
 * صف database notification صحيح وتوزّع SendFcmNotificationJob بالبيانات الصحيحة.
 */
class NotificationTriggersTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;
    protected Child $child;
    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->driverUser = User::create([
            'full_name'     => 'سائق اختبار الإشعارات',
            'email'         => 'trg.driver.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
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
            'morning_go'     => true,
            'morning_return' => true,
        ]);

        DB::table('driver_seat_slots')->insert([
            ['driver_id' => $this->driver->id, 'slot' => 'morning_go', 'total_seats' => 10, 'reserved_seats' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['driver_id' => $this->driver->id, 'slot' => 'morning_return', 'total_seats' => 10, 'reserved_seats' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('vehicles')->insert([
            'driver_id'       => $this->driver->id,
            'brand'           => 'تويوتا',
            'model'           => 'هايس',
            'year'            => 2022,
            'color'           => 'أبيض',
            'plate_number'    => 'TRG-' . rand(1000, 9999),
            'capacity_manual' => 10,
            'capacity_ai'     => 10,
            'status'          => 'Active',
            'deleted_at'      => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->parentUser = User::create([
            'full_name'     => 'ولي أمر اختبار الإشعارات',
            'email'         => 'trg.parent.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        // قيمة الاشتراك تُفحص وتُحجز لكل الأنواع (وليس اليومي فقط)،
        // لذا يجب أن تكون محفظة ولي الأمر ممولة قبل إرسال الطلب.
        $this->parent->deposit(500000);

        $this->school = School::create([
            'name'    => 'مدرسة اختبار الإشعارات',
            'address' => 'شارع الاختبار',
            'lat'     => 32.9,
            'lng'     => 13.2,
            'status'  => 'active',
        ]);

        $this->child = Child::create([
            'parent_id'            => $this->parent->id,
            'full_name'            => 'طفل اختبار الإشعارات',
            'birth_date'           => '2018-05-10',
            'gender'               => 'male',
            'grade'                => 1,
            'notification_radius'  => 500,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function createSubscriptionRequest(): SubscriptionRequest
    {
        $addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'منزل ولي الأمر',
            'lat'        => 32.88,
            'lng'        => 13.19,
        ]);

        $req = SubscriptionRequest::create([
            'parent_id'   => $this->parent->id,
            'driver_id'   => $this->driver->id,
            'total_price' => 200,
            'status'      => SubscriptionRequest::STATUS_PENDING,
            'notes'       => 'طلب تجريبي للاختبار',
        ]);

        DB::table('request_children')->insert([
            'request_id'         => $req->id,
            'child_id'           => $this->child->id,
            'subscription_type'  => 'monthly',
            'trip_direction'     => 'both',
            'timing'             => 'BOTH',
            'start_date'         => now()->addDay()->format('Y-m-d'),
            'end_date'           => now()->addMonth()->format('Y-m-d'),
            'working_days_count' => 22,
            'distance_km'        => 5.5,
            'trip_price'         => 10,
            'price_per_child'    => 200,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        return $req->fresh();
    }

    public function test_new_subscription_request_notifies_driver(): void
    {
        Queue::fake();

        $addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'home',
            'lat'        => 32.88,
            'lng'        => 13.19,
        ]);

        $service = app(SubscriptionRequestService::class);
        $service->createRequest([
            'driver_id'   => $this->driver->id,
            'total_price' => 200,
            'children'    => [
                [
                    'child_id'           => $this->child->id,
                    'subscription_type'  => 'monthly',
                    'trip_direction'     => 'both',
                    'timing'             => 'BOTH',
                    'start_date'         => now()->addDay()->format('Y-m-d'),
                    'end_date'           => now()->addMonth()->format('Y-m-d'),
                    'price_per_child'    => 200,
                ],
            ],
        ], $this->parentUser->id);

        $notif = DatabaseNotification::where('notifiable_id', $this->driverUser->id)
            ->where('data->type', 'new_subscription_request')
            ->first();

        $this->assertNotNull($notif);
        Queue::assertPushed(SendFcmNotificationJob::class, fn ($job) => $job->userId === $this->driverUser->id);
    }

    public function test_request_rejected_notifies_parent(): void
    {
        Queue::fake();
        $req = $this->createSubscriptionRequest();

        app(SubscriptionRequestService::class)->updateStatus($req, 'rejected', 'لا توجد مقاعد متاحة');

        $notif = DatabaseNotification::where('notifiable_id', $this->parentUser->id)
            ->where('data->type', 'request_rejected')
            ->first();

        $this->assertNotNull($notif);
        Queue::assertPushed(SendFcmNotificationJob::class, fn ($job) => $job->userId === $this->parentUser->id);
    }

    public function test_driver_account_approved_notifies_driver(): void
    {
        Queue::fake();

        $changeId = DB::table('driver_profile_changes')->insertGetId([
            'driver_id'   => $this->driver->id,
            'new_values'  => json_encode(['full_name' => 'اسم محدّث']),
            'status'      => 'Pending',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        app(AdminDriverService::class)->reviewProfileChangeRequest($changeId, 'Approved', null, 1);

        $notif = DatabaseNotification::where('notifiable_id', $this->driverUser->id)
            ->where('data->type', 'driver_account_approved')
            ->first();

        $this->assertNotNull($notif);
        Queue::assertPushed(SendFcmNotificationJob::class, fn ($job) => $job->userId === $this->driverUser->id);
    }

    public function test_trip_completed_notifies_subscribed_parents(): void
    {
        Queue::fake();

        $trip = Trip::create([
            'driver_id'            => $this->driver->id,
            'route_id'             => null,
            'trip_type'            => 'Morning',
            'shift_slot'           => 'morning_go',
            'status'               => 'in_progress',
            'scheduled_at'         => now(),
            'scheduled_start_time' => now(),
            'trip_date'            => now()->toDateString(),
        ]);

        $subReq = $this->createSubscriptionRequest();

        ActiveSubscription::create([
            'subscription_request_id' => $subReq->id,
            'status'        => 'active',
            'child_id'      => $this->child->id,
            'driver_id'     => $this->driver->id,
            'parent_id'     => $this->parentUser->id,
            'pickup_lat'    => 32.88,
            'pickup_lng'    => 13.19,
            'pickup_label'  => 'home',
            'pickup_time'   => '07:00:00',
            'dropoff_lat'   => 32.90,
            'dropoff_lng'   => 13.20,
            'dropoff_label' => 'school',
            'dropoff_time'  => '14:00:00',
        ]);

        app(TripLifecycleService::class)->completeTrip($trip->id);

        $notif = DatabaseNotification::where('notifiable_id', $this->parentUser->id)
            ->where('data->type', 'trip_completed')
            ->first();

        $this->assertNotNull($notif);
        $this->assertEquals((string) $trip->id, $notif->data['entity_id']);
        Queue::assertPushed(SendFcmNotificationJob::class, fn ($job) => $job->userId === $this->parentUser->id);
    }
}
