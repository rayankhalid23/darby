<?php

namespace App\Models\Parent;

use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{

    use SoftDeletes;
    protected $table = 'addresses';
    
    // إلغاء الزمن بناءً على طلبك والـ ERD
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'label',
        'lat',
        'lng',
    ];

    /**
     * العنوان يعود لولي أمر محدد
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ParentModel::class, 'parent_id'); // تأكد من اسم كلاس ولي الأمر لديك
    }

    /**
     * العنوان مرتبك بالعديد من الأطفال كعنوان انطلاق لهم
     */
    public function children(): HasMany
    {
        return $this->hasMany(\App\Models\Parent\Child::class, 'home_address_id');
    }
}