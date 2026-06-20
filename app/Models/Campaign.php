<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'title',
        'description',
        'goal_amount',
        'collected_amount',
        'category',
        'status',
        'is_emergency',
        'start_date',
        'end_date',
        'short_url',
        'qr_code_url',
        'created_by'
    ];

    protected $casts = [
        'goal_amount' => 'decimal:2',
        'collected_amount' => 'decimal:2',
        'is_emergency' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }

    public function media()
    {
        return $this->hasMany(CampaignMedia::class);
    }

    public function updates()
    {
        return $this->hasMany(CampaignUpdate::class);
    }

    public function beneficiaryProjects()
    {
        return $this->hasMany(BeneficiaryProject::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeEmergency($query)
    {
        return $query->where('is_emergency', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    // Helper methods
    public function getProgressPercentageAttribute()
    {
        if ($this->goal_amount == 0) return 0;
        return min(100, round(($this->collected_amount / $this->goal_amount) * 100));
    }

    public function getRemainingAmountAttribute()
    {
        return max(0, $this->goal_amount - $this->collected_amount);
    }

    public function getDonorsCountAttribute()
    {
        return $this->donations()->where('status', 'completed')->distinct('user_id')->count('user_id');
    }

    public function updateCollectedAmount()
    {
        $this->collected_amount = $this->donations()
            ->where('status', 'completed')
            ->sum('amount');
        $this->save();
        
        // Auto close if goal reached
        if ($this->collected_amount >= $this->goal_amount && $this->status === 'active') {
            $this->status = 'completed';
            $this->save();
        }
        
        return $this;
    }
}