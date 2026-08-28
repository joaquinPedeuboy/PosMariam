<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = ['nombre', 'apellido', 'email', 'password', 'telefono'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // hashea automáticamente al asignar, sin necesidad de Hash::make manual
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
