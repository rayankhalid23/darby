<?php

namespace App\Console\Commands;

use App\Models\Shared\Contract;
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

        $contracts = Contract::with(['parent', 'driver'])
            ->where('status', 'active')
            ->whereDate('end_date', '<=', $today)
            ->get();

        $this->info("تم العثور على {$contracts->count()} عقد للتسوية.");

        $settled = 0;
        $warned = 0;

        foreach ($contracts as $contract) {
            $alreadySettled = Invoice::where('contract_id', $contract->id)
                ->where('type', 'final')
                ->exists();

            if ($alreadySettled) {
                continue;
            }

            try {
                $financialService->settleContract($contract);
                $settled++;
                $this->info("تم تسوية العقد {$contract->contract_number}");
            } catch (\Exception $e) {
                $this->error("فشل تسوية العقد {$contract->contract_number}: {$e->getMessage()}");
            }
        }

        $warningDate = now()->addDays(3)->startOfDay();
        $upcomingContracts = Contract::with(['parent.user', 'driver.user'])
            ->where('status', 'active')
            ->whereDate('end_date', $warningDate)
            ->get();

        foreach ($upcomingContracts as $contract) {
            $alreadyWarned = Invoice::where('contract_id', $contract->id)
                ->where('action_taken', 'pre_warning_sent')
                ->exists();

            if (!$alreadyWarned) {
                try {
                    $financialService->sendPreSettlementWarning($contract);
                    $warned++;
                    $this->info("تم إرسال إنذار للعقد {$contract->contract_number}");

                    Invoice::where('contract_id', $contract->id)
                        ->where('type', 'proforma')
                        ->update(['action_taken' => 'pre_warning_sent']);
                } catch (\Exception $e) {
                    $this->error("فشل إرسال إنذار للعقد {$contract->contract_number}: {$e->getMessage()}");
                }
            }
        }

        $this->info("تم تسوية {$settled} عقد، وإرسال {$warned} إنذار.");
        $this->info('انتهت عملية تسوية الاشتراكات.');

        return Command::SUCCESS;
    }
}
