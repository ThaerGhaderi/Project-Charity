<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonorProfile extends Model
{
   protected $fillable = [
        'user_id',
        'bio',
        
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
