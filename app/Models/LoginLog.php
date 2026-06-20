<?php
// app/Models/LoginLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    protected $table = 'login_logs';
    
    protected $fillable = [
        'user_id',
        'ip_address',
        'device_info',
        'success',
        'logged_at'
    ];
    
    protected $casts = [
        'success' => 'boolean',
        'logged_at' => 'datetime'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}