<?php

namespace App\Services\Admin;

use App\Models\Admin\Admin;
use App\Models\Admin\AdminAuditLog;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Shared\MasterEscrowVault;
use App\Models\Shared\SupportTicket;
use App\Services\Notification\NotificationService;
use App\Services\Shared\FinancialLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * تنفيذ التسوية المالية من داخل تذكرة دعم فني — يعيد استخدام نفس البنية
 * التحتية المالية الحالية (محافظ bavix/laravel-wallet + FinancialLedgerService)
 * بدل إنشاء آلية مالية موازية، حتى تبقى كل حركة موثّقة في نفس دفتر الأستاذ.
 */
class SupportTicketSettlementService
{
    protected FinancialLedgerService $ledgerService;
    protected NotificationService $notificationService;

    public function __construct(FinancialLedgerService $ledgerService, NotificationService $notificationService)
    {
        $this->ledgerService = $ledgerService;
        $this->notificationService = $notificationService;
    }

    public function executeSettlement(
        SupportTicket $ticket,
        Admin $admin,
        string $direction,
        string $partyRole,
        int $partyUserId,
        float $amountDinar,
        ?string $note = null
    ): SupportTicket {
        $amountCents = (int) round($amountDinar * 100);

        $holder = $partyRole === 'driver'
            ? Driver::where('user_id', $partyUserId)->first()
            : ParentModel::where('user_id', $partyUserId)->first();

        if (!$holder) {
            throw ValidationException::withMessages([
                'party_user_id' => ['تعذر العثور على محفظة الطرف المحدد.'],
            ]);
        }

        return DB::transaction(function () use ($ticket, $admin, $direction, $partyRole, $partyUserId, $amountCents, $note, $holder) {
            $balBefore = $holder->balance;
            $vault = MasterEscrowVault::getVault();
            $walletAccount = ($partyRole === 'driver' ? 'driver_wallet_' : 'parent_wallet_') . $partyUserId;

            if ($direction === 'credit') {
                $holder->deposit($amountCents);
                $vault->decrement('platform_revenue_pool', $amountCents);

                $this->ledgerService->recordLedgerEntry(
                    'platform_revenue_pool',
                    $walletAccount,
                    $amountCents,
                    'ticket_settlement_credit',
                    $balBefore,
                    $holder->fresh()->balance,
                    "TICKET-{$ticket->id}",
                    ['ticket_id' => $ticket->id, 'note' => $note]
                );
            } else { // debit
                if ($balBefore < $amountCents) {
                    throw ValidationException::withMessages([
                        'amount' => ['رصيد الطرف المستهدف غير كافٍ لتنفيذ هذا الخصم.'],
                    ]);
                }

                $holder->withdraw($amountCents);
                $vault->increment('platform_revenue_pool', $amountCents);

                $this->ledgerService->recordLedgerEntry(
                    $walletAccount,
                    'platform_revenue_pool',
                    $amountCents,
                    'ticket_settlement_debit',
                    $balBefore,
                    $holder->fresh()->balance,
                    "TICKET-{$ticket->id}",
                    ['ticket_id' => $ticket->id, 'note' => $note]
                );
            }

            $ticket->update([
                'assigned_admin_id' => $ticket->assigned_admin_id ?? $admin->id,
                'resolution_note'   => $note ?? $ticket->resolution_note,
            ]);

            $this->logAudit($admin, $ticket, $direction, $partyRole, $partyUserId, $amountCents, $note);
            $this->notifySettlement($ticket, $partyRole, $partyUserId, $direction, $amountCents);

            return $ticket->fresh();
        });
    }

    protected function notifySettlement(SupportTicket $ticket, string $partyRole, int $partyUserId, string $direction, int $amountCents): void
    {
        try {
            $user = \App\Models\User::find($partyUserId);
            if (!$user) {
                return;
            }

            $amount = number_format($amountCents / 100, 2);
            $message = $direction === 'credit'
                ? "تم تحويل مبلغ ({$amount} د.ل) إلى محفظتك كتسوية لتذكرة الدعم الفني رقم {$ticket->id}."
                : "تم خصم مبلغ ({$amount} د.ل) من محفظتك كتسوية لتذكرة الدعم الفني رقم {$ticket->id}.";

            $this->notificationService->sendToUser($user, 'support_ticket_settlement', [
                'title'       => 'تسوية مالية على تذكرتك 💰',
                'message'     => $message,
                'entity_type' => 'support_ticket',
                'entity_id'   => (string) $ticket->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning("فشل إرسال إشعار التسوية المالية للتذكرة #{$ticket->id}: " . $e->getMessage());
        }
    }

    protected function logAudit(Admin $admin, SupportTicket $ticket, string $direction, string $partyRole, int $partyUserId, int $amountCents, ?string $note): void
    {
        try {
            AdminAuditLog::create([
                'admin_id'    => $admin->id,
                'admin_name'  => $admin->user?->full_name,
                'admin_role'  => $admin->user?->role?->name,
                'action'      => 'settle_ticket_financial',
                'entity_type' => 'support_ticket',
                'entity_id'   => $ticket->id,
                'entity_name' => "تسوية تذكرة #{$ticket->id}",
                'result'      => 'success',
                'changes'     => [
                    'direction'      => $direction,
                    'party_role'     => $partyRole,
                    'party_user_id'  => $partyUserId,
                    'amount_cents'   => $amountCents,
                    'note'           => $note,
                ],
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("فشل تسجيل سجل التدقيق لتسوية التذكرة #{$ticket->id}: " . $e->getMessage());
        }
    }
}
