<?php

namespace App\Console\Commands;

use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Invoice;
use App\Services\Shared\FinancialService;
use Illuminate\Console\Command;

class SettleSubscriptions extends Command
{
    protected $signature = 'subscriptions:settle';
    protected $description = 'تسوية الاشتراكات اليومية والشهرية تلقائياً';

    public function handle(FinancialService $financialService): int
    {
        $this->info('بدء عملية تسوية الاشتراكات...');

        $today = now()->startOfDay();

        $pendingInvoices = Invoice::where('type', 'proforma')
            ->where('status', 'pending')
            ->whereDate('due_date', '<=', $today)
            ->with(['subscriptionRequest.parent.user', 'subscriptionRequest.driver.user'])
            ->get();

        $this->info("تم العثور على {$pendingInvoices->count()} اشتراك جاهز للتسوية.");

        $settled = 0;
        $warned = 0;

        foreach ($pendingInvoices as $invoice) {
            $req = $invoice->subscriptionRequest;
            if (!$req) continue;

            try {
                $financialService->settleSubscription($req);
                $settled++;
                $this->info("تم تسوية الاشتراك #{$req->id}");
            } catch (\Exception $e) {
                $this->error("فشل تسوية الاشتراك #{$req->id}: {$e->getMessage()}");
            }
        }

        $warningDate = now()->addDays(3)->startOfDay();
        $upcomingInvoices = Invoice::where('type', 'proforma')
            ->where('status', 'pending')
            ->whereDate('due_date', $warningDate)
            ->with(['subscriptionRequest.parent.user', 'subscriptionRequest.driver.user'])
            ->get();

        foreach ($upcomingInvoices as $invoice) {
            $req = $invoice->subscriptionRequest;
            if (!$req) continue;

            if ($invoice->action_taken !== 'pre_warning_sent') {
                try {
                    $financialService->sendPreSettlementWarning($req);
                    $warned++;
                    $this->info("تم إرسال إنذار للاشتراك #{$req->id}");

                    $invoice->update(['action_taken' => 'pre_warning_sent']);
                } catch (\Exception $e) {
                    $this->error("فشل إرسال إنذار للاشتراك #{$req->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("تم تسوية {$settled} اشتراك، وإرسال {$warned} إنذار.");
        $this->info('انتهت عملية تسوية الاشتراكات.');

        return Command::SUCCESS;
    }
}
