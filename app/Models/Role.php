<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'display_name',
        'permissions',
        'description',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * علاقة الدور بالمستخدمين التابعين له
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
