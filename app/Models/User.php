<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens; 
use App\Models\Role;
use App\Models\Driver\Driver; 
use App\Models\Admin\Admin;
use App\Models\Parent\ParentModel;
use Filament\Models\Contracts\HasName;

class User extends Authenticatable implements HasName
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes; 

    protected $table = 'users';

    public $timestamps = true;

    protected $fillable = [
        'full_name',
        'email',
        'phone_number',
        'password_hash',
        'avatar_url',
        'role_id',
        'is_active',
        'phone_verified',
        'email_verified_at',
        'phone_verified_at',
        'alternative_phone',
        'last_login_at',
        'new_email_temporary',
        'pending_new_email', // أضفنا هذا الحقل المطابق لكود الـ Service
        'email_change_pending',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token'
    ];

    protected function casts(): array
    {
        return [
            'is_active'            => 'boolean',
            'phone_verified'       => 'boolean',
            'email_verified_at'    => 'datetime',
            'phone_verified_at'    => 'datetime',
            'last_login_at'        => 'datetime',
            'created_at'           => 'datetime',
            'updated_at'           => 'datetime', // تصحيح الحرف الكبير
            'deleted_at'           => 'datetime', // تصحيح الحرف الكبير
            'email_change_pending' => 'boolean',
        ];
    }

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function parentProfile()
    {
        return $this->hasOne(ParentModel::class, 'user_id');
    }

    public function parent()
    {
        return $this->hasOne(ParentModel::class, 'user_id');
    }

    public function driver()
    {
        return $this->hasOne(Driver::class, 'user_id');
    }
    
    public function admin()
    {
        return $this->hasOne(Admin::class, 'user_id');
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class, 'user_id');
    }

    public function getFilamentName(): string
    {
        return $this->full_name ?? 'مدير النظام';
    }
}