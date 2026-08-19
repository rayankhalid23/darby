<?php

namespace Tests\Feature\Notification;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\UserDevice;

class DeviceTokenTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->user = User::create([
            'full_name'     => 'مستخدم اختبار الأجهزة',
            'email'         => 'device.test.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $this->otherUser = User::create([
            'full_name'     => 'مستخدم آخر لاختبار الملكية',
            'email'         => 'device.owner.' . uniqid() . '@darby.test',
            'phone_number'  => '093' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);
    }

    public function test_can_register_a_new_device_token(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/user/device-token', [
            'fcm_token'   => 'token-' . uniqid(),
            'device_id'   => 'device-A',
            'device_name' => 'iPhone 15',
            'platform'    => 'ios',
            'app_version' => '1.2.0',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        $this->assertDatabaseHas('user_devices', [
            'user_id'   => $this->user->id,
            'device_id' => 'device-A',
            'platform'  => 'ios',
            'is_active' => 1,
        ]);
    }

    public function test_repeated_registration_with_same_token_is_idempotent(): void
    {
        $token = 'token-' . uniqid();

        $this->actingAs($this->user)->postJson('/api/user/device-token', [
            'fcm_token' => $token,
            'device_id' => 'device-A',
        ])->assertStatus(200);

        $this->actingAs($this->user)->postJson('/api/user/device-token', [
            'fcm_token' => $token,
            'device_id' => 'device-A',
        ])->assertStatus(200);

        $this->assertEquals(1, UserDevice::where('fcm_token', $token)->count());
    }

    public function test_token_refresh_on_same_device_replaces_old_row_not_duplicates(): void
    {
        $this->actingAs($this->user)->postJson('/api/user/device-token', [
            'fcm_token' => 'old-token-' . uniqid(),
            'device_id' => 'device-A',
        ])->assertStatus(200);

        $newToken = 'new-token-' . uniqid();
        $this->actingAs($this->user)->postJson('/api/user/device-token', [
            'fcm_token' => $newToken,
            'device_id' => 'device-A',
        ])->assertStatus(200);

        $this->assertEquals(1, UserDevice::where('user_id', $this->user->id)->where('device_id', 'device-A')->count());
        $this->assertDatabaseHas('user_devices', ['fcm_token' => $newToken, 'device_id' => 'device-A']);
    }

    public function test_user_can_have_multiple_distinct_devices(): void
    {
        $this->actingAs($this->user)->postJson('/api/user/device-token', [
            'fcm_token' => 'token-a-' . uniqid(),
            'device_id' => 'device-A',
        ])->assertStatus(200);

        $this->actingAs($this->user)->postJson('/api/user/device-token', [
            'fcm_token' => 'token-b-' . uniqid(),
            'device_id' => 'device-B',
        ])->assertStatus(200);

        $this->assertEquals(2, UserDevice::where('user_id', $this->user->id)->count());
    }

    public function test_logout_removes_only_the_specified_device(): void
    {
        $tokenA = 'token-a-' . uniqid();
        $tokenB = 'token-b-' . uniqid();

        $this->actingAs($this->user)->postJson('/api/user/device-token', [
            'fcm_token' => $tokenA,
            'device_id' => 'device-A',
        ]);
        $this->actingAs($this->user)->postJson('/api/user/device-token', [
            'fcm_token' => $tokenB,
            'device_id' => 'device-B',
        ]);

        $response = $this->actingAs($this->user)->deleteJson('/api/user/device-token', [
            'device_id' => 'device-A',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('user_devices', ['device_id' => 'device-A', 'user_id' => $this->user->id]);
        $this->assertDatabaseHas('user_devices', ['device_id' => 'device-B', 'user_id' => $this->user->id]);
    }

    public function test_delete_without_device_id_or_fcm_token_fails_validation(): void
    {
        $response = $this->actingAs($this->user)->deleteJson('/api/user/device-token', []);

        $response->assertStatus(422);
    }

    public function test_logout_all_removes_every_device_for_user(): void
    {
        $this->actingAs($this->user)->postJson('/api/user/device-token', [
            'fcm_token' => 'token-a-' . uniqid(),
            'device_id' => 'device-A',
        ]);
        $this->actingAs($this->user)->postJson('/api/user/device-token', [
            'fcm_token' => 'token-b-' . uniqid(),
            'device_id' => 'device-B',
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/user/device-token/logout-all');

        $response->assertStatus(200);
        $this->assertEquals(0, UserDevice::where('user_id', $this->user->id)->count());
    }

    public function test_inactive_device_is_excluded_from_active_lookups(): void
    {
        UserDevice::create([
            'user_id'        => $this->user->id,
            'device_id'      => 'device-inactive',
            'fcm_token'      => 'token-inactive-' . uniqid(),
            'device_name'    => 'Old Phone',
            'platform'       => 'android',
            'is_active'      => false,
            'last_active_at' => now(),
        ]);

        $activeCount = UserDevice::where('user_id', $this->user->id)->where('is_active', true)->count();
        $this->assertEquals(0, $activeCount);
    }

    // --- Ownership policy: a user cannot hijack another user's device by replaying their fcm_token ---

    public function test_cannot_claim_token_owned_by_another_user_with_a_different_device_id(): void
    {
        $token = 'owned-token-' . uniqid();

        // otherUser genuinely owns this token on their own physical device.
        $this->actingAs($this->otherUser)->postJson('/api/user/device-token', [
            'fcm_token' => $token,
            'device_id' => 'other-users-real-device',
        ])->assertStatus(200);

        // this->user replays the same token, claiming a DIFFERENT device_id -> must be rejected.
        $response = $this->actingAs($this->user)->postJson('/api/user/device-token', [
            'fcm_token' => $token,
            'device_id' => 'attacker-own-device',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'DEVICE_TOKEN_CONFLICT');

        // ownership must NOT have changed.
        $this->assertDatabaseHas('user_devices', [
            'fcm_token' => $token,
            'user_id'   => $this->otherUser->id,
            'device_id' => 'other-users-real-device',
        ]);
    }

    public function test_can_claim_token_when_device_id_matches_previous_owner_shared_device_handover(): void
    {
        $token = 'shared-device-token-' . uniqid();
        $deviceId = 'shared-physical-device-001';

        // otherUser (e.g. previous account) was logged in on this physical device.
        $this->actingAs($this->otherUser)->postJson('/api/user/device-token', [
            'fcm_token' => $token,
            'device_id' => $deviceId,
        ])->assertStatus(200);

        // otherUser logs out, this->user logs into the SAME physical device (same device_id)
        // and the app resubmits the same still-valid FCM token -> legitimate handover, must succeed.
        $response = $this->actingAs($this->user)->postJson('/api/user/device-token', [
            'fcm_token' => $token,
            'device_id' => $deviceId,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('user_devices', [
            'fcm_token' => $token,
            'user_id'   => $this->user->id,
            'device_id' => $deviceId,
        ]);
        $this->assertDatabaseMissing('user_devices', [
            'fcm_token' => $token,
            'user_id'   => $this->otherUser->id,
        ]);
    }
}
