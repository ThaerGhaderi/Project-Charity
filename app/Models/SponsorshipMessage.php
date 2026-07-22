<?php
// app/Models/SponsorshipMessage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SponsorshipMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'sponsorship_id',
        'sender_id',
        'receiver_id',
        'message',
        'is_read',
        'read_at',
        'type', // sponsor_to_beneficiary, beneficiary_to_sponsor, system
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // العلاقات
    public function sponsorship()
    {
        return $this->belongsTo(Sponsorship::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    // النطاقات
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('sender_id', $userId)->orWhere('receiver_id', $userId);
    }

    // تحديد ما إذا كانت الرسالة من الكافل
    public function getIsFromSponsorAttribute()
    {
        return $this->sender_id === $this->sponsorship->sponsor_id;
    }

    // تحديد ما إذا كانت الرسالة من المستفيد
    public function getIsFromBeneficiaryAttribute()
    {
        return $this->sender_id === $this->sponsorship->beneficiary_id;
    }
}