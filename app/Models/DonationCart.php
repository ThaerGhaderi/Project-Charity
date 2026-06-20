<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonationCart extends Model
{
     protected $table = 'donation_cart'; 
    protected $fillable = [
        'user_id',
        'campaign_id',
        'amount'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    // Get total amount for user's cart
    public static function getTotalForUser($userId)
    {
        return self::where('user_id', $userId)->sum('amount');
    }

    // Clear user's cart
    public static function clearCart($userId)
    {
        return self::where('user_id', $userId)->delete();
    }
}