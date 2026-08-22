<?php

namespace App\Models\Driver;

use App\Models\Driver; // أو مسار موديل السائق الحالي عندك
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory, SoftDeletes;

    // 🚀 إجبار الموديل على القراءة من الجدول المنفصل الجديد
    protected $table = 'driver_addresses';

    protected $fillable = [
        'driver_id',
        'label',
        'lat',
        'lng',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    /**
     * علاقة عكسية: العنوان ينتمي إلى سائق واحد
     */
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}