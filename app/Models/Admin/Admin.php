<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Admin extends Model
{
    protected $table = 'admins';

    // جدول admins يحتوي على (id, user_id, created_by) فقط بدون أعمدة تواريخ
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'created_by'
    ];

    /**
     * علاقة المشرف بحسابه الأساسي في جدول المستخدمين
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * علاقة المشرف بالشخص الذي قام بإنشائه
     * العمود created_by مرتبط بمفتاح أجنبي على users.id وليس على admins.id
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}