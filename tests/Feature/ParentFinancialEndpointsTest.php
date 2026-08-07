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
use App\Models\Shared\Contract;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Route as RouteModel;
use App\Models\Shared\Trip;

/**
 * اختبار إصلاحات الأخطاء المكتشفة في واجهات ولي الأمر المالية:
 * 1) POST /active-subscriptions/{id}/cancel كان مسجَّلاً بلا دالة فعلية.
 * 2) بلوك billing في active-subscriptions كان يحتوي قيماً وهمية ثابتة.
 * 3) استجابة hold-trip كانت ترجع المبلغ بالقروش خلافاً لباقي واجهات المحفظة.
 * 4) ContractResource كان يشير لعمودين محذوفين (price, selected_clauses).
 */
class ParentFinancialEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;
    protected Child $child;
    protected School $school;
    protected Contract $contract;
    protected ActiveSubscription $activeSub;
    protected int $subscriptionRequestId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->driverUser = User::create([
            'full_name'     => 'سائق مالية ولي الأمر',
            'email'         => 'driver.pfin.' . uniqid() . '@darby.test',
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
        ]);

        $vehicleId = DB::table('vehicles')->insertGetId([
            'driver_id'       => $this->driver->id,
            'brand'           => 'تويوتا',
            'model'           => 'هايس',
            'year'            => 2022,
            'color'           => 'أبيض',
            'plate_number'    => 'PFIN-' . rand(1000, 9999),
            'capacity_manual' => 10,
            'capacity_ai'     => 10,
            'status'          => 'Active',
            'deleted_at'      => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->parentUser = User::create([
            'full_name'     => 'ولي أمر مالية الاختبار',
            'email'         => 'parent.pfin.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        $this->school = School::create([
            'name'    => 'مدرسة مالية الاختبار',
            'address' => 'شارع الاختبار',
            'lat'     => 32.9000,
            'lng'     => 13.2000,
            'status'  => 'active',
        ]);

        $this->child = Child::create([
            'parent_id'            => $this->parent->id,
            'school_id'            => $this->school->id,
            'full_name'            => 'طفل مالية الاختبار',
            'birth_date'           => '2018-05-10',
            'gender'               => 'male',
            'grade'                => 1,
            'notification_radius' => 500,
        ]);

        // طلب اشتراك مقبول + سعر خاص بالطفل (request_children.price_per_child)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->subscriptionRequestId = DB::table('requests')->insertGetId([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'school_id'         => $this->school->id,
            'timing'            => 'MORNING',
            'direction'         => 'both',
            'status'            => 'accepted',
            'subscription_type' => 'monthly',
            'children_count'    => 1,
            'created_at'        => now(),
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'منزل ولي الأمر',
            'lat'        => 32.88,
            'lng'        => 13.19,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('request_children')->insert([
            'request_id'         => $this->subscriptionRequestId,
            'child_id'           => $this->child->id,
            'pickup_address_id'  => $addressId,
            'dropoff_address_id' => $this->school->id,
            'price_per_child'    => 175.50,
        ]);

        $this->contract = Contract::create([
            'subscription_request_id' => $this->subscriptionRequestId,
            'parent_id'               => $this->parentUser->id,
            'driver_id'               => $this->driverUser->id,
            'contract_number'         => 'DRBY-PFIN-' . rand(100000, 999999),
            'subscription_type'       => 'monthly',
            'direction'               => 'both',
            'timing'                  => 'MORNING',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
            'max_waiting_time'        => 15,
            'start_date'              => now()->subDays(5)->format('Y-m-d'),
            'end_date'                => now()->addDays(25)->format('Y-m-d'),
            'days_count'              => 22,
            'total_price'             => 175.50,
            'clauses'                 => ['يلتزم الطرفان بالمواعيد المتفق عليها.', 'يحق لولي الأمر الإلغاء وفق الشروط.'],
            'status'                  => 'active',
        ]);

        $this->activeSub = ActiveSubscription::create([
            'contract_id'   => $this->contract->id,
            'status'        => 'active',
            'child_id'      => $this->child->id,
            'driver_id'     => $this->driver->id,
            'parent_id'     => $this->parentUser->id,
            'pickup_lat'    => 32.88,
            'pickup_lng'    => 13.19,
            'pickup_label'  => 'المنزل',
            'pickup_time'   => '07:00:00',
            'dropoff_lat'   => 32.90,
            'dropoff_lng'   => 13.20,
            'dropoff_label' => 'المدرسة',
            'dropoff_time'  => '14:00:00',
        ]);
    }

    // =========================================================
    // إصلاح 1: POST /active-subscriptions/{id}/cancel كان معطلاً
    // =========================================================

    public function test_parent_can_cancel_active_subscription(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->postJson("/api/parent/active-subscriptions/{$this->activeSub->id}/cancel");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('active_subscriptions', [
            'id'     => $this->activeSub->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancelling_already_cancelled_subscription_returns_422(): void
    {
        $this->activeSub->update(['status' => 'cancelled']);

        $response = $this->actingAs($this->parentUser)
            ->postJson("/api/parent/active-subscriptions/{$this->activeSub->id}/cancel");

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_cancelling_nonexistent_subscription_returns_404(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->postJson('/api/parent/active-subscriptions/999999/cancel');

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
    }

    public function test_parent_cannot_cancel_another_parents_subscription(): void
    {
        $otherParentUser = User::create([
            'full_name'     => 'ولي أمر آخر',
            'email'         => 'other.pfin.' . uniqid() . '@darby.test',
            'phone_number'  => '093' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);
        ParentModel::create(['user_id' => $otherParentUser->id, 'is_trusted' => 1]);

        $response = $this->actingAs($otherParentUser)
            ->postJson("/api/parent/active-subscriptions/{$this->activeSub->id}/cancel");

        $response->assertStatus(404);
    }

    // =========================================================
    // إصلاح 2: بلوك billing يعرض قيماً حقيقية بدل الوهمية
    // =========================================================

    public function test_active_subscription_billing_block_reflects_real_data(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->getJson("/api/parent/active-subscriptions/{$this->activeSub->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.billing.currency', 'د.ل');
        $response->assertJsonPath('data.billing.subscriptionType', 'monthly');
        $response->assertJsonPath('data.billing.totalPrice', 175.5);
        $response->assertJsonPath('data.billing.childPrice', 175.5);
        $response->assertJsonPath('data.billing.autoRenew', false);
        $response->assertJsonPath('data.billing.paymentMethod', 'wallet');
        $response->assertJsonPath('data.billing.startsAt', now()->subDays(5)->format('Y-m-d'));
        $response->assertJsonPath('data.billing.endsAt', now()->addDays(25)->format('Y-m-d'));

        $remainingDays = $response->json('data.billing.remainingDays');
        $this->assertIsInt($remainingDays);
        $this->assertGreaterThan(20, $remainingDays);
        $this->assertLessThan(26, $remainingDays);
    }

    public function test_active_subscriptions_list_billing_block_reflects_real_data(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->getJson('/api/parent/active-subscriptions');

        $response->assertStatus(200);
        $item = collect($response->json('data'))->firstWhere('id', $this->activeSub->id);

        $this->assertNotNull($item);
        $this->assertEquals('د.ل', $item['billing']['currency']);
        $this->assertEquals(175.5, $item['billing']['childPrice']);
        $this->assertEquals('wallet', $item['billing']['paymentMethod']);
        $this->assertFalse($item['billing']['autoRenew']);
    }

    // =========================================================
    // إصلاح 3: hold-trip يرجع المبلغ بالدينار (وليس بالقروش)
    // =========================================================

    public function test_hold_trip_amount_response_is_in_dinar_not_cents(): void
    {
        $this->parent->deposit(100000); // 1000 د.ل

        $vehicleId = DB::table('vehicles')->where('driver_id', $this->driver->id)->value('id');

        $route = RouteModel::create([
            'driver_id'  => $this->driver->id,
            'vehicle_id' => $vehicleId,
            'route_name' => 'مسار مالية الاختبار',
            'route_type' => 'Morning',
            'shift_slot' => 'morning_go',
            'start_time' => '07:00:00',
            'status'     => 'Active',
        ]);

        $trip = Trip::create([
            'driver_id'    => $this->driver->id,
            'route_id'     => $route->id,
            'trip_type'    => 'Morning',
            'shift_slot'   => 'morning_go',
            'status'       => 'pending',
            'trip_date'    => now()->toDateString(),
            'scheduled_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($this->parentUser)
            ->postJson('/api/parent/wallet/hold-trip', [
                'trip_id' => $trip->id,
                'amount'  => 25.5,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.amount', 25.5); // وليس 2550
        $response->assertJsonPath('data.hold_status', 'held');

        $this->assertDatabaseHas('trip_escrow_holds', [
            'trip_id' => $trip->id,
            'amount'  => 2550, // التخزين الداخلي يبقى بالقروش كما هو، لا نغيّره
        ]);
    }

    // =========================================================
    // إصلاح 4: ContractResource لا يشير لأعمدة محذوفة (price, selected_clauses)
    // =========================================================

    public function test_contract_accept_response_uses_total_price_and_real_clauses(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->putJson("/api/contracts/{$this->contract->id}/accept");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.price', 175.5);
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.status_text', 'مفعّل وساري العمل به');
        $response->assertJsonPath('data.parent.id', $this->parentUser->id);
        $response->assertJsonPath('data.parent.name', $this->parentUser->full_name);
        $response->assertJsonPath('data.driver.id', $this->driverUser->id);

        $clauses = $response->json('data.clauses');
        $this->assertIsArray($clauses);
        $this->assertCount(2, $clauses);
        $this->assertContains('يلتزم الطرفان بالمواعيد المتفق عليها.', $clauses);
    }
}
