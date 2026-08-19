<?php

namespace App\Services\Shared;

use App\Models\Shared\Contract;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\Clause;
use App\Models\Shared\ActiveSubscription;
use App\Models\Parent\ParentModel;
use App\Notifications\CustomDatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use niklasravnsborg\LaravelPdf\Facades\Pdf as PDF;
use Exception;

class ContractService
{
    private string $primaryColor = '#007A99';
    private string $accentColor  = '#F59E0B';

    protected FinancialService $financialService;

    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
    }

    // ============================================================
    // توليد العقد تلقائياً عند القبول
    // ============================================================


    public function generateContract(SubscriptionRequest $req): Contract
{
    // 1. تحميل جميع العلاقات اللازمة
    $req->loadMissing(['children.school', 'parent.user', 'driver.user', 'driver.vehicles']);

    // 2. جلب الشروط من قاعدة البيانات
    $clauses = Clause::all()->pluck('clause_text')->toArray();

    // 3. إنشاء رقم العقد الفريد
    $contractNumber = Contract::generateContractNumber();

    // 4. استخراج معرّفات المستخدمين (User IDs)
    $parentUserId = $req->parent?->user_id ?? $req->parent_id;
    $driverUserId = $req->driver?->user_id ?? $req->driver_id;

    // 5. إنشاء سجل العقد في قاعدة البيانات
    $contract = Contract::create([
        'subscription_request_id' => $req->id,
        'parent_id'               => $parentUserId,
        'driver_id'               => $driverUserId,
        'contract_number'         => $contractNumber,
        'subscription_type'       => $req->subscription_type ?? $req->type ?? 'multi_day',
        'direction'               => $req->direction ?? $req->trip_type ?? 'both',
        'timing'                  => $req->timing ?? 'MORNING',
        'pickup_time'             => $req->pickup_time ?? '07:00:00',
        'dropoff_time'            => $req->dropoff_time ?? '14:00:00',
        'max_waiting_time'        => $req->max_waiting_time ?? 15,
        'start_date'              => $req->start_date ?? now(),
        'end_date'                => $req->end_date ?? null,
        'days_count'              => $req->days_count ?? $req->working_days_count ?? 0,
        'total_price'             => $req->total_price ?? $req->total_amount ?? 0,
        'clauses'                 => $clauses,
        'status'                  => 'active',
        'signed_at'               => now(),
    ]);

    // 6. إنشاء الفاتورة المبدئية (بروفورما)
    try {
        if (isset($this->financialService) && method_exists($this->financialService, 'generateProformaInvoice')) {
            $this->financialService->generateProformaInvoice($contract);
        }
    } catch (\Exception $e) {
        Log::error("فشل توليد الفاتورة المبدئية للعقد {$contractNumber}: " . $e->getMessage());
    }

    return $contract->load(['subscriptionRequest', 'parent.user', 'driver.user', 'activeSubscriptions']);
}
    

    /**
     * قبول وتوقيع العقد من قبل ولي الأمر
     */
    public function acceptContract(int $contractId): Contract
    {
        return DB::transaction(function () use ($contractId) {
            $contract = Contract::with(['parentUser', 'driverUser'])->findOrFail($contractId);

            if ($contract->status === 'active') {
                $contract->update(['signed_at' => now()]);
                return $contract;
            }

            if (!in_array($contract->status, ['pending_parent_approval', 'draft'])) {
                throw new Exception('لا يمكن قبول عقد بحالته الحالية.', 400);
            }

            $contract->update([
                'status'    => 'active',
                'signed_at' => now(),
            ]);

            ActiveSubscription::where('contract_id', $contract->id)
                ->update(['status' => 'active']);

            try {
                $this->financialService->generateProformaInvoice($contract);
            } catch (Exception $e) {
                Log::error("فشل توليد الفاتورة للعقد {$contract->contract_number}: " . $e->getMessage());
            }

            if ($contract->parentUser) {
                $contract->parentUser->notify(new CustomDatabaseNotification([
                    'title'   => 'تم توقيع العقد',
                    'message' => "تم توقيع العقد رقم {$contract->contract_number} بنجاح وبدء الاشتراك.",
                    'type'    => 'contract_signed',
                ]));
            }

            if ($contract->driverUser) {
                $contract->driverUser->notify(new CustomDatabaseNotification([
                    'title'   => 'تم توقيع العقد',
                    'message' => "قام ولي الأمر بتوقيع العقد رقم {$contract->contract_number}.",
                    'type'    => 'contract_signed',
                ]));
            }

            return $contract->fresh()->load(['parentUser', 'driverUser', 'activeSubscriptions']);
        });
    }

    /**
     * رفض العقد من قبل ولي الأمر
     */
    public function rejectContract(int $contractId): Contract
    {
        return DB::transaction(function () use ($contractId) {
            $contract = Contract::with(['parentUser'])->findOrFail($contractId);

            if ($contract->status === 'active') {
                throw new Exception('لا يمكن رفض عقد مُفعّل بالفعل.', 400);
            }

            $contract->update(['status' => 'rejected']);

            ActiveSubscription::where('contract_id', $contract->id)
                ->update(['status' => 'cancelled']);

            if ($contract->parentUser) {
                $contract->parentUser->notify(new CustomDatabaseNotification([
                    'title'   => 'تم رفض العقد',
                    'message' => "تم رفض العقد رقم {$contract->contract_number}.",
                    'type'    => 'contract_rejected',
                ]));
            }

            return $contract->fresh()->load(['parentUser', 'driverUser']);
        });
    }

    // ============================================================
    // توليد ملف PDF
    // ============================================================

    /**
     * يولّد ملف PDF من قالب HTML احترافي بهوية Darby
     * ويحفظه في storage/app/public/contracts/
     */
    private function generatePdf(Contract $contract): string
    {
        $contract->load([
            'driver.user',
            'driver.vehicles',
            'parent.user',
            'subscriptionRequest.children.school',
        ]);

        $pdf = PDF::loadView('Pdf.contract', ['contract' => $contract]);

        $filename  = "contracts/{$contract->contract_number}.pdf";
        $directory = storage_path("app/public/contracts");

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $pdf->save(storage_path("app/public/{$filename}"));

        return $filename;
    }

    // ============================================================
    // جلب رابط PDF للعقد
    // ============================================================

    /**
     * يرجع المسار الكامل لملف PDF إذا كان موجوداً
     */
    public function getContractPdfPath(Contract $contract): ?string
    {
        if (!$contract->pdf_path) {
            return null;
        }
        $fullPath = storage_path("app/public/{$contract->pdf_path}");
        return file_exists($fullPath) ? $fullPath : null;
    }
}