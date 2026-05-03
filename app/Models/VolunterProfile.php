<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VolunterProfile extends Model
{
      protected $fillable = [
        'user_id',
        'skills',
        'availability',
        'total_hours',
         'region',  
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
