<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'profile_completed'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',

        ];
    }
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
    public function donor()
    {
        return $this->hasOne(DonorProfile::class);
    }

    public function volunteer()
    {
        return $this->hasOne(VolunterProfile::class);
    }

    public function beneficiary()
    {
        return $this->hasOne(BeneficiaryProfile::class);
    }
}
