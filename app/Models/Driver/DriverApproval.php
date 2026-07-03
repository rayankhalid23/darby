<?php

namespace App\Models\Driver;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverApproval extends Model
{
    // تعيين الجدول بشكل صريح ليتطابق مع قاعدة البيانات
    protected $table = 'driver_approvals';

    protected $fillable = [
        'driver_id',
        'admin_id',
        'status',
        'rejection_reason',
        'created_at' // 🚀 أضفناه هنا ليتم تعبئته تلقائياً أو عبر السيرفس
    ];

    // 🚀 نخبر لارافيل أننا نريد فقط تاريخ الإنشاء وتجاهل تاريخ التحديث تماماً
    const UPDATED_AT = null;

    // إيقاف الـ timestamps الافتراضية لأننا نعتمد على created_at فقط
    public $timestamps = false;

    /**
     * تحويل أنواع البيانات (Casts) لضمان قراءة التاريخ ككائن متطور
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Admin\Admin::class, 'admin_id');
    }
    /**
 * علاقة السائق بالوثائق المرفوعة
 * بناءً على جدول driver_documents في image_075cae.png
 */
public function documents()
{
    return $this->hasMany(DriverDocument::class, 'driver_id');
}
}