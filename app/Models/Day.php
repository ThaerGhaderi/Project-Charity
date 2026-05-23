<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Day extends Model
{
     protected $fillable = ['name'];

    public function volunteerProfiles()
    {
        return $this->belongsToMany(VolunterProfile::class, 'volunteer_days');
    }
}
