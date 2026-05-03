<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $fillable = [
        'email',    // ✅ أضف هذا
        'otp',
        'type',     // ✅ أضف هذا
        'expires_at',
        'is_used'
    ];
    
    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
    ];
    
    public function isValid(): bool
    {
        return !$this->is_used && $this->expires_at->isFuture();
    }
}