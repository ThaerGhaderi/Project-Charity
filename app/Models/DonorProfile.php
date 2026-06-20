<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonorProfile extends Model
{
    protected $table = 'donor_profiles';
    
    protected $fillable = [
        'user_id',
        'donor_type',
        'is_anonymous',
        'total_donated',
        'loyalty_points',
        'loyalty_tier',
        'bio'
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'total_donated' => 'integer',
        'loyalty_points' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function donations()
    {
        return $this->hasManyThrough(Donation::class, User::class, 'id', 'user_id', 'user_id', 'id');
    }

    // Update loyalty tier based on points
    public function updateLoyaltyTier()
    {
        if ($this->loyalty_points >= 3000) {
            $this->loyalty_tier = 'ذهبية';
        } elseif ($this->loyalty_points >= 1000) {
            $this->loyalty_tier = 'فضية';
        } elseif ($this->loyalty_points >= 300) {
            $this->loyalty_tier = 'برونزية';
        } else {
            $this->loyalty_tier = null;
        }
        $this->save();
        
        return $this;
    }

    // Add points and update tier
    public function addPoints($points)
    {
        $this->loyalty_points += $points;
        $this->updateLoyaltyTier();
        return $this;
    }

    // Add donation amount to total
    public function addDonation($amount)
    {
        $this->total_donated += $amount;
        $this->save();
        
        // Add loyalty points (1 point per $1)
        $this->addPoints((int)$amount);
        
        return $this;
    }
}