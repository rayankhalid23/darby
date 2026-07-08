<?php

namespace App\Models\Driver;

use App\Models\User;
use App\Models\Student;
use App\Models\Shared\Zone;
use App\Enums\driver\DriverShift;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Driver extends Model
{
    // إلغاء الـ timestamps للمحافظة على هيكلية جدولك الحالي
    public $timestamps = false;
    protected $table = 'drivers';
    protected $guarded = [];

    protected $fillable = [
        'user_id', 
        'gender',
        'shift',
        'subscription_type', // الحقل الجديد المرتبط بنظام الجغرافيا والاشتراكات الجديد
        'national_id', 
        'license_number', 
        'license_expiry', 
        'status',          // معتمد في منطق الموافقة الادارية (Pending, Active, Suspended)
        'current_lat', 
        'current_lng', 
        'last_ping_at'
    ];

    /**
     * تحويل أنواع البيانات تلقائياً (Casts)
     * ضروري لتعامل لارافيل الصحيح مع الإحداثيات والتواريخ والـ Enums
     */
    protected function casts(): array
    {
        return [
            'current_lat'    => 'float',
            'current_lng'    => 'float',
            'last_ping_at'   => 'datetime',
            'license_expiry' => 'date',
            'shift'          => DriverShift::class, // تحويل تلقائي لقراءة الـ value والـ label للـ Enum
        ];
    }

    /**
     * 🗺️ علاقة السائق بالمناطق المخصصة للعمل (Many-to-Many)
     * تربط السائق بالمناطق الدقيقة المتعددة التي يغطيها عبر الجدول الوسيط driver_zones
     */
   // في ملف App\Models\Driver\Driver.php
   public function zones(): BelongsToMany
{
    // تأكد أن أسماء الأعمدة في الجدول الوسيط صحيحة (driver_id, zone_id)
    return $this->belongsToMany(\App\Models\Shared\Zone::class, 'driver_zone', 'driver_id', 'zone_id');
}
    /**
     * علاقة مع المستخدم (حساب السائق الأساسي)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * علاقة مع المركبات التابعة للسائق
     */
    // أضف هذه الدالة داخل كلاس Driver
// داخل كلاس Driver
/**
 * علاقة السائق بالمركبة الخاصة به
 * بناءً على جدول vehicles
 */
public function vehicles(): HasMany // قمت بتغييرها من vehicle إلى vehicles
{
    return $this->hasMany(\App\Models\Driver\Vehicle::class, 'driver_id');
}

    /**
     * علاقة مع وثائق السائق (الرخصة، كتيب المركبة، إلخ)
     */
    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class, 'driver_id');
    }

    /**
     * علاقة مع الموافقات (للسجل الإداري والتدقيق)
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(DriverApproval::class, 'driver_id');
    }

    /**
     * علاقة السائق مع الطلاب المشتركين معه حالياً
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'driver_id');
    }

    /**
     * جلب عناوين السائق المتعددة من الجدول المنفصل
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'driver_id');
    }

    /**
     * 🔥 الفلترة الذكية بالسعة المتبقية للمركبة
     * تفحص (سعة المركبة النشطة - عدد الطلاب الحاليين >= عدد المقاعد المطلوبة للأطفال الجدد)
     */
    public function scopeAvailableCapacity(Builder $query, ?int $requiredSeats): Builder
    {
        if (!$requiredSeats) return $query;

        return $query->whereHas('vehicles', function ($q) use ($requiredSeats) {
            $q->whereRaw('(capacity - (select count(*) from students where students.driver_id = drivers.id)) >= ?', [$requiredSeats]);
        });
    }
    // جلب الاشتراكات الفعالة التابعة للسائق
    public function activeSubscriptions()
    {
        return $this->hasMany(\App\Models\Shared\ActiveSubscription::class, 'driver_id');
    }

    // جلب كافة الرحلات اليومية للسائق
    public function trips()
    {
        return $this->hasMany(\App\Models\Shared\Trip::class, 'driver_id');
    }

    // جلب المسارات الخاصة بالسائق
    public function routes()
    {
        return $this->hasMany(\App\Models\Shared\Route::class, 'driver_id');
    }
}