<?php

namespace App\Services\Admin;

use App\Models\Admin\Admin;
use App\Models\Admin\AdminAuditLog;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Shared\SupportTicket;
use App\Models\Shared\SupportTicketMessage;
use App\Services\Notification\NotificationService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * إدارة تذاكر الدعم الفني من جهة الأدمن: التوزيع حسب القسم (تشغيل/طوارئ ← مالية)،
 * الرد، العقوبات التشغيلية، النقل بين الأقسام، والإغلاق والتوثيق.
 * التسوية المالية الفعلية منفصلة في SupportTicketSettlementService.
 */
class SupportTicketAdminService
{
    public const PENALTY_HIDE_DRIVER   = 'hide_driver_from_search';
    public const PENALTY_BLOCK_PARENT  = 'block_parent_booking';

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function getQueue(string $scope, array $filters = []): LengthAwarePaginator
    {
        $query = SupportTicket::with(['user', 'targetUser', 'referenceable', 'assignedAdmin.user'])
            ->where('scope', $scope);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        return $query->latest()->paginate(15);
    }

    public function getDetail(int $ticketId): SupportTicket
    {
        return SupportTicket::with([
            'user', 'targetUser', 'referenceable', 'assignedAdmin.user', 'closedByAdmin.user', 'messages.admin.user',
        ])->findOrFail($ticketId);
    }

    public function reply(int $ticketId, Admin $admin, string $message): SupportTicketMessage
    {
        $ticket = SupportTicket::findOrFail($ticketId);

        if ($ticket->status === SupportTicket::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'ticket' => ['هذه التذكرة مغلقة ولا يمكن الرد عليها.'],
            ]);
        }

        $ticket->update(['assigned_admin_id' => $ticket->assigned_admin_id ?? $admin->id]);

        $ticketMessage = SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'admin_id'  => $admin->id,
            'is_admin'  => true,
            'message'   => $message,
        ]);

        $this->notifyTicketOwner($ticket, 'support_ticket_reply', 'رد جديد على تذكرتك 💬', 'قام الدعم الفني بالرد على تذكرتك، يرجى مراجعتها.');

        return $ticketMessage;
    }

    public function applyPenalty(int $ticketId, Admin $admin, string $penaltyAction): SupportTicket
    {
        $ticket = SupportTicket::findOrFail($ticketId);

        if ($ticket->category !== SupportTicket::CATEGORY_PARTY || !$ticket->target_user_id) {
            throw ValidationException::withMessages([
                'ticket' => ['لا يمكن تطبيق عقوبة تشغيلية إلا على تذكرة محددة الطرف (سائق أو ولي أمر).'],
            ]);
        }

        return DB::transaction(function () use ($ticket, $admin, $penaltyAction) {
            if ($penaltyAction === self::PENALTY_HIDE_DRIVER) {
                if ($ticket->target_role !== 'driver') {
                    throw ValidationException::withMessages([
                        'penalty_action' => ['هذه العقوبة تُطبَّق فقط على تذاكر السائقين.'],
                    ]);
                }
                Driver::where('user_id', $ticket->target_user_id)->update(['hidden_from_search' => true]);
            } elseif ($penaltyAction === self::PENALTY_BLOCK_PARENT) {
                if ($ticket->target_role !== 'parent') {
                    throw ValidationException::withMessages([
                        'penalty_action' => ['هذه العقوبة تُطبَّق فقط على تذاكر أولياء الأمور.'],
                    ]);
                }
                ParentModel::where('user_id', $ticket->target_user_id)->update(['booking_blocked' => true]);
            } else {
                throw ValidationException::withMessages([
                    'penalty_action' => ['إجراء العقوبة غير معروف.'],
                ]);
            }

            $ticket->update([
                'penalty_action'    => $penaltyAction,
                'assigned_admin_id' => $ticket->assigned_admin_id ?? $admin->id,
            ]);

            $this->logAudit($admin, 'apply_ticket_penalty', $ticket, [
                'penalty_action' => $penaltyAction,
            ]);

            return $ticket->fresh();
        });
    }

    public function transferToFinancial(int $ticketId, Admin $admin, string $note): SupportTicket
    {
        $ticket = SupportTicket::findOrFail($ticketId);

        if ($ticket->scope === SupportTicket::SCOPE_FINANCIAL) {
            throw ValidationException::withMessages([
                'ticket' => ['هذه التذكرة محوَّلة للمالية بالفعل.'],
            ]);
        }

        $ticket->update([
            'scope'         => SupportTicket::SCOPE_FINANCIAL,
            'transfer_note' => $note,
        ]);

        $this->logAudit($admin, 'transfer_ticket_to_financial', $ticket, ['note' => $note]);

        return $ticket->fresh();
    }

    public function getAllTickets(array $filters = []): LengthAwarePaginator
    {
        $query = SupportTicket::with(['user', 'targetUser', 'referenceable', 'assignedAdmin.user']);

        if (!empty($filters['scope'])) {
            $query->where('scope', $filters['scope']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        return $query->latest()->paginate(15);
    }

    public function updateStatus(int $ticketId, Admin $admin, string $status, ?string $resolutionNote = null): SupportTicket
    {
        $ticket = SupportTicket::findOrFail($ticketId);

        $allowedStatuses = [
            SupportTicket::STATUS_OPEN,
            SupportTicket::STATUS_IN_PROGRESS,
            SupportTicket::STATUS_RESOLVED,
            SupportTicket::STATUS_CLOSED,
            SupportTicket::STATUS_REJECTED,
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => ['الحالة المحددة غير صالحة.'],
            ]);
        }

        $updateData = [
            'status'            => $status,
            'assigned_admin_id' => $ticket->assigned_admin_id ?? $admin->id,
        ];

        if ($resolutionNote !== null) {
            $updateData['resolution_note'] = $resolutionNote;
        }

        if ($status === SupportTicket::STATUS_CLOSED || $status === SupportTicket::STATUS_RESOLVED) {
            $updateData['closed_by'] = $admin->id;
            $updateData['closed_at'] = now();
        }

        $ticket->update($updateData);

        $this->logAudit($admin, 'update_ticket_status', $ticket, [
            'status'          => $status,
            'resolution_note' => $resolutionNote,
        ]);

        $statusTitles = [
            SupportTicket::STATUS_IN_PROGRESS => 'تذكرتك قيد المراجعة 🔄',
            SupportTicket::STATUS_RESOLVED    => 'تم حل تذكرتك بنجاح ✅',
            SupportTicket::STATUS_CLOSED      => 'تم إغلاق تذكرتك 🔒',
            SupportTicket::STATUS_REJECTED    => 'تم رفض التذكرة ⚠️',
        ];

        $title = $statusTitles[$status] ?? 'تحديث على تذكرة الدعم الفني';
        $message = $resolutionNote 
            ? "قام المشرف بتحديث التذكرة: {$resolutionNote}" 
            : "تم تغيير حالة تذكرتك إلى: {$status}";

        $this->notifyTicketOwner($ticket, 'support_ticket_status_changed', $title, $message);

        return $ticket->fresh(['user', 'targetUser', 'referenceable', 'assignedAdmin.user', 'closedByAdmin.user']);
    }

    public function close(int $ticketId, Admin $admin, ?string $resolutionNote): SupportTicket
    {
        return $this->updateStatus($ticketId, $admin, SupportTicket::STATUS_CLOSED, $resolutionNote);
    }

    protected function notifyTicketOwner(SupportTicket $ticket, string $type, string $title, string $message): void
    {
        try {
            $owner = $ticket->user ?? $ticket->user()->first();
            if ($owner && $this->notificationService) {
                $this->notificationService->sendToUser($owner, $type, [
                    'title'       => $title,
                    'message'     => $message,
                    'entity_type' => 'support_ticket',
                    'entity_id'   => (string) $ticket->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("فشل إرسال إشعار صاحب التذكرة #{$ticket->id}: " . $e->getMessage());
        }
    }

    protected function logAudit(Admin $admin, string $action, SupportTicket $ticket, array $changes = []): void
    {
        try {
            AdminAuditLog::create([
                'admin_id'    => $admin->id,
                'admin_name'  => $admin->user?->full_name,
                'admin_role'  => $admin->user?->role?->name,
                'action'      => $action,
                'entity_type' => 'support_ticket',
                'entity_id'   => $ticket->id,
                'entity_name' => "تذكرة دعم فني #{$ticket->id}",
                'result'      => 'success',
                'changes'     => $changes,
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("فشل تسجيل سجل التدقيق للتذكرة #{$ticket->id}: " . $e->getMessage());
        }
    }
}
