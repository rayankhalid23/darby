<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Shared\Contract;
use App\Models\Shared\Invoice;
use App\Models\Shared\WithdrawalRequest;

class DriverFinancialEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected Contract $contract;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        // 1. Driver user and model
        $this->driverUser = User::create([
            'full_name'     => 'سائق مالية الاختبار',
            'email'         => 'driver.fin.' . uniqid() . '@darby.test',
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

        // Give driver initial balance in wallet
        $this->driver->deposit(50000); // 500 LYD in cents (or balance)

        // 2. Parent user
        $this->parentUser = User::create([
            'full_name'     => 'ولي أمر مالية الاختبار',
            'email'         => 'parent.fin.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $parentModel = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        // 3. SubscriptionRequest (مطلوب كـ FK للعقد — نعطّل FK مؤقتاً في بيئة الاختبار)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $subscriptionRequestId = DB::table('requests')->insertGetId([
            'parent_id'         => $parentModel->id,
            'driver_id'         => $this->driver->id,
            'school_id'         => 9999,
            'timing'            => 'MORNING',
            'direction'         => 'both',
            'status'            => 'accepted',
            'subscription_type' => 'monthly',
            'children_count'    => 1,
            'created_at'        => now(),
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // 4. Contract
        $this->contract = Contract::create([
            'subscription_request_id' => $subscriptionRequestId,
            'parent_id'               => $this->parentUser->id,
            'driver_id'               => $this->driverUser->id,
            'contract_number'         => 'DRBY-FIN-' . rand(100000, 999999),
            'subscription_type'       => 'monthly',
            'direction'               => 'both',
            'timing'                  => 'MORNING',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
            'max_waiting_time'        => 15,
            'start_date'              => now()->subDays(5)->format('Y-m-d'),
            'end_date'                => now()->addDays(25)->format('Y-m-d'),
            'days_count'              => 22,
            'total_price'             => 300.00,
            'clauses'                 => [],
            'status'                  => 'active',
        ]);

        // 4. Invoice
        $this->invoice = Invoice::create([
            'contract_id'       => $this->contract->id,
            'parent_id'         => $this->parentUser->id,
            'driver_id'         => $this->driver->id,
            'invoice_number'    => 'INV-' . $this->contract->contract_number,
            'amount'            => 300.00,
            'type'              => 'proforma',
            'status'            => 'pending',
            'due_date'          => now()->addDays(30),
            'subscription_type' => 'monthly',
            'total_trips'       => 22,
            'completed_trips'   => 0,
            'driver_absences'   => 0,
            'student_absences'  => 0,
        ]);
    }

    /**
     * Test 1: GET /api/v1/driver/wallet/balance
     */
    public function test_get_wallet_balance(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/v1/driver/wallet/balance');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'balance',
                'currency'
            ]
        ]);
    }

    /**
     * Test 2: GET /api/v1/driver/withdrawals
     */
    public function test_get_withdrawals_list(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/v1/driver/withdrawals');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data',
            'pagination' => ['current_page', 'last_page', 'total']
        ]);
    }

    /**
     * Test 3: POST /api/v1/driver/withdrawals
     */
    public function test_store_withdrawal_request(): void
    {
        $payload = [
            'amount' => 50.0,
            'payment_method_details' => [
                'bank_name' => 'مصرف الجمهورية',
                'account_number' => '123456789',
                'account_name' => 'سائق مالية الاختبار',
                'mobile_number' => '0910000000'
            ]
        ];

        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/withdrawals', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.amount', '50.00');
        $response->assertJsonPath('data.status', 'pending');
    }

    /**
     * Test 4: GET /api/v1/driver/invoices
     */
    public function test_get_driver_invoices(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/v1/driver/invoices');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data',
            'pagination' => ['current_page', 'last_page', 'total', 'per_page']
        ]);
    }

    /**
     * Test 5: GET /api/v1/driver/invoices/{id}
     */
    public function test_get_single_driver_invoice(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/v1/driver/invoices/{$this->invoice->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $this->invoice->id);
    }
}
