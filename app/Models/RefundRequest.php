<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
    protected $fillable = [
        'donation_id',
        'user_id',
        'reason',
        'status',
        'admin_notes',
        'processed_by',
        'processed_at'
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function donation()
    {
        return $this->belongsTo(Donation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function approve($adminId, $notes = null)
    {
        $this->status = 'approved';
        $this->admin_notes = $notes;
        $this->processed_by = $adminId;
        $this->processed_at = now();
        $this->save();
        
        // Update donation status
        $this->donation->status = 'refunded';
        $this->donation->save();
        
        return $this;
    }

    public function reject($adminId, $reason)
    {
        $this->status = 'rejected';
        $this->admin_notes = $reason;
        $this->processed_by = $adminId;
        $this->processed_at = now();
        $this->save();
        
        return $this;
    }
}