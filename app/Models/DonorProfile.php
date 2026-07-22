<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonorProfile extends Model
{
    protected $table = 'donor_profiles';
    
    protected $fillable = [
         'name',
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

    // ✅ العلاقة الصحيحة مع التبرعات
    public function donations()
    {
        return $this->hasMany(Donation::class);
    }

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

    public function addPoints($points)
    {
        $this->loyalty_points += $points;
        $this->updateLoyaltyTier();
        return $this;
    }

    public function addDonation($amount)
    {
        $this->total_donated += $amount;
        $this->save();
        $this->addPoints((int)$amount);
        return $this;
    }
     public function dorations()
    {
        return $this->hasMany(Doration::class,'donor_profile_id');
    }
}