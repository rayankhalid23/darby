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

// *** إضـافـة 1: استدعاء واجهة Filament ***
use Filament\Models\Contracts\HasName;

// *** إضـافـة 2: تطبيق الواجهة على الكلاس (implements HasName) ***
class User extends Authenticatable implements HasName
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes; 

    protected $table = 'users';

    // *** التعديل 1: إخبار لارافيل بعدم استخدام timestamps التلقائية ***
    public $timestamps = true;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at'; // بحرف U كبير كما طلبته بدقة
    const DELETED_AT = 'deleted_at';

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
        'email_change_pending',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token'
    ];

    protected function casts(): array
    {
        return [
            'is_active'         => 'boolean',
            'phone_verified'    => 'boolean',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            // *** التعديل 2: إزالة 'created_at', 'updated_at' لأننا ألغينا الـ timestamps ***
            'created_at'        => 'datetime',
            'Update_at'         => 'datetime',
            'Delete_at'         => 'datetime',
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

    public function driver()
    {
        return $this->hasOne(Driver::class, 'user_id');
    }
    
    public function admin()
    {
        return $this->hasOne(Admin::class, 'user_id');
    }

    public function parent()
    {
        return $this->hasOne(ParentModel::class); // تأكد من اسم الكلاس الصحيح
    }

    // *** إضـافـة 3: الدالة التي تخبر النظام بقراءة حقل full_name بدلاً من name ***
    public function getFilamentName(): string
    {
        return $this->full_name ?? 'مدير النظام';
    }
}