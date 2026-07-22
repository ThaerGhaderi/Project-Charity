<?php
// app/Models/VolunteerEvaluation.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VolunteerEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'volunteer_id',
        'task_id',
        'supervisor_id',
        'rating',
        'feedback',
        'supervisor_response',
        'evaluated_at',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
        'evaluated_at' => 'datetime',
    ];

    public function volunteer()
    {
        return $this->belongsTo(VolunterProfile::class, 'volunteer_id');
    }

    public function task()
    {
        return $this->belongsTo(VolunteerTask::class, 'task_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}