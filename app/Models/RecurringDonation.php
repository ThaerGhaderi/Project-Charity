<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringDonation extends Model
{
    protected $fillable = [
        'donation_id',
        'user_id',
        'campaign_id',
        'amount',
        'frequency',
        'next_charge_date',
        'is_active'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'next_charge_date' => 'date',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function donation()
    {
        return $this->belongsTo(Donation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDueForCharge($query)
    {
        return $query->where('is_active', true)
                     ->where('next_charge_date', '<=', now()->toDateString());
    }

    // Helper methods
    public function processCharge()
    {
        // This would integrate with payment gateway
        // For now, create a new donation record
        $donation = Donation::create([
            'user_id' => $this->user_id,
            'campaign_id' => $this->campaign_id,
            'amount' => $this->amount,
            'status' => 'pending',
            'is_recurring' => true
        ]);
        
        // Update next charge date based on frequency
        switch ($this->frequency) {
            case 'daily':
                $this->next_charge_date = now()->addDay()->toDateString();
                break;
            case 'weekly':
                $this->next_charge_date = now()->addWeek()->toDateString();
                break;
            case 'monthly':
                $this->next_charge_date = now()->addMonth()->toDateString();
                break;
        }
        
        $this->save();
        
        return $donation;
    }

    public function cancel()
    {
        $this->is_active = false;
        $this->save();
        
        return $this;
    }
}