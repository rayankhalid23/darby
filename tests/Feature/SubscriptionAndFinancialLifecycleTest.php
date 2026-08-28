<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Parent\School;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Route;
use App\Models\Shared\Trip;
use App\Models\Shared\Invoice;
use App\Services\Shared\FinancialService;
use App\Services\Shared\FinancialLedgerService;
use App\Services\Admin\ReportService;

class SubscriptionAndFinancialLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;
    protected Child $child;
    protected School $school;
    protected SubscriptionRequest $subReq;
    protected ActiveSubscription $activeSub;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'display_name' => 'مدير النظام'],
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->driverUser = User::create([
            'full_name' => 'سائق تجربة مالي',
            'email' => 'driver.fin.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id' => 2,
            'is_active' => 1,
        ]);
        $this->driver = Driver::create([
            'user_id' => $this->driverUser->id,
            'national_id' => '1990' . rand(10000000, 99999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status' => 'Approved',
        ]);

        $this->parentUser = User::create([
            'full_name' => 'ولي أمر تجربة مالي',
            'email' => 'parent.fin.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id' => 3,
            'is_active' => 1,
        ]);
        $this->parent = ParentModel::create([
            'user_id' => $this->parentUser->id,
        ]);

        $this->school = School::firstOrCreate(
            ['name' => 'مدرسة التجارب المالية'],
            ['lat' => 32.8800, 'lng' => 13.1900]
        );

        $this->child = Child::create([
            'parent_id' => $this->parent->id,
            'school_id' => $this->school->id,
            'full_name' => 'طفل المالية التجريبي',
            'gender'    => 'male',
            'birth_date' => '2015-05-10',
            'grade'      => 4,
            'grade_level' => '4',
        ]);

        $this->subReq = SubscriptionRequest::create([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'total_price'       => 220.00,
            'status'            => 'accepted',
            'children_count'    => 1,
        ]);

        $vehicle = Vehicle::create([
            'driver_id' => $this->driver->id,
            'plate_number' => '5-' . rand(1000, 9999),
            'brand' => 'Toyota',
            'model' => 'Hiace',
            'year' => 2022,
            'color' => 'White',
            'type' => 'Van',
            'capacity_manual' => 14,
            'is_verified' => 1,
            'status' => 'Active',
        ]);

        $route = Route::create([
            'driver_id'               => $this->driver->id,
            'vehicle_id'              => $vehicle->id,
            'subscription_request_id' => $this->subReq->id,
            'route_name'              => 'مسار المالية التجريبي',
            'route_type'              => 'Morning',
            'shift_slot'              => 'morning_go',
            'start_time'              => '07:00:00',
            'status'                  => 'Active',
        ]);

        $this->activeSub = ActiveSubscription::create([
            'parent_id'               => $this->parentUser->id,
            'driver_id'               => $this->driver->id,
            'child_id'                => $this->child->id,
            'subscription_request_id' => $this->subReq->id,
            'route_id'                => $route->id,
            'status'                  => 'active',
        ]);
    }

    /**
     * اختبار توليد الفاتورة المبدئية والتسوية المالية بدون كائن Contract
     */
    public function test_financial_service_generates_and_settles_invoice_using_subscription_request()
    {
        $financialService = app(FinancialService::class);

        // 1. توليد فاتورة مبدئية
        $invoice = $financialService->generateProformaInvoice($this->subReq);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals('proforma', $invoice->type);
        $this->assertEquals('pending', $invoice->status);
        $this->assertEquals($this->subReq->id, $invoice->subscription_request_id);
        $this->assertEquals(220.00, (float) $invoice->amount);

        // 2. إيداع رصيد لولي الأمر للتسوية
        $this->parent->deposit(30000); // 300 د.ل

        // 3. إجراء التسوية المالية
        $settled = $financialService->settleSubscription($this->subReq);

        $this->assertEquals('final', $settled->type);
        $this->assertEquals('paid', $settled->status);
        $this->assertNotNull($settled->paid_at);
    }

    /**
     * اختبار التسوية الشهرية والإلغاء المبكر عبر FinancialLedgerService
     */
    public function test_financial_ledger_settle_and_terminate_mid_month()
    {
        $ledgerService = app(FinancialLedgerService::class);

        // 1. معاينة الإلغاء المبكر
        $preview = $ledgerService->previewSubscriptionTermination($this->subReq, 'parent', true);
        $this->assertIsArray($preview);
        $this->assertArrayHasKey('cancellation_fee', $preview);
        $this->assertEquals($this->subReq->id, $preview['contract_id']);

        // 2. تسوية شهرية
        $settlement = $ledgerService->settleMonthlySubscription($this->subReq);
        $this->assertIsArray($settlement);
        $this->assertArrayHasKey('final_settled_amount', $settlement);
        $this->assertEquals(220.00, $settlement['total_contract_price']);
    }

    /**
     * اختبار الأمر المجدول subscriptions:settle
     */
    public function test_settle_subscriptions_command_executes_successfully()
    {
        $financialService = app(FinancialService::class);
        $invoice = $financialService->generateProformaInvoice($this->subReq);
        $invoice->update(['due_date' => now()->startOfDay()->format('Y-m-d')]);

        $exitCode = Artisan::call('subscriptions:settle');
        $this->assertEquals(0, $exitCode);
    }

    /**
     * اختبار تقارير الاشتراكات في ReportService بدون أخطاء Contract
     */
    public function test_report_service_subscriptions_report_works()
    {
        $reportService = app(ReportService::class);

        $report = $reportService->getSubscriptionsReport([
            'date_from' => now()->subMonth()->toDateString(),
            'date_to'   => now()->addMonth()->toDateString(),
        ]);

        $this->assertIsArray($report);
        $this->assertArrayHasKey('subscription_types', $report);
        $this->assertArrayHasKey('status_breakdown', $report);
        $this->assertArrayHasKey('expiring_soon', $report);
    }

    /**
     * اختبار منع تحميل واستعراض ملفات PDF عبر MediaController
     */
    public function test_media_controller_rejects_pdf_files()
    {
        $response = $this->getJson('/api/media/drivers/documents/test_file.pdf');
        $response->assertStatus(404);
    }

    /**
     * اختبار قبول طلبات التحقق للصور ومنع ملفات الـ PDF
     */
    public function test_update_complaint_request_rejects_pdf_attachment()
    {
        Storage::fake('public');
        $this->actingAs($this->parentUser);

        $complaint = \App\Models\Shared\Complaint::create([
            'submitted_by' => $this->parent->id,
            'against_type' => 'DRIVER',
            'against_id'   => $this->driver->id,
            'description'  => 'وصف الشكوى لا يقل عن عشرة أحرف تفصيلية.',
            'status'       => 'Open',
        ]);

        $pdfFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->postJson("/api/parent/complaints/{$complaint->id}", [
            'title'       => 'شكوى تجريبية للتحقق من المرفقات',
            'description' => 'وصف الشكوى لا يقل عن عشرة أحرف تفصيلية.',
            'type'        => 'driver',
            'attachments' => [$pdfFile],
        ]);

        // يجب أن يفشل التحقق لأن صيغة الـ pdf لم تعد مقبولة
        $response->assertStatus(422);
    }
}
