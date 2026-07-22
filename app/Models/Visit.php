<?php
// app/Models/Visit.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'beneficiary_id',
        'social_worker_id',
        'visit_type',
        'location',
        'address',
        'visit_date',
        'visit_time',
        'status',
        'notes',
        'instructions',
        'cancelled_reason',
        'cancelled_at',
        'confirmed_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'visit_time' => 'datetime',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

   
    const VISIT_TYPES = [
        'زيارة باحث اجتماعي' => 'زيارة باحث اجتماعي',
        'تحديث بيانات الأسرة' => 'تحديث بيانات الأسرة',
        'زيارة طبية' => 'زيارة طبية',
        'متابعة' => 'متابعة',
        'زيارة طارئة' => 'زيارة طارئة',
        'تقييم الحالة' => 'تقييم الحالة',
    ];

   
    const STATUSES = [
        'قيد الانتظار' => 'قيد الانتظار',
        'مؤكدة' => 'مؤكدة',
        'مكتملة' => 'مكتملة',
        'ملغية' => 'ملغية',
        'معاد جدولتها' => 'معاد جدولتها',
    ];

    
    public function beneficiary()
    {
        return $this->belongsTo(User::class, 'beneficiary_id');
    }

    public function socialWorker()
    {
        return $this->belongsTo(User::class, 'social_worker_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

  
    public function scopePending($query)
    {
        return $query->where('status', 'قيد الانتظار');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'مؤكدة');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'مكتملة');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'ملغية');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('visit_date', '>=', now()->startOfDay())
            ->whereIn('status', ['قيد الانتظار', 'مؤكدة']);
    }

    public function scopeByBeneficiary($query, $userId)
    {
        return $query->where('beneficiary_id', $userId);
    }

    
    public function getStatusTextAttribute()
    {
        return $this->status;
    }

    public function getTypeTextAttribute()
    {
        return $this->visit_type;
    }

    public function getIsUpcomingAttribute()
    {
        return in_array($this->status, ['قيد الانتظار', 'مؤكدة']) && 
               $this->visit_date >= now()->startOfDay();
    }

    public function getFormattedDateAttribute()
    {
        return $this->visit_date ? $this->visit_date->format('d/m/Y') : null;
    }

    public function getFormattedTimeAttribute()
    {
        return $this->visit_time ? $this->visit_time->format('H:i') : null;
    }

    public function getIsPendingAttribute()
    {
        return $this->status === 'قيد الانتظار';
    }

    public function getIsConfirmedAttribute()
    {
        return $this->status === 'مؤكدة';
    }

    public function getIsCompletedAttribute()
    {
        return $this->status === 'مكتملة';
    }

    public function getIsCancelledAttribute()
    {
        return $this->status === 'ملغية';
    }
     public function volunteerTask()
    {
        return $this->hasOne(VolunteerTask::class, 'visit_id');
    }
}