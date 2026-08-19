<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    use HasFactory;

    protected $table = 'user_devices';

    protected $fillable = [
        'user_id',
        'device_id',
        'fcm_token',
        'device_name',
        'platform',
        'app_version',
        'is_active',
        'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'       => 'boolean',
            'last_active_at'  => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
