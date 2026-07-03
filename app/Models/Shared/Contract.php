<?php

namespace App\Models\Shared;

use App\Models\Parent\ParentModel;
use App\Models\Driver\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Shared\ActiveSubscription;

class Contract extends Model
{
    protected $table = 'contracts';

    protected $fillable = [
        'subscription_request_id',
        'parent_id',
        'driver_id',
        'contract_number',
        'subscription_type',
        'direction',
        'timing',
        'start_date',
        'end_date',
        'days_count',
        'total_price',
        'pickup_time', // تأكد من إضافته هنا
        'dropoff_time',
        'clauses',
        'status',
        'max_waiting_time',
        'signed_at',
        'pdf_path'
    ];

    protected $casts = [
        'clauses'    => 'array',
        'start_date' => 'date',
        'end_date'   => 'date',
        'signed_at'  => 'datetime',
        'total_price'=> 'decimal:2',
    ];

    // ============================================================
    // العلاقات
    // ============================================================

    public function subscriptionRequest(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRequest::class, 'subscription_request_id');
    }

    /**
     * ولي الأمر — العلاقة مع جدول parents
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ParentModel::class, 'parent_id');
    }

    /**
     * السائق — العلاقة مع جدول drivers
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    /**
     * الاشتراكات النشطة المولّدة من هذا العقد (طفل بطفل)
     */
    public function activeSubscription()
    {
        // بافتراض أن جدول العقود يحتوي على active_subscription_id
        return $this->belongsTo(ActiveSubscription::class, 'active_subscription_id');
    }

    /**
     * الاشتراكات النشطة المرتبطة بهذا العقد
     */
    public function activeSubscriptions(): HasMany
    {
        return $this->hasMany(ActiveSubscription::class, 'contract_id');
    }

    // ============================================================
    // توليد رقم عقد فريد
    // ============================================================

    /**
     * ينشئ رقم عقد بصيغة: DRBY-YYYY-000001
     */
    public static function generateContractNumber(): string
    {
        $year       = now()->format('Y');
        $lastId     = static::max('id') ?? 0;
        $sequential = str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);
        return "DRBY-{$year}-{$sequential}";
    }

    // ============================================================
    // دوال مساعدة
    // ============================================================

    public function getDirectionTextAttribute(): string
    {
        return match($this->direction) {
            'go'     => 'ذهاب فقط',
            'return' => 'إياب فقط',
            'both'   => 'ذهاب وإياب',
            default  => $this->direction,
        };
    }

    public function getSubscriptionTypeTextAttribute(): string
    {
        return match($this->subscription_type) {
            'monthly' => 'اشتراك شهري',
            'daily'   => 'اشتراك يومي',
            default   => $this->subscription_type,
        };
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            'active'     => 'نشط وساري',
            'terminated' => 'منتهي',
            default      => 'غير معروف',
        };
    }
}