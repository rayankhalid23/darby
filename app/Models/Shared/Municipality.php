<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Municipality extends Model
{
    protected $fillable = ['name'];

    // البلدية الكبرى تحتوي على العديد من البلديات الأصغر
    public function subMunicipalities(): HasMany
    {
        return $this->hasMany(SubMunicipality::class);
    }

    /**
     * كل مناطق البلدية عبر المحلات التابعة لها.
     *
     * واجهة الأدمن تتعامل مع مستويين فقط (بلدية ← منطقة) وتخفي المحلة تماماً،
     * وهذه العلاقة هي الجسر الذي يتيح ذلك دون تغيير بنية قاعدة البيانات.
     */
    public function zones(): HasManyThrough
    {
        return $this->hasManyThrough(
            Zone::class,
            SubMunicipality::class,
            'municipality_id',      // المفتاح في جدول sub_municipalities
            'sub_municipality_id',  // المفتاح في جدول zones
            'id',
            'id'
        );
    }
}