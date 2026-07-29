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


    protected $appends = ['achieved_amount', 'donors_count', 'progress_percentage'];
  protected static function booted()
    {
        static::creating(function ($campaign) {
            if (auth()->check()) {
                $campaign->created_by = auth()->id();
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }


    public function donations()
    {
        return $this->hasMany(Donation::class, 'campaign_id');
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


    public function donorProfiles()
    {
        return $this->belongsToMany(DonorProfile::class, 'donations', 'campaign_id', 'donor_id')->distinct();
    }


    public function volunteerTasks()
    {
        return $this->hasMany(VolunteerTask::class);
    }


    public function scopeActive($query)
    {
        return $query->where('status', 'نشطة');
    }


    public function scopeEmergency($query)
    {
        return $query->where('is_emergency', true);
    }


    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }


    public function getProgressPercentageAttribute()
    {
        if (!$this->goal_amount || $this->goal_amount == 0) {
            return 0;
        }

        return round(($this->achieved_amount / $this->goal_amount) * 100, 2);
    }


    public function getRemainingAmountAttribute()
    {
        return max(0, $this->goal_amount - $this->collected_amount);
    }


    public function getAchievedAmountAttribute()
    {
        return $this->attributes['achieved_amount_sum']
            ?? $this->donations()->where('status', 'completed')->sum('amount');
    }


    public function getDonorsCountAttribute()
    {
        return $this->attributes['donors_count_calc']
            ?? $this->donations()->where('status', 'completed')->distinct('donor_id')->count('donor_id');
    }


    public function updateCollectedAmount()
    {
        $this->collected_amount = $this->donations()
            ->where('status', 'completed')
            ->sum('amount');
        $this->save();


        if ($this->collected_amount >= $this->goal_amount && $this->status === 'نشطة') {
            $this->status = 'مكتملة';
            $this->save();
        }

        return $this;
    }
}
