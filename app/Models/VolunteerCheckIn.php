<?php
// app/Models/VolunteerCheckIn.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VolunteerCheckIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'volunteer_id',
        'check_in_time',
        'check_out_time',
        'location_verified',
        'latitude',
        'longitude',
        'status',
        'notes',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'location_verified' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function task()
    {
        return $this->belongsTo(VolunteerTask::class, 'task_id');
    }

    public function volunteer()
    {
        return $this->belongsTo(VolunterProfile::class, 'volunteer_id');
    }

    public function getDurationAttribute()
    {
        if ($this->check_in_time && $this->check_out_time) {
            return $this->check_in_time->diffInSeconds($this->check_out_time);
        }
        return null;
    }

    public function getFormattedDurationAttribute()
    {
        $seconds = $this->duration;
        if (!$seconds) return '00:00:00';
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
}