<?php

namespace App\Models;

use App\Notifications\VerifyCustomerEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

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

    public function sendEmailVerificationNotification(
    string $redirect = '/mi-cuenta'
    ): void {
        $this->notify(new VerifyCustomerEmail($redirect));
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }
}
