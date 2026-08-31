<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Shared\DatabaseNotification;
use Illuminate\Support\Str;

class ComprehensiveRoleNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin',  'display_name' => 'مدير'],
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        // 1. حساب الأدمن
        $this->adminUser = User::create([
            'full_name'     => 'مدير النظام للتنبيهات',
            'email'         => 'admin.notif.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);
        DB::table('admins')->insertOrIgnore(['user_id' => $this->adminUser->id]);

        // 2. حساب السائق
        $this->driverUser = User::create([
            'full_name'     => 'سائق التنبيهات التجريبي',
            'email'         => 'driver.notif.' . uniqid() . '@darby.test',
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
        ]);

        // 3. حساب ولي الأمر
        $this->parentUser = User::create([
            'full_name'     => 'ولي أمر التنبيهات التجريبي',
            'email'         => 'parent.notif.' . uniqid() . '@darby.test',
            'phone_number'  => '093' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);
        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);
    }

    protected function createSampleNotification(User $user, string $type, string $title, string $message): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id'              => $id,
            'type'            => 'App\\Notifications\\GenericAppNotification',
            'notifiable_type' => User::class,
            'notifiable_id'   => $user->id,
            'data'            => json_encode([
                'type'        => $type,
                'title'       => $title,
                'message'     => $message,
                'screen'      => 'HOME',
                'action'      => 'open',
                'entity_type' => 'trip',
                'entity_id'   => '101',
            ]),
            'read_at'         => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        return $id;
    }

    // ==========================================
    // 1. اختبارات إشعارات السائق (Driver)
    // ==========================================

    public function test_driver_can_fetch_notifications_and_unread_count(): void
    {
        $notifId = $this->createSampleNotification($this->driverUser, 'trip_ready', 'رحلة جديدة جاهزة', 'تم تجهيز مسار رحلتك الصباحية.');

        // 1. جلب الإشعارات عبر المسار العام
        $res1 = $this->actingAs($this->driverUser)->getJson('/api/notifications');
        $res1->assertStatus(200);
        $res1->assertJsonPath('status', true);
        $res1->assertJsonPath('data.unread_count', 1);

        // 2. جلب الإشعارات عبر مسار السائق المخصص
        $res2 = $this->actingAs($this->driverUser)->getJson('/api/driver/notifications');
        $res2->assertStatus(200);
        $res2->assertJsonPath('status', true);
        $res2->assertJsonPath('data.unread_count', 1);

        // 3. عدد غير المقروء (Badge)
        $resBadge = $this->actingAs($this->driverUser)->getJson('/api/driver/notifications/unread-count');
        $resBadge->assertStatus(200);
        $resBadge->assertJsonPath('unread_count', 1);

        // 4. تعليم كمقروء
        $resRead = $this->actingAs($this->driverUser)->postJson("/api/driver/notifications/{$notifId}/read");
        $resRead->assertStatus(200);

        $resBadgeAfter = $this->actingAs($this->driverUser)->getJson('/api/driver/notifications/unread-count');
        $resBadgeAfter->assertJsonPath('unread_count', 0);
    }

    // ==========================================
    // 2. اختبارات إشعارات ولي الأمر (Parent)
    // ==========================================

    public function test_parent_can_fetch_notifications_and_mark_all_read(): void
    {
        $notif1 = $this->createSampleNotification($this->parentUser, 'child_boarded', 'صعود الطفل', 'صعد طفلك الحافلة بنجاح.');
        $notif2 = $this->createSampleNotification($this->parentUser, 'trip_completed', 'اكتمال الرحلة', 'وصل الطفل المدرسة بسلام.');

        // جلب الإشعارات عبر مسار ولي الأمر
        $res = $this->actingAs($this->parentUser)->getJson('/api/parent/notifications');
        $res->assertStatus(200);
        $res->assertJsonPath('status', true);
        $res->assertJsonPath('data.unread_count', 2);

        // تمييز الكل كمقروء
        $resReadAll = $this->actingAs($this->parentUser)->postJson('/api/parent/notifications/read-all');
        $resReadAll->assertStatus(200);

        $resBadge = $this->actingAs($this->parentUser)->getJson('/api/parent/notifications/unread-count');
        $resBadge->assertJsonPath('unread_count', 0);

        // حذف إشعار
        $resDelete = $this->actingAs($this->parentUser)->deleteJson("/api/parent/notifications/{$notif1}");
        $resDelete->assertStatus(200);
    }

    // ==========================================
    // 3. اختبارات إشعارات لوحة تحكم الأدمن (Admin)
    // ==========================================

    public function test_admin_can_fetch_notifications_and_manage_them(): void
    {
        $notifId = $this->createSampleNotification($this->adminUser, 'driver_absence_requested', 'طلب غياب سائق', 'قدم الكابتن طلب غياب جديد.');

        // جلب إشعارات الأدمن
        $res = $this->actingAs($this->adminUser)->getJson('/api/admin/notifications');
        $res->assertStatus(200);
        $res->assertJsonPath('status', true);
        $res->assertJsonPath('data.unread_count', 1);

        // عدد غير المقروء للأدمن
        $resCount = $this->actingAs($this->adminUser)->getJson('/api/admin/notifications/unread-count');
        $resCount->assertStatus(200);
        $resCount->assertJsonPath('unread_count', 1);

        // تعليم كمقروء
        $resRead = $this->actingAs($this->adminUser)->postJson("/api/admin/notifications/{$notifId}/read");
        $resRead->assertStatus(200);

        // حذف الإشعار
        $resDelete = $this->actingAs($this->adminUser)->deleteJson("/api/admin/notifications/{$notifId}");
        $resDelete->assertStatus(200);
    }

    // ==========================================
    // 4. اختبارات تسجيل أجهزة FCM (Device Token)
    // ==========================================

    public function test_user_can_register_and_remove_fcm_device_token(): void
    {
        $deviceId = 'device_test_' . uniqid();
        $fcmToken = 'fcm_token_sample_' . Str::random(32);

        // تسجيل التوكن
        $resStore = $this->actingAs($this->driverUser)->postJson('/api/user/device-token', [
            'fcm_token'   => $fcmToken,
            'device_id'   => $deviceId,
            'device_name' => 'Pixel 8 Pro',
            'platform'    => 'android',
        ]);
        $resStore->assertStatus(200);
        $resStore->assertJsonPath('status', true);

        $this->assertDatabaseHas('user_devices', [
            'user_id'   => $this->driverUser->id,
            'device_id' => $deviceId,
            'fcm_token' => $fcmToken,
        ]);

        // إزالة التوكن عند تسجيل الخروج
        $resRemove = $this->actingAs($this->driverUser)->deleteJson('/api/user/device-token', [
            'device_id' => $deviceId,
        ]);
        $resRemove->assertStatus(200);
        $resRemove->assertJsonPath('status', true);

        $this->assertDatabaseMissing('user_devices', [
            'user_id'   => $this->driverUser->id,
            'device_id' => $deviceId,
        ]);
    }
}
