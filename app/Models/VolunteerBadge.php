<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VolunteerBadge extends Model
{
    protected $fillable = [
        'volunteer_id',
        'name',
        'icon',
        'description',
        'earned_at',
    ];

    protected $casts = [
        'earned_at' => 'datetime',
    ];

    public function volunteer()
    {
        return $this->belongsTo(VolunterProfile::class, 'volunteer_id');
    }
}