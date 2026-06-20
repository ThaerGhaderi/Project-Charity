<?php
// app/Models/PaymentTransaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $table = 'payment_transactions';
    
    protected $fillable = [
        'donation_id',
        'gateway_ref',
        'amount',
        'currency',
        'status',
        'gateway_response',
        'processed_at'
    ];
    
    protected $casts = [
        'gateway_response' => 'array',
        'processed_at' => 'datetime',
        'amount' => 'decimal:2'
    ];
    
    // العلاقة العكسية
    public function donation()
    {
        return $this->belongsTo(Donation::class);
    }
}