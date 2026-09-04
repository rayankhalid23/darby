<?php

namespace App\Console\Commands;

use App\Models\Shared\MasterEscrowVault;
use App\Services\Shared\FinancialLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * يقارن أحواض الخزينة المركزية بالالتزامات الحقيقية المستخرجة من الجداول،
 * ويصحّحها عند الطلب.
 *
 * ⚠️ لماذا يلزم هذا الأمر: كانت أحواض الخزينة تتحرّك بشكل غير متسق —
 * الشحن يزيد حوض الأمانات رغم أن المال في المحفظة، والحجز يزيده ثانيةً بنفس
 * المال، وتسوية الرحلة تودع في محفظة السائق بلا زيادة في حوضه، والسحب يخرج
 * بلا أي أثر. النتيجة أرصدة أحواض متراكمة لا تقابلها أي التزامات فعلية.
 *
 * إصلاح الكود يوقف تراكم الانحراف من الآن فصاعداً، لكنه لا يصحّح ما تراكم
 * سابقاً — وهذا الأمر هو الذي يفعل ذلك.
 */
class ReconcileFinancialVault extends Command
{
    protected $signature = 'finance:reconcile {--fix : تطبيق التصحيح فعلياً بدل عرضه فقط}';

    protected $description = 'مطابقة أحواض الخزينة المركزية مع الالتزامات المالية الحقيقية';

    public function handle(FinancialLedgerService $ledger): int
    {
        $result = $ledger->checkDailySolvency();

        $this->newLine();
        $this->line('════════ فحص السلامة المالية ════════');

        foreach ($result['checks'] as $name => $check) {
            $mark = $check['passed'] ? '<fg=green>✔</>' : '<fg=red>✘</>';
            $this->line("{$mark} {$name} — {$check['description']}");

            if (!$check['passed'] && isset($check['expected_dinar'])) {
                $this->line("   المتوقع: {$check['expected_dinar']} د.ل | الفعلي: {$check['actual_dinar']} د.ل");
                $this->line('   الفارق : ' . round($check['difference_cents'] / 100, 2) . ' د.ل');
            }
        }

        if ($result['is_solvent']) {
            $this->newLine();
            $this->info('✅ الأحواض مطابقة للالتزامات الفعلية — لا حاجة لأي تصحيح.');
            return self::SUCCESS;
        }

        if (!$this->option('fix')) {
            $this->newLine();
            $this->warn('لعرض ما سيتغيّر فقط تم تشغيل الأمر بلا تصحيح.');
            $this->warn('لتطبيق التصحيح: php artisan finance:reconcile --fix');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('──────── تطبيق التصحيح ────────');

        DB::transaction(function () use ($result) {
            $vault = MasterEscrowVault::getVault();

            $escrowCheck = $result['checks']['escrow_backing'];
            $mirrorCheck = $result['checks']['driver_wallet_mirror'];

            if (!$escrowCheck['passed']) {
                $target = (int) round($escrowCheck['expected_dinar'] * 100);
                $vault->update(['parents_escrow_pool' => $target]);
                $this->line("   parents_escrow_pool  ← {$escrowCheck['expected_dinar']} د.ل");
            }

            if (!$mirrorCheck['passed']) {
                $target = (int) round($mirrorCheck['expected_dinar'] * 100);
                $vault->update(['driver_available_pool' => $target]);
                $this->line("   driver_available_pool ← {$mirrorCheck['expected_dinar']} د.ل");
            }

            // أي حوض سالب يُصفَّر: القيمة السالبة ليست دَيناً على النظام بل أثر
            // خصم من حوض لم يدخله المال أصلاً.
            foreach (['parents_escrow_pool', 'driver_pending_pool', 'driver_available_pool',
                      'pending_withdrawal_pool', 'platform_revenue_pool', 'penalty_pool'] as $pool) {
                if ((int) $vault->fresh()->{$pool} < 0) {
                    $vault->update([$pool => 0]);
                    $this->line("   {$pool} ← 0 (كان سالباً)");
                }
            }
        });

        $this->newLine();
        $after = $ledger->checkDailySolvency();

        if ($after['is_solvent']) {
            $this->info('✅ تم التصحيح — الأحواض الآن مطابقة للالتزامات الفعلية.');
            return self::SUCCESS;
        }

        $this->error('⚠️ بقيت فحوص غير مطابقة بعد التصحيح — راجع التفاصيل أعلاه.');

        return self::FAILURE;
    }
}
