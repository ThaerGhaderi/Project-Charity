<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'skills',
        'availability',
        'total_hours',
        'status',
        'region',
        
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}