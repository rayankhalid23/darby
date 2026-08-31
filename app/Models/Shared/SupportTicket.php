<?php

namespace App\Models\Shared;

use App\Models\Admin\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SupportTicket extends Model
{
    protected $table = 'support_tickets';

    // فئات التذكرة
    public const CATEGORY_GENERAL   = 'general';
    public const CATEGORY_TECHNICAL = 'technical';
    public const CATEGORY_FINANCIAL = 'financial';
    public const CATEGORY_TRIP      = 'trip';
    public const CATEGORY_PARTY     = 'party';
    public const CATEGORY_DRIVER    = 'driver';
    public const CATEGORY_PARENT    = 'parent';

    // الأقسام الإدارية المالكة للتذكرة حالياً
    public const SCOPE_OPERATIONS = 'operations';
    public const SCOPE_FINANCIAL  = 'financial';

    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED    = 'resolved';
    public const STATUS_CLOSED      = 'closed';
    public const STATUS_REJECTED    = 'rejected';

    protected $fillable = [
        'user_id',
        'creator_role',
        'category',
        'referenceable_type',
        'referenceable_id',
        'target_role',
        'target_user_id',
        'description',
        'attachments',
        'status',
        'scope',
        'assigned_admin_id',
        'transfer_note',
        'penalty_action',
        'resolution_note',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'closed_at'   => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    public function closedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'closed_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id')->oldest();
    }
}
