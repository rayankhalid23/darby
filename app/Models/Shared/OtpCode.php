<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    public $timestamps = false; 

    protected $fillable = [
        'email',
        'code_hash', 
        'purpose', 
        'expires_at', 
        'is_used',
        'attempts', 
        'created_at' 
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_used'    => 'boolean',
    ];
}