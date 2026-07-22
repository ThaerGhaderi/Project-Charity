<?php
// app/Models/AidApplication.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AidApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'beneficiary_profile_id',
        'user_id',
        'type',
        'description',
        'is_urgent',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'amount_requested',
        'amount_approved',
    ];

    protected $casts = [
        'is_urgent' => 'boolean',
        'reviewed_at' => 'datetime',
        'amount_requested' => 'decimal:2',
        'amount_approved' => 'decimal:2',
    ];

    const TYPES = ['مالية', 'تعليمية', 'صحية', 'نفسية', 'إغاثية', 'إيواء', 'غذاء', 'مياه', 'كسوة', 'دعم نفسي', 'تمكين اقتصادي'];
    
    const STATUSES = ['pending', 'reviewing', 'approved', 'rejected', 'completed', 'cancelled'];

    // Relationships
    public function beneficiary()
    {
        return $this->belongsTo(BeneficiaryProfile::class, 'beneficiary_profile_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUrgent($query)
    {
        return $query->where('is_urgent', true);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Accessors
    public function getStatusTextAttribute()
    {
        return [
            'pending' => 'قيد الانتظار',
            'reviewing' => 'قيد المراجعة',
            'approved' => 'مقبول',
            'rejected' => 'مرفوض',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي'
        ][$this->status] ?? $this->status;
    }

    public function getTypeTextAttribute()
    {
        return $this->type;
    }
}