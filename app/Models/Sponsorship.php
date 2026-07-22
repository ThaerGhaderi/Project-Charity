<?php
// app/Models/Sponsorship.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sponsorship extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sponsor_id',
        'beneficiary_id',
        'campaign_id',
        'type',
        'amount',
        'currency',
        'start_date',
        'end_date',
        'status',
        'payment_method',
        'payment_frequency',
        'is_anonymous',
        'message',
        'beneficiary_message',
        'next_payment_date',
        'last_payment_date',
        'total_paid',
        'remaining_payments',
        'auto_renew',
        'cancelled_reason',
        'cancelled_at',
        'completed_at',
        'admin_notes',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_payment_date' => 'date',
        'last_payment_date' => 'date',
        'is_anonymous' => 'boolean',
        'auto_renew' => 'boolean',
        'total_paid' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    // ✅ أنواع الكفالة بالعربية
    const TYPES = [
        'شهرية' => 'شهرية',
        'اسبوعية' => 'اسبوعية',
        'سنوية' => 'سنوية',
        'مرة واحدة' => 'مرة واحدة',
    ];

    // ✅ حالات الكفالة بالعربية
    const STATUSES = [
        'قيد الانتظار' => 'قيد الانتظار',
        'نشطة' => 'نشطة',
        'مكتملة' => 'مكتملة',
        'ملغية' => 'ملغية',
        'معلقة' => 'معلقة',
    ];

    // العلاقات
    public function sponsor()
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    public function beneficiary()
    {
        return $this->belongsTo(User::class, 'beneficiary_id');
    }

    public function beneficiaryProfile()
    {
        return $this->belongsTo(BeneficiaryProfile::class, 'beneficiary_id', 'user_id');
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function payments()
    {
        return $this->hasMany(SponsorshipPayment::class);
    }

    public function messages()
    {
        return $this->hasMany(SponsorshipMessage::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // النطاقات
    public function scopeActive($query)
    {
        return $query->where('status', 'نشطة');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'قيد الانتظار');
    }

    public function scopeBySponsor($query, $userId)
    {
        return $query->where('sponsor_id', $userId);
    }

    public function scopeByBeneficiary($query, $userId)
    {
        return $query->where('beneficiary_id', $userId);
    }

    public function scopeNeedsPayment($query)
    {
        return $query->where('status', 'نشطة')
            ->where('next_payment_date', '<=', now()->addDays(7));
    }

    // الخصائص المحسوبة
    public function getStatusTextAttribute()
    {
        return $this->status;
    }

    public function getTypeTextAttribute()
    {
        return $this->type;
    }

    public function getRemainingAmountAttribute()
    {
        if ($this->type === 'مرة واحدة') {
            return $this->amount - $this->total_paid;
        }
        
        return $this->remaining_payments * $this->amount;
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->type === 'مرة واحدة') {
            return $this->amount > 0 ? ($this->total_paid / $this->amount) * 100 : 0;
        }
        
        $totalPayments = $this->remaining_payments + $this->payments()->count();
        return $totalPayments > 0 ? ($this->payments()->count() / $totalPayments) * 100 : 0;
    }

    public function getIsActiveAttribute()
    {
        return $this->status === 'نشطة';
    }

    public function getIsCompletedAttribute()
    {
        return $this->status === 'مكتملة';
    }

    public function isValid()
    {
        return $this->status === 'نشطة' && 
               ($this->end_date === null || $this->end_date >= now());
    }

    public function calculateNextPaymentDate()
    {
        if ($this->type === 'مرة واحدة') {
            return null;
        }

        $lastPayment = $this->payments()->latest()->first();
        $baseDate = $lastPayment ? $lastPayment->paid_at : $this->start_date;

        return match ($this->type) {
            'شهرية' => $baseDate->addMonth(),
            'اسبوعية' => $baseDate->addWeek(),
            'سنوية' => $baseDate->addYear(),
            default => null,
        };
    }

    public function recordPayment($amount, $paymentMethod, $transactionId = null)
    {
        $payment = $this->payments()->create([
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId,
            'paid_at' => now(),
            'status' => 'مكتملة',
        ]);

        $this->total_paid += $amount;
        $this->last_payment_date = now();
        $this->remaining_payments = max(0, $this->remaining_payments - 1);
        $this->next_payment_date = $this->calculateNextPaymentDate();

        if ($this->type === 'مرة واحدة' && $this->total_paid >= $this->amount) {
            $this->status = 'مكتملة';
            $this->completed_at = now();
        } elseif ($this->remaining_payments <= 0) {
            $this->status = 'مكتملة';
            $this->completed_at = now();
        }

        $this->save();

        return $payment;
    }
}