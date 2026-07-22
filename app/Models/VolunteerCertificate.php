<?php
// app/Models/VolunteerCertificate.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VolunteerCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'volunteer_id',
        'title',
        'description',
        'issued_at',
        'certificate_number',
        'file_path',
        'level',
        'hours_required',
        'hours_completed',
        'is_active',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'hours_required' => 'integer',
        'hours_completed' => 'integer',
        'is_active' => 'boolean',
    ];

    const LEVELS = [
        'برونزية' => 'برونزية',
        'فضية' => 'فضية',
        'ذهبية' => 'ذهبية',
        'ماسية' => 'ماسية',
    ];

    public function volunteer()
    {
        return $this->belongsTo(VolunterProfile::class, 'volunteer_id');
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->hours_required == 0) return 0;
        return round(($this->hours_completed / $this->hours_required) * 100);
    }

    public function getRemainingHoursAttribute()
    {
        return max(0, $this->hours_required - $this->hours_completed);
    }
}