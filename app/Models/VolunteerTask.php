<?php
// app/Models/VolunteerTask.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VolunteerTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'volunteer_id',
        'supervisor_id',
        'beneficiary_id',
        'aid_application_id',
        'campaign_id',
        'visit_id',
        'title',
        'description',
        'location',
        'start_time',
        'end_time',
        'expected_end_time',
        'status',
        'progress_percentage',
        'points_earned',
        'supervisor_notes',
        'completed_at',
        'cancelled_at',
        'priority',
        'due_date',
        'type_id',


        'awaiting_approval',
        'requested_at',
        'requested_latitude',
        'requested_longitude',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'expected_end_time' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',

        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',


        'progress_percentage' => 'integer',
        'points_earned' => 'integer',
    ];

    // ✅ حالات المهمة
    const STATUSES = [
        'جديدة' => 'جديدة',
        'قيد التنفيذ' => 'قيد التنفيذ',
        'مكتملة' => 'مكتملة',
        'ملغية' => 'ملغية',
        'معلقة' => 'معلقة',
    ];

    // ==================== العلاقات ====================

    public function volunteer()
    {
        return $this->belongsTo(VolunterProfile::class, 'volunteer_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

        public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function checkIns()
    {
        return $this->hasMany(VolunteerCheckIn::class, 'task_id'); // ✅ استخدم task_id
    }

    // ✅ العلاقات مع المستفيد فقط
    public function beneficiary()
    {
        return $this->belongsTo(User::class, 'beneficiary_id');
    }

    public function aidApplication()
    {
        return $this->belongsTo(AidApplication::class);
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }


    // ✅ أضف هذه العلاقة
    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    // ✅ أضف هذه الخصائص المحسوبة

    public function getSourceNameAttribute()
    {
        if ($this->campaign) {
            return $this->campaign->title;
        } elseif ($this->visit) {
            return $this->visit->location;
        } elseif ($this->aidApplication) {
            return $this->aidApplication->type;
        }
        return null;
    }


    // ==================== النطاقات ====================

    public function scopeInProgress($query)
    {
        return $query->where('status', 'قيد التنفيذ');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'مكتملة');
    }

    public function scopeNew($query)
    {
        return $query->where('status', 'جديدة');
    }

    public function scopeByVolunteer($query, $volunteerId)
    {
        return $query->where('volunteer_id', $volunteerId);
    }

    // ==================== الخصائص المحسوبة ====================

    public function getStatusTextAttribute()
    {
        return $this->status;
    }

    public function getElapsedTimeAttribute()
    {
        if ($this->start_time && $this->status === 'قيد التنفيذ') {
            return $this->start_time->diffInSeconds(now());
        }
        return null;
    }

    public function getFormattedElapsedTimeAttribute()
    {
        $seconds = $this->elapsed_time;
        if (!$seconds) return '00:00:00';

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    public function getIsInProgressAttribute()
    {
        return $this->status === 'قيد التنفيذ';
    }

    public function getIsCompletedAttribute()
    {
        return $this->status === 'مكتملة';
    }

    public function getIsNewAttribute()
    {
        return $this->status === 'جديدة';
    }

    public function getSourceTypeAttribute()
    {
        if ($this->campaign_id) {
            return 'حملة';
        } elseif ($this->visit_id) {
            return 'زيارة ميدانية';
        } elseif ($this->aid_application_id) {
            return 'طلب مساعدة';
        } elseif ($this->beneficiary_id) {
            return 'مستفيد مباشر';
        }
        return 'مهمة عامة';
    }


    public function getBeneficiaryNameAttribute()
    {
        if ($this->beneficiary) {
            return $this->beneficiary->name;
        } elseif ($this->aidApplication && $this->aidApplication->user) {
            return $this->aidApplication->user->name;
        } elseif ($this->visit && $this->visit->beneficiary) {
            return $this->visit->beneficiary->name;
        }
        return null;
    }
    public function evaluation()
    {
        return $this->hasOne(VolunteerEvaluation::class, 'task_id');
    }
    public function type()
    {
        return $this->belongsTo(Type::class);
    }
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
