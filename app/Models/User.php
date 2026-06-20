<?php

namespace App\Models;

use App\Traits\HasFcmToken;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Session;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasFcmToken;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_verified',
        'is_active',
        'profile_completed',
        'two_fa_secret',
        'email_verified_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_fa_secret'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'profile_completed' => 'boolean',
    ];

    // Relationships
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function donor()
    {
        return $this->hasOne(DonorProfile::class);
    }

    public function beneficiary()
    {
        return $this->hasOne(BeneficiaryProfile::class);
    }

    public function volunteer()
    {
        return $this->hasOne(VolunterProfile::class);
    }

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }

    public function recurringDonations()
    {
        return $this->hasMany(RecurringDonation::class);
    }

    public function donationCart()
    {
        return $this->hasMany(DonationCart::class);
    }

    public function loyaltyPoints()
    {
        return $this->hasOne(LoyaltyPoints::class);
    }

    public function socialAccounts()
    {
        return $this->hasMany(socialAccount::class);
    }

    public function sessions()
    {
        return $this->hasMany(Session::class);
    }

    public function loginLogs()
    {
        return $this->hasMany(loginLog::class);

    }

    public function auditLogs()
    {
        return $this->hasMany(auditLog::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function notificationPreferences()
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function contactTickets()
    {
        return $this->hasMany(ContactTicket::class);
    }

    // Helper methods
    public function hasVerifiedEmail()
    {
        return !is_null($this->email_verified_at);
    }

    public function markEmailAsVerified()
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
            'is_verified' => true,
        ])->save();
    }

    public function isDonor()
    {
        return $this->role === 'Donor';
    }

    public function isVolunteer()
    {
        return $this->role === 'volunteer';
    }

    public function isBeneficiary()
    {
        return $this->role === 'Beneficiary';
    }
}