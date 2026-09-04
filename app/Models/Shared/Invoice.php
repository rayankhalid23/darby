<?php

namespace App\Models\Shared;

use App\Models\User;
use App\Models\Driver\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $fillable = [
        'subscription_request_id',
        'parent_id',
        'driver_id',
        'invoice_number',
        'amount',
        'type',
        'status',
        'due_date',
        'subscription_type',
        'total_trips',
        'completed_trips',
        'driver_absences',
        'student_absences',
        'calculated_amount',
        'action_taken',
        // ⚠️ كان مسار شحن المحفظة يمرّر هذين الحقلين لـ Invoice::create() وهما
        // غير موجودين لا في $fillable ولا في الجدول، فتُهمل بيانات بوابة الدفع
        // بصمت ويتعذّر مطابقة الإيصال بمعاملته عند أي نزاع.
        'payment_method',
        'details',
        'paid_at',
        'resolved_at',
    ];

    protected $casts = [
        'amount'            => 'decimal:2',
        'calculated_amount' => 'decimal:2',
        'details'           => 'array',
        'due_date'          => 'date',
        'paid_at'           => 'datetime',
        'resolved_at'       => 'datetime',
    ];

    public function subscriptionRequest(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRequest::class, 'subscription_request_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }
}
