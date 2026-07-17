<?php

namespace App\Models\Parent;

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Traits\HasWallet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ParentModel extends Model implements Wallet
{
    use HasFactory, HasWallet;

    protected $table = 'parents';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'is_trusted'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function addresses()
    {
        return $this->hasMany(Address::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Child::class, 'parent_id');
    }
}