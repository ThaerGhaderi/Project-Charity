<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    protected $fillable = [
        'user_id',
        'campaign_id',
        'amount',
        'currency',
        'payment_method',
        'status',
        'is_anonymous',
        'is_recurring',
        'on_behalf_of',
        'gift_message',
        'receipt_url'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_anonymous' => 'boolean',
        'is_recurring' => 'boolean',
        'donated_at' => 'datetime',
    ];

    protected $dates = ['donated_at'];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
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
        return $this->hasOne(paymentTransaction::class);
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
        $this->campaign->updateCollectedAmount();
        
        // Update donor profile
        if ($this->user && $this->user->donor) {
            $this->user->donor->addDonation($this->amount);
        }
        
        return $this;
    }

    public function isRefundable()
    {
        // Only refundable within 24 hours
        return $this->status === 'completed' && 
               $this->donated_at->diffInHours(now()) <= 24;
    }
    
}