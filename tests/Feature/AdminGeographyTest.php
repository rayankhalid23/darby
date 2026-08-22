<?php

namespace Tests\Feature;

use App\Models\Admin\Admin;
use App\Models\Driver\Driver;
use App\Models\Shared\Municipality;
use App\Models\Shared\SubMunicipality;
use App\Models\Shared\Zone;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 🧪 اختبارات إدارة الجغرافيا: بلدية ← محلة ← منطقة
 *
 * تغطي لكل مستوى: العرض والإضافة والتعديل والحذف، وحمايات الحذف،
 * وإجبارية التبعية للمستوى الأعلى.
 */
class AdminGeographyTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'full_name'     => 'مدير الجغرافيا للاختبار',
            'email'         => 'geo.' . uniqid() . '@darby.test',
            'phone_number'  => '09' . rand(10000000, 99999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);

        Admin::create(['user_id' => $this->adminUser->id, 'created_by' => $this->adminUser->id]);
    }

    private function makeMunicipality(?string $name = null): Municipality
    {
        return Municipality::create(['name' => $name ?? ('بلدية اختبار ' . uniqid())]);
    }

    private function makeSub(Municipality $m, ?string $name = null): SubMunicipality
    {
        return SubMunicipality::create([
            'municipality_id' => $m->id,
            'name'            => $name ?? ('محلة اختبار ' . uniqid()),
        ]);
    }

    private function makeZone(SubMunicipality $s, ?string $name = null): Zone
    {
        return Zone::create([
            'sub_municipality_id' => $s->id,
            'name'                => $name ?? ('منطقة اختبار ' . uniqid()),
        ]);
    }

    /**
     * سائق مخصص لهذا الاختبار فقط (بدل الاعتماد على وجود سائق عرضي في قاعدة
     * البيانات المشتركة، وهو أمر هش لا يمكن ضمانه تحت DatabaseTransactions).
     */
    private function makeDriver(): Driver
    {
        $user = User::create([
            'full_name'     => 'سائق منطقة للاختبار ' . uniqid(),
            'email'         => 'geo.driver.' . uniqid() . '@darby.test',
            'phone_number'  => '09' . rand(10000000, 99999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);

        return Driver::create([
            'user_id'        => $user->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);
    }

    // =====================================================================
    // 🏛️ البلديات
    // =====================================================================

    public function test_can_list_all_municipalities_with_counts(): void
    {
        $m   = $this->makeMunicipality('بلدية القائمة ' . uniqid());
        $sub = $this->makeSub($m);
        $this->makeZone($sub);
        $this->makeZone($sub);

        $response = $this->actingAs($this->adminUser)->getJson('/api/admin/municipalities');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'تم جلب قائمة البلديات بنجاح.');
        $response->assertJsonStructure([
            'status', 'message',
            'data' => [['id', 'name', 'sub_municipalities_count', 'zones_count', 'created_at', 'updated_at']],
        ]);

        $row = collect($response->json('data'))->firstWhere('id', $m->id);
        $this->assertNotNull($row);
        $this->assertEquals(1, $row['sub_municipalities_count']);
        $this->assertEquals(2, $row['zones_count']);
    }

    public function test_municipalities_list_supports_search(): void
    {
        $unique = 'بلدية فريدة ' . uniqid();
        $m      = $this->makeMunicipality($unique);
        $this->makeMunicipality();

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/municipalities?search=' . urlencode($unique));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.id', $m->id);
    }

    public function test_can_create_municipality(): void
    {
        $name = 'بلدية جديدة ' . uniqid();

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/municipalities', ['name' => $name]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'تم إضافة البلدية بنجاح.');
        $response->assertJsonPath('data.name', $name);
        $response->assertJsonPath('data.zones_count', 0);

        $this->assertDatabaseHas('municipalities', ['name' => $name]);
    }

    public function test_municipality_name_is_required_and_unique(): void
    {
        $existing = $this->makeMunicipality();

        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/municipalities', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/municipalities', ['name' => $existing->name])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_show_municipality_with_its_sub_municipalities(): void
    {
        $m    = $this->makeMunicipality();
        $sub  = $this->makeSub($m, 'محلة معروضة ' . uniqid());
        $this->makeZone($sub);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/municipalities/' . $m->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $m->id);
        $response->assertJsonPath('data.sub_municipalities_count', 1);
        $response->assertJsonPath('data.zones_count', 1);
        $response->assertJsonPath('data.sub_municipalities.0.id', $sub->id);
        $response->assertJsonPath('data.sub_municipalities.0.zones_count', 1);
    }

    public function test_can_update_municipality_name(): void
    {
        $m       = $this->makeMunicipality();
        $newName = 'اسم بلدية معدل ' . uniqid();

        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/municipalities/' . $m->id, ['name' => $newName])
            ->assertStatus(200)
            ->assertJsonPath('data.name', $newName);

        $this->assertDatabaseHas('municipalities', ['id' => $m->id, 'name' => $newName]);
    }

    public function test_can_delete_empty_municipality_and_it_cascades(): void
    {
        $m   = $this->makeMunicipality();
        $sub = $this->makeSub($m);
        $z   = $this->makeZone($sub);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson('/api/admin/municipalities/' . $m->id);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        // الحذف يتسلسل على المحلات والمناطق
        $this->assertDatabaseMissing('municipalities', ['id' => $m->id]);
        $this->assertDatabaseMissing('sub_municipalities', ['id' => $sub->id]);
        $this->assertDatabaseMissing('zones', ['id' => $z->id]);
    }

    public function test_cannot_delete_municipality_whose_zone_has_drivers(): void
    {
        $m    = $this->makeMunicipality();
        $sub  = $this->makeSub($m);
        $zone = $this->makeZone($sub);

        $driverId = $this->makeDriver()->id;
        DB::table('driver_zone')->insert([
            'driver_id'  => $driverId,
            'zone_id'    => $zone->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson('/api/admin/municipalities/' . $m->id);

        $response->assertStatus(409);
        $response->assertJsonPath('status', false);
        $this->assertStringContainsString('سائق', $response->json('message'));

        // لم يُحذف شيء
        $this->assertDatabaseHas('municipalities', ['id' => $m->id]);
        $this->assertDatabaseHas('zones', ['id' => $zone->id]);
    }

    public function test_municipality_endpoints_return_404_for_missing_id(): void
    {
        $this->actingAs($this->adminUser)->getJson('/api/admin/municipalities/99999999')->assertStatus(404);
        $this->actingAs($this->adminUser)->deleteJson('/api/admin/municipalities/99999999')->assertStatus(404);
        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/municipalities/99999999', ['name' => 'اسم ما'])
            ->assertStatus(404);
    }

    // =====================================================================
    // 🏘️ المحلات
    // =====================================================================

    public function test_can_list_sub_municipalities_of_a_municipality(): void
    {
        $m    = $this->makeMunicipality();
        $sub  = $this->makeSub($m);
        $this->makeZone($sub);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/municipalities/' . $m->id . '/sub-municipalities');

        $response->assertStatus(200);
        $response->assertJsonPath('data.municipality.id', $m->id);
        $response->assertJsonPath('data.sub_municipalities.0.id', $sub->id);
        $response->assertJsonPath('data.sub_municipalities.0.zones_count', 1);
    }

    public function test_can_create_sub_municipality_under_a_municipality(): void
    {
        $m    = $this->makeMunicipality();
        $name = 'محلة جديدة ' . uniqid();

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/municipalities/' . $m->id . '/sub-municipalities', ['name' => $name]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'تم إضافة المحلة بنجاح.');
        $response->assertJsonPath('data.name', $name);
        $response->assertJsonPath('data.municipality_id', $m->id);
        $response->assertJsonPath('data.municipality_name', $m->name);

        $this->assertDatabaseHas('sub_municipalities', ['name' => $name, 'municipality_id' => $m->id]);
    }

    public function test_cannot_create_sub_municipality_under_missing_municipality(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/municipalities/99999999/sub-municipalities', ['name' => 'محلة ما'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'عذراً، البلدية غير موجودة.');
    }

    public function test_sub_municipality_name_must_be_unique_within_its_municipality_only(): void
    {
        $first  = $this->makeMunicipality();
        $second = $this->makeMunicipality();
        $name   = 'المركز ' . uniqid();

        $this->makeSub($first, $name);

        // نفس الاسم في نفس البلدية → مرفوض
        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/municipalities/' . $first->id . '/sub-municipalities', ['name' => $name])
            ->assertStatus(422);

        // نفس الاسم في بلدية أخرى → مسموح
        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/municipalities/' . $second->id . '/sub-municipalities', ['name' => $name])
            ->assertStatus(201);
    }

    public function test_can_show_sub_municipality_with_its_zones(): void
    {
        $m    = $this->makeMunicipality();
        $sub  = $this->makeSub($m);
        $zone = $this->makeZone($sub);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/sub-municipalities/' . $sub->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $sub->id);
        $response->assertJsonPath('data.municipality_id', $m->id);
        $response->assertJsonPath('data.zones.0.id', $zone->id);
        $response->assertJsonPath('data.zones.0.can_delete', true);
    }

    public function test_can_update_and_delete_sub_municipality(): void
    {
        $m       = $this->makeMunicipality();
        $sub     = $this->makeSub($m);
        $zone    = $this->makeZone($sub);
        $newName = 'محلة معدلة ' . uniqid();

        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/sub-municipalities/' . $sub->id, ['name' => $newName])
            ->assertStatus(200)
            ->assertJsonPath('data.name', $newName);

        $this->actingAs($this->adminUser)
            ->deleteJson('/api/admin/sub-municipalities/' . $sub->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('sub_municipalities', ['id' => $sub->id]);
        $this->assertDatabaseMissing('zones', ['id' => $zone->id]);
        // البلدية الأم تبقى سليمة
        $this->assertDatabaseHas('municipalities', ['id' => $m->id]);
    }

    public function test_sub_municipality_endpoints_return_404_for_missing_id(): void
    {
        $this->actingAs($this->adminUser)->getJson('/api/admin/sub-municipalities/99999999')->assertStatus(404);
        $this->actingAs($this->adminUser)->deleteJson('/api/admin/sub-municipalities/99999999')->assertStatus(404);
    }

    // =====================================================================
    // 📍 المناطق
    // =====================================================================

    public function test_can_list_zones_of_a_sub_municipality(): void
    {
        $m    = $this->makeMunicipality();
        $sub  = $this->makeSub($m);
        $zone = $this->makeZone($sub);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/sub-municipalities/' . $sub->id . '/zones');

        $response->assertStatus(200);
        $response->assertJsonPath('data.municipality.id', $m->id);
        $response->assertJsonPath('data.sub_municipality.id', $sub->id);
        $response->assertJsonPath('data.zones.0.id', $zone->id);
        $response->assertJsonStructure([
            'data' => ['zones' => [[
                'id', 'name', 'sub_municipality_id', 'sub_municipality_name',
                'municipality_id', 'municipality_name', 'full_path',
                'drivers_count', 'schools_count', 'addresses_count', 'can_delete',
            ]]],
        ]);
    }

    public function test_can_list_all_zones_of_a_municipality_across_sub_municipalities(): void
    {
        $m     = $this->makeMunicipality();
        $subA  = $this->makeSub($m);
        $subB  = $this->makeSub($m);
        $zoneA = $this->makeZone($subA);
        $zoneB = $this->makeZone($subB);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/municipalities/' . $m->id . '/zones');

        $response->assertStatus(200);
        $ids = array_column($response->json('data.zones'), 'id');
        $this->assertContains($zoneA->id, $ids);
        $this->assertContains($zoneB->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_can_create_zone_under_a_sub_municipality(): void
    {
        $m    = $this->makeMunicipality();
        $sub  = $this->makeSub($m);
        $name = 'منطقة جديدة ' . uniqid();

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/sub-municipalities/' . $sub->id . '/zones', ['name' => $name]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'تم إضافة المنطقة بنجاح.');
        $response->assertJsonPath('data.name', $name);
        $response->assertJsonPath('data.sub_municipality_id', $sub->id);
        $response->assertJsonPath('data.municipality_id', $m->id);
        $response->assertJsonPath('data.full_path', "{$m->name} ← {$sub->name} ← {$name}");

        $this->assertDatabaseHas('zones', ['name' => $name, 'sub_municipality_id' => $sub->id]);
    }

    public function test_cannot_create_zone_under_missing_sub_municipality(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/sub-municipalities/99999999/zones', ['name' => 'منطقة ما'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'عذراً، المحلة غير موجودة.');
    }

    public function test_zone_name_is_required(): void
    {
        $sub = $this->makeSub($this->makeMunicipality());

        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/sub-municipalities/' . $sub->id . '/zones', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_zone_name_must_be_unique_within_its_sub_municipality_only(): void
    {
        $m     = $this->makeMunicipality();
        $subA  = $this->makeSub($m);
        $subB  = $this->makeSub($m);
        $name  = 'حي النصر ' . uniqid();

        $this->makeZone($subA, $name);

        // نفس الاسم في نفس المحلة → مرفوض
        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/sub-municipalities/' . $subA->id . '/zones', ['name' => $name])
            ->assertStatus(422);

        // نفس الاسم في محلة أخرى → مسموح
        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/sub-municipalities/' . $subB->id . '/zones', ['name' => $name])
            ->assertStatus(201);
    }

    public function test_can_show_zone_details_with_full_path(): void
    {
        $m    = $this->makeMunicipality();
        $sub  = $this->makeSub($m);
        $zone = $this->makeZone($sub);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/admin-zones/' . $zone->id);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'تم جلب بيانات المنطقة بنجاح.');
        $response->assertJsonPath('data.id', $zone->id);
        $response->assertJsonPath('data.municipality_id', $m->id);
        $response->assertJsonPath('data.municipality_name', $m->name);
        $response->assertJsonPath('data.sub_municipality_id', $sub->id);
        $response->assertJsonPath('data.sub_municipality_name', $sub->name);
        $response->assertJsonPath('data.full_path', "{$m->name} ← {$sub->name} ← {$zone->name}");
        $response->assertJsonPath('data.drivers_count', 0);
        $response->assertJsonPath('data.can_delete', true);
    }

    public function test_can_update_zone_name(): void
    {
        $zone    = $this->makeZone($this->makeSub($this->makeMunicipality()));
        $newName = 'منطقة معدلة ' . uniqid();

        $this->actingAs($this->adminUser)
            ->postJson('/api/admin/admin-zones/' . $zone->id, ['name' => $newName])
            ->assertStatus(200)
            ->assertJsonPath('data.name', $newName);

        $this->assertDatabaseHas('zones', ['id' => $zone->id, 'name' => $newName]);
    }

    public function test_can_delete_unused_zone(): void
    {
        $zone = $this->makeZone($this->makeSub($this->makeMunicipality()));

        $this->actingAs($this->adminUser)
            ->deleteJson('/api/admin/admin-zones/' . $zone->id)
            ->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertDatabaseMissing('zones', ['id' => $zone->id]);
    }

    public function test_cannot_delete_zone_that_has_drivers(): void
    {
        $zone     = $this->makeZone($this->makeSub($this->makeMunicipality()));
        $driverId = $this->makeDriver()->id;

        DB::table('driver_zone')->insert([
            'driver_id'  => $driverId,
            'zone_id'    => $zone->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson('/api/admin/admin-zones/' . $zone->id);

        $response->assertStatus(409);
        $this->assertStringContainsString('سائق', $response->json('message'));
        $this->assertDatabaseHas('zones', ['id' => $zone->id]);
    }

    public function test_zone_shows_can_delete_false_when_in_use(): void
    {
        $zone     = $this->makeZone($this->makeSub($this->makeMunicipality()));
        $driverId = $this->makeDriver()->id;

        DB::table('driver_zone')->insert([
            'driver_id'  => $driverId,
            'zone_id'    => $zone->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->adminUser)
            ->getJson('/api/admin/admin-zones/' . $zone->id)
            ->assertStatus(200)
            ->assertJsonPath('data.drivers_count', 1)
            ->assertJsonPath('data.can_delete', false);
    }

    public function test_zone_endpoints_return_404_for_missing_id(): void
    {
        $this->actingAs($this->adminUser)->getJson('/api/admin/admin-zones/99999999')->assertStatus(404);
        $this->actingAs($this->adminUser)->deleteJson('/api/admin/admin-zones/99999999')->assertStatus(404);
    }

    // =====================================================================
    // 🔒 الحماية
    // =====================================================================

    public function test_guest_cannot_access_any_geography_endpoint(): void
    {
        $m    = $this->makeMunicipality();
        $sub  = $this->makeSub($m);
        $zone = $this->makeZone($sub);

        $this->getJson('/api/admin/municipalities')->assertStatus(401);
        $this->postJson('/api/admin/municipalities', ['name' => 'x'])->assertStatus(401);
        $this->getJson('/api/admin/municipalities/' . $m->id)->assertStatus(401);
        $this->deleteJson('/api/admin/municipalities/' . $m->id)->assertStatus(401);
        $this->getJson('/api/admin/sub-municipalities/' . $sub->id)->assertStatus(401);
        $this->deleteJson('/api/admin/sub-municipalities/' . $sub->id)->assertStatus(401);
        $this->getJson('/api/admin/admin-zones/' . $zone->id)->assertStatus(401);
        $this->deleteJson('/api/admin/admin-zones/' . $zone->id)->assertStatus(401);
    }

    // =====================================================================
    // 🔄 دورة كاملة
    // =====================================================================

    public function test_full_geography_lifecycle_end_to_end(): void
    {
        // 1) إنشاء بلدية
        $municipalityId = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/municipalities', ['name' => 'بلدية الدورة ' . uniqid()])
            ->assertStatus(201)
            ->json('data.id');

        // 2) إضافة محلة تحتها
        $subId = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/municipalities/{$municipalityId}/sub-municipalities", [
                'name' => 'محلة الدورة ' . uniqid(),
            ])
            ->assertStatus(201)
            ->json('data.id');

        // 3) إضافة منطقتين تحت المحلة
        $zoneId = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/sub-municipalities/{$subId}/zones", ['name' => 'منطقة أولى ' . uniqid()])
            ->assertStatus(201)
            ->json('data.id');

        $this->actingAs($this->adminUser)
            ->postJson("/api/admin/sub-municipalities/{$subId}/zones", ['name' => 'منطقة ثانية ' . uniqid()])
            ->assertStatus(201);

        // 4) البلدية تعكس العدّادات
        $this->actingAs($this->adminUser)
            ->getJson("/api/admin/municipalities/{$municipalityId}")
            ->assertStatus(200)
            ->assertJsonPath('data.sub_municipalities_count', 1)
            ->assertJsonPath('data.zones_count', 2);

        // 5) عرض تفاصيل منطقة
        $this->actingAs($this->adminUser)
            ->getJson("/api/admin/admin-zones/{$zoneId}")
            ->assertStatus(200)
            ->assertJsonPath('data.municipality_id', $municipalityId);

        // 6) حذف منطقة واحدة
        $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/admin-zones/{$zoneId}")
            ->assertStatus(200);

        $this->actingAs($this->adminUser)
            ->getJson("/api/admin/municipalities/{$municipalityId}")
            ->assertJsonPath('data.zones_count', 1);

        // 7) حذف البلدية بالكامل
        $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/municipalities/{$municipalityId}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('municipalities', ['id' => $municipalityId]);
        $this->assertDatabaseMissing('sub_municipalities', ['id' => $subId]);
    }

    // =====================================================================
    // 📝 اختبارات تفصيلية لرسائل التحقق والأخطاء (Validation & Error Messages)
    // =====================================================================

    public function test_sub_municipality_validation_rules_and_messages(): void
    {
        $m = $this->makeMunicipality();

        // 1. الاسم فارغ
        $res = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/municipalities/{$m->id}/sub-municipalities", ['name' => '']);
        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['name']);
        $this->assertEquals('يرجى إدخال اسم المحلة، هذا الحقل إجباري.', $res->json('errors.name.0'));

        // 2. الاسم قصير جداً (أقل من حرفين)
        $res = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/municipalities/{$m->id}/sub-municipalities", ['name' => 'أ']);
        $res->assertStatus(422);
        $this->assertEquals('اسم المحلة قصير جداً، يجب ألا يقل عن حرفين.', $res->json('errors.name.0'));

        // 3. الاسم طويل جداً (أكثر من 100 حرف)
        $res = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/municipalities/{$m->id}/sub-municipalities", ['name' => str_repeat('م', 101)]);
        $res->assertStatus(422);
        $this->assertEquals('اسم المحلة طويل جداً، يجب ألا يتجاوز 100 حرف.', $res->json('errors.name.0'));
    }

    public function test_zone_validation_rules_and_messages(): void
    {
        $sub = $this->makeSub($this->makeMunicipality());

        // 1. الاسم فارغ
        $res = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/sub-municipalities/{$sub->id}/zones", ['name' => '']);
        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['name']);
        $this->assertEquals('يرجى إدخال اسم المنطقة، هذا الحقل إجباري.', $res->json('errors.name.0'));

        // 2. الاسم قصير جداً (أقل من حرفين)
        $res = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/sub-municipalities/{$sub->id}/zones", ['name' => 'ب']);
        $res->assertStatus(422);
        $this->assertEquals('اسم المنطقة قصير جداً، يجب ألا يقل عن حرفين.', $res->json('errors.name.0'));

        // 3. الاسم طويل جداً (أكثر من 100 حرف)
        $res = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/sub-municipalities/{$sub->id}/zones", ['name' => str_repeat('ن', 101)]);
        $res->assertStatus(422);
        $this->assertEquals('اسم المنطقة طويل جداً، يجب ألا يتجاوز 100 حرف.', $res->json('errors.name.0'));
    }

    public function test_sub_municipality_and_zone_put_method_update(): void
    {
        $m    = $this->makeMunicipality();
        $sub  = $this->makeSub($m);
        $zone = $this->makeZone($sub);

        // تعديل محلة عبر PUT
        $newSubName = 'محلة معدلة عبر PUT ' . uniqid();
        $this->actingAs($this->adminUser)
            ->putJson("/api/admin/sub-municipalities/{$sub->id}", ['name' => $newSubName])
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', $newSubName);

        // تعديل منطقة عبر PUT
        $newZoneName = 'منطقة معدلة عبر PUT ' . uniqid();
        $this->actingAs($this->adminUser)
            ->putJson("/api/admin/admin-zones/{$zone->id}", ['name' => $newZoneName])
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', $newZoneName);
    }

    public function test_cannot_delete_sub_municipality_whose_zone_has_schools(): void
    {
        $m    = $this->makeMunicipality();
        $sub  = $this->makeSub($m);
        $zone = $this->makeZone($sub);

        DB::table('schools')->insert([
            'name'       => 'مدرسة تجريبية ' . uniqid(),
            'zone_id'    => $zone->id,
            'lat'        => 32.881,
            'lng'        => 13.191,
            'status'     => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/sub-municipalities/{$sub->id}");

        $response->assertStatus(409);
        $response->assertJsonPath('status', false);
        $this->assertStringContainsString('مدرسة', $response->json('message'));
        $this->assertDatabaseHas('sub_municipalities', ['id' => $sub->id]);
    }

    public function test_cannot_delete_zone_that_has_schools(): void
    {
        $zone = $this->makeZone($this->makeSub($this->makeMunicipality()));

        DB::table('schools')->insert([
            'name'       => 'مدرسة تجريبية 2 ' . uniqid(),
            'zone_id'    => $zone->id,
            'lat'        => 32.881,
            'lng'        => 13.191,
            'status'     => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/admin-zones/{$zone->id}");

        $response->assertStatus(409);
        $response->assertJsonPath('status', false);
        $this->assertStringContainsString('مدرسة', $response->json('message'));
        $this->assertDatabaseHas('zones', ['id' => $zone->id]);
    }
}
