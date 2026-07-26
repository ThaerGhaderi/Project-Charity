<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Doration extends Model
{
protected $fillable = [
        'donor_profile_id',
        'name',
        'amount',
        'payment_method',
        'cat',
        'date',
        'status',
        'notes',
    ];

    public function donorProfile()
    {
        return $this->belongsTo(DonorProfile::class);
    }
}
