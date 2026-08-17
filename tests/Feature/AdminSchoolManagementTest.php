<?php

namespace Tests\Feature;

use App\Models\Admin\Admin;
use App\Models\Parent\Child;
use App\Models\Parent\Parents;
use App\Models\Parent\School;
use App\Models\Shared\Municipality;
use App\Models\Shared\SubMunicipality;
use App\Models\Shared\Zone;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSchoolManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'full_name'     => 'مدير المدارس للاختبار',
            'email'         => 'school.admin.' . uniqid() . '@darby.test',
            'phone_number'  => '09' . rand(10000000, 99999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);

        Admin::create(['user_id' => $this->adminUser->id, 'created_by' => $this->adminUser->id]);

        $municipality = Municipality::create(['name' => 'بلدية اختبار ' . uniqid()]);
        $sub = SubMunicipality::create(['municipality_id' => $municipality->id, 'name' => 'محلة اختبار ' . uniqid()]);
        $this->zone = Zone::create(['sub_municipality_id' => $sub->id, 'name' => 'منطقة اختبار ' . uniqid()]);
    }

    private function createSchool(array $overrides = []): School
    {
        return School::create(array_merge([
            'name'         => 'مدرسة اختبار ' . uniqid(),
            'lat'          => 32.8872,
            'lng'          => 13.1913,
            'address'      => 'طرابلس - حي الأندلس',
            'status'       => 'active',
            'zone_id'      => $this->zone->id,
        ], $overrides));
    }

    /**
     * اختبار حذف مدرسة شاغرة بنجاح
     */
    public function test_admin_can_delete_school_without_dependencies(): void
    {
        $school = $this->createSchool();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/admin/schools/{$school->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'تم حذف المدرسة من النظام بنجاح.'
            ]);

        $this->assertDatabaseMissing('schools', ['id' => $school->id]);
    }

    /**
     * اختبار منع حذف مدرسة مرتبطة بأطفال مسجلين
     */
    public function test_admin_cannot_delete_school_with_enrolled_children(): void
    {
        $school = $this->createSchool();

        $parentUser = User::create([
            'full_name'     => 'ولي أمر للاختبار',
            'email'         => 'parent.' . uniqid() . '@darby.test',
            'phone_number'  => '09' . rand(10000000, 99999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);
        $parent = \App\Models\Parent\ParentModel::create(['user_id' => $parentUser->id]);

        Child::create([
            'parent_id'           => $parent->id,
            'school_id'           => $school->id,
            'full_name'           => 'طفل تجريبي',
            'birth_date'          => '2016-05-10',
            'gender'              => 'male',
            'grade'               => 3,
            'qr_code_token'       => 'QR_' . uniqid(),
            'notification_radius' => 500,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/admin/schools/{$school->id}");

        $response->assertStatus(422)
            ->assertJson([
                'status'     => false,
                'error_code' => 'SCHOOL_IN_USE',
            ]);

        $this->assertDatabaseHas('schools', ['id' => $school->id]);
    }

    /**
     * اختبار إرجاع 404 عند محاولة حذف مدرسة غير موجودة
     */
    public function test_delete_non_existent_school_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/admin/schools/999999");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'عذراً، المدرسة المطلوبة غير موجودة في النظام.'
            ]);
    }

    /**
     * اختبار منع حذف مدرسة مرتبطة بمحطات مسارات
     */
    public function test_admin_cannot_delete_school_with_route_stops(): void
    {
        $school = $this->createSchool();

        $driverUser = User::create([
            'full_name'     => 'سائق للاختبار',
            'email'         => 'driver.' . uniqid() . '@darby.test',
            'phone_number'  => '09' . rand(10000000, 99999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 4,
            'is_active'     => 1,
        ]);
        $driver = \App\Models\Driver\Driver::create([
            'user_id'     => $driverUser->id,
            'current_lat' => 32.8872,
            'current_lng' => 13.1913,
        ]);

        $vehicleId = DB::table('vehicles')->insertGetId([
            'driver_id'       => $driver->id,
            'brand'           => 'تويوتا',
            'model'           => 'هايس',
            'year'            => 2023,
            'color'           => 'أبيض',
            'plate_number'    => 'SCH-' . rand(1000, 9999),
            'capacity_manual' => 14,
            'capacity_ai'     => 14,
            'status'          => 'Active',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $route = \App\Models\Shared\Route::create([
            'driver_id'   => $driver->id,
            'vehicle_id'  => $vehicleId,
            'route_name'  => 'مسار الصباح للاختبار',
            'route_type'  => 'Morning',
            'start_time'  => '06:30:00',
            'shift_slot'  => 'morning_go',
            'status'      => 'Active',
            'is_active'   => true,
        ]);

        \App\Models\Shared\RouteStop::create([
            'route_id'       => $route->id,
            'school_id'      => $school->id,
            'stop_type'      => 'school',
            'lat'            => 32.8872,
            'lng'            => 13.1913,
            'label'          => 'محطة المدرسة',
            'sequence_order' => 1,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/admin/schools/{$school->id}");

        $response->assertStatus(422)
            ->assertJson([
                'status'     => false,
                'error_code' => 'SCHOOL_IN_USE',
            ]);

        $this->assertDatabaseHas('schools', ['id' => $school->id]);
    }

    /**
     * اختبار جلب تفاصيل مدرسة
     */
    public function test_admin_can_show_school(): void
    {
        $school = $this->createSchool();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/admin/schools/{$school->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $school->id)
            ->assertJsonPath('data.name', $school->name);
    }

    /**
     * اختبار تعديل مدرسة
     */
    public function test_admin_can_update_school(): void
    {
        $school = $this->createSchool();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/admin/schools/{$school->id}", [
                'name'    => 'مدرسة النخبة الحديثة',
                'lat'     => 32.8900,
                'lng'     => 13.2000,
                'address' => 'طرابلس - النوفليين',
                'zone_id' => $this->zone->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'مدرسة النخبة الحديثة');

        $this->assertDatabaseHas('schools', [
            'id'   => $school->id,
            'name' => 'مدرسة النخبة الحديثة',
        ]);
    }
}
