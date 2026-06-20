<?php
// app/Models/AuditLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    
    protected $fillable = [
        'user_id',
        'model_type',
        'model_id',
        'action',
        'old_values',
        'new_values',
        'created_at'
    ];
    
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}