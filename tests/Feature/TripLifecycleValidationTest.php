<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Parent\Child;
use App\Models\Driver\Driver;
use App\Models\Shared\Subscription;
use App\Models\Shared\Trip;

class TripLifecycleValidationTest extends TestCase
{
    use RefreshDatabase;

    protected $driver;
    protected $child;
    protected $trip;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        // 1. إنشاء مستخدم متوافق مع جدول users
        $user = User::create([
            'full_name'     => 'صلاح الدين السائق',
            'email'         => 'driver.' . uniqid() . '@darbi.com',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 2, 
            'is_active'     => 1,
        ]);

        // 2. إنشاء السائق وربطه بالمستخدم
        $this->driver = Driver::create([
            'user_id'        => $user->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
            'current_lat'    => 32.8872,
            'current_lng'    => 13.1932,
        ]);

        // 3. إنشاء ولي أمر
        $parent = User::create([
            'full_name'     => 'ولي الأمر الوهمي',
            'email'         => 'parent.' . uniqid() . '@darbi.com',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3, 
            'is_active'     => 1,
        ]);

        // 4. إنشاء الطفل
        $this->child = Child::create([
            'parent_id'     => $parent->id,
            'full_name'     => 'أحمد صلاح',
            'birth_date'    => '2018-05-10',
            'gender'        => 'male',
            'grade'         => 1,
            'notification_radius' => 500,
            'qr_code_token' => 'VALID_QR_TOKEN_123',
        ]);

        // 5. إنشاء رحلة نشطة
        $this->trip = Trip::create([
            'driver_id' => $this->driver->id,
            'status'    => 'active',
        ]);
    }

    public function test_driver_cannot_interact_with_unsubscribed_child()
    {
        $response = $this->actingAs($this->driver->user)
            ->postJson("/api/driver/trips/{$this->trip->id}/verify-qr", [
                'qr_code_token' => 'VALID_QR_TOKEN_123',
                'child_id'      => $this->child->id
            ]);

        $response->assertStatus(403);
    }

    public function test_driver_cannot_verify_qr_if_geographically_far()
    {
        Subscription::create([
            'child_id'   => $this->child->id,
            'parent_id'  => $this->child->parent_id,
            'driver_id'  => $this->driver->id,
            'status'     => 'active',
            'start_date' => now()->format('Y-m-d'),
            'end_date'   => now()->addMonths(3)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->driver->user)
            ->postJson("/api/driver/trips/{$this->trip->id}/verify-qr", [
                'qr_code_token' => 'VALID_QR_TOKEN_123',
                'child_id'      => $this->child->id,
                'driver_lat'    => 31.0000,
                'driver_lng'    => 12.0000
            ]);

        $response->assertStatus(422);
    }

    public function test_driver_cannot_verify_incorrect_qr_code()
    {
        Subscription::create([
            'child_id'   => $this->child->id,
            'parent_id'  => $this->child->parent_id,
            'driver_id'  => $this->driver->id,
            'status'     => 'active',
            'start_date' => now()->format('Y-m-d'),
            'end_date'   => now()->addMonths(3)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->driver->user)
            ->postJson("/api/driver/trips/{$this->trip->id}/verify-qr", [
                'qr_code_token' => 'WRONG_QR_TOKEN',
                'child_id'      => $this->child->id,
                'driver_lat'    => 32.8872,
                'driver_lng'    => 13.1932
            ]);

        $response->assertStatus(400);
    }

    public function test_driver_can_verify_qr_successfully_with_all_conditions_met()
    {
        Subscription::create([
            'child_id'   => $this->child->id,
            'parent_id'  => $this->child->parent_id,
            'driver_id'  => $this->driver->id,
            'status'     => 'active',
            'start_date' => now()->format('Y-m-d'),
            'end_date'   => now()->addMonths(3)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->driver->user)
            ->postJson("/api/driver/trips/{$this->trip->id}/verify-qr", [
                'qr_code_token' => 'VALID_QR_TOKEN_123',
                'child_id'      => $this->child->id,
                'driver_lat'    => 32.8872,
                'driver_lng'    => 13.1932
            ]);

        $response->assertStatus(200);
    }
}