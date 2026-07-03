<?php

namespace App\Services\Shared;

use App\Models\Shared\Contract;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\Clause;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use niklasravnsborg\LaravelPdf\Facades\Pdf as PDF;
use Exception;

class ContractService
{
    private string $primaryColor = '#007A99';
    private string $accentColor  = '#F59E0B';

    // ============================================================
    // توليد العقد تلقائياً عند القبول
    // ============================================================

    /**
     * ينشئ سجل العقد في قاعدة البيانات + يولّد ملف PDF موقّعاً بهوية Darby
     *
     * @param SubscriptionRequest $req الطلب المقبول
     * @return Contract
     * @throws Exception
     */
    public function generateContract(SubscriptionRequest $req): Contract
    {
        // 1. تحميل جميع العلاقات اللازمة
        $req->load(['children.school', 'parent.user', 'driver.user', 'driver.vehicles']);

        // 2. جلب الشروط من قاعدة البيانات
        $clauses = Clause::all()->pluck('clause_text')->toArray();

        // 3. إنشاء رقم العقد الفريد
        $contractNumber = Contract::generateContractNumber();

        // 4. إنشاء سجل العقد في قاعدة البيانات
        $contract = Contract::create([
            'subscription_request_id' => $req->id,
            'parent_id'               => $req->parent_id,
            'driver_id'               => $req->driver_id,
            'contract_number'         => $contractNumber,
            'subscription_type'       => $req->subscription_type,
            'direction'               => $req->direction,
            'timing'                  => $req->timing,
            'pickup_time'             => $req->pickup_time ?? '07:00:00',
            'dropoff_time'            => $req->dropoff_time ?? '14:00:00',
            'max_waiting_time' => $req->max_waiting_time ?? 15,
            'start_date'              => $req->start_date,
            'end_date'                => $req->end_date,
            'days_count'              => $req->days_count,
            'total_price'             => $req->total_price,
            'clauses'                 => $clauses,
            'status'                  => 'active',
            'signed_at'               => now(),
        ]);

        // 5. توليد PDF
        try {
            $pdfPath = $this->generatePdf($contract);
            $contract->update(['pdf_path' => $pdfPath]);
        } catch (Exception $e) {
            Log::error("فشل توليد PDF للعقد {$contractNumber}: " . $e->getMessage());
            // لا نوقف العملية بسبب فشل PDF فقط
        }

        return $contract->load(['subscriptionRequest', 'parent.user', 'driver.user', 'activeSubscriptions']);
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