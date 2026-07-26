<?php

namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Authenticatable
{
    use HasApiTokens;
    protected $fillable = [
        'full_name',
        'password',
        'role',
    ];
    protected $hidden = [
        'password',
    ];
}
