<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripTracking extends Model
{
    protected $table = 'trip_tracking';

    // إذا كان جدولك لا يحتوي على created_at و updated_at الافتراضيين، اجعلها false
    // لكن من لوج الـ SQL السابق رأيت أنه يحاول إدخالهما، لذا سنبقيها True 
    // وإذا ألغيتهم من قاعدة البيانات مستقبلاً اجعل هذا السطر false
    public $timestamps = false;

    protected $fillable = [
        'trip_id', 
        'latitude', 
        'longitude', 
        'speed', 
        'accuracy', // 👈 أضف هذا الحقل احتياطاً بما أنه موجود بقاعدة البيانات
        'recorded_at'
    ];

    /**
     * تحويل الحقول إلى كائنات Carbon (Date Casting)
     */
    protected $casts = [
        'latitude'    => 'float',
        'longitude'   => 'float',
        'speed'       => 'float',
        'accuracy'    => 'float',
        'recorded_at' => 'datetime', // 👈 لتتمكن من تنسيق الوقت والتاريخ بسهولة
    ];

    /**
     * علاقة التتبع بالرحلة
     */
    public function trip(): BelongsTo
    {
        // استخدام المسار الكامل للتأكد من عدم حدوث تداخل Namespaces
        return $this->belongsTo(\App\Models\Trip::class, 'trip_id'); 
    }
}