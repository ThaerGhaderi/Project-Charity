<?php
// app/Models/SponsorshipPayment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SponsorshipPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sponsorship_id',
        'amount',
        'payment_method',
        'transaction_id',
        'paid_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    // العلاقات
    public function sponsorship()
    {
        return $this->belongsTo(Sponsorship::class);
    }

    // النطاقات
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}