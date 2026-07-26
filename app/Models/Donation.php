<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    protected $table = 'donations';
    
    protected $fillable = [
        'donor_id',           // ✅ تغيير من user_id
        'campaign_id',
        'amount',
        'currency',
        'payment_method',
        'payment_gateway',
        'status',
        'gateway_status',
        'is_anonymous',
        'is_recurring',
        'is_gift',
        'on_behalf_of',
        'gift_message',
        'receipt_url',
        'crypto_currency',
        'crypto_amount',
        'donated_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_anonymous' => 'boolean',
        'is_recurring' => 'boolean',
        'is_gift' => 'boolean',
        'donated_at' => 'datetime',
    ];

    protected $dates = ['donated_at'];

    // ✅ العلاقة الصحيحة مع DonorProfile
    public function donor()
    {
        return $this->belongsTo(DonorProfile::class);
    }

    // ✅ علاقة shortcut للمستخدم (عبر المتبرع)
    public function user()
    {
        return $this->hasOneThrough(User::class, DonorProfile::class, 'id', 'id', 'donor_id', 'user_id');
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function recurringDonation()
    {
        return $this->hasOne(RecurringDonation::class);
    }

    public function paymentTransaction()
    {
        return $this->hasOne(PaymentTransaction::class);
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('donated_at', [$start, $end]);
    }

    // Helper methods
    public function markAsCompleted()
    {
        $this->status = 'completed';
        $this->save();
        
        // Update campaign collected amount
        if ($this->campaign) {
            $this->campaign->updateCollectedAmount();
        }
        
        // ✅ Update donor profile (مصحح)
        if ($this->donor) {
            $this->donor->addDonation($this->amount);
        }
        
        return $this;
    }

public function isRefundable(): bool
{
   
    if ($this->status !== 'completed') {
        return false;
    }
    
    $refundableHours = config('donation.refundable_hours', 24);
    
    return $this->created_at->diffInHours(now()) <= $refundableHours;
}
}
