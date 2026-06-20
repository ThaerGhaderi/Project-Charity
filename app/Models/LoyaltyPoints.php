<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyPoints extends Model
{
    protected $fillable = [
        'user_id',
        'points',
        'badge'
    ];

    protected $casts = [
        'points' => 'integer',
    ];

    // Badge thresholds
    const BADGE_BRONZE = 300;
    const BADGE_SILVER = 1000;
    const BADGE_GOLD = 3000;
    const BADGE_PLATINUM = 10000;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getBadgeAttribute($value)
    {
        if ($value) return $value;
        
        if ($this->points >= self::BADGE_PLATINUM) return 'platinum';
        if ($this->points >= self::BADGE_GOLD) return 'gold';
        if ($this->points >= self::BADGE_SILVER) return 'silver';
        if ($this->points >= self::BADGE_BRONZE) return 'bronze';
        
        return null;
    }

    public function addPoints($points, $source = null)
    {
        $this->points += $points;
        $this->save();
        
        // Create points history
        LoyaltyPoints::create([
            'user_id' => $this->user_id,
            'points' => $points,
            'type' => 'earned',
            'source' => $source
        ]);
        
        return $this;
    }

    public function redeemPoints($points)
    {
        if ($this->points < $points) {
            throw new \Exception('Insufficient points');
        }
        
        $this->points -= $points;
        $this->save();
        
        LoyaltyPoints::create([
            'user_id' => $this->user_id,
            'points' => $points,
            'type' => 'redeemed'
        ]);
        
        return $this;
    }
}