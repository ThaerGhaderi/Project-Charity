<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftDonation extends Model
{
    protected $table = 'gift_donations';
    
    protected $fillable = [
        'donation_id',
        'recipient_name',
        'recipient_email',
        'message',
        'certificate_url'
    ];

    public function donation()
    {
        return $this->belongsTo(Donation::class);
    }

    public function generateCertificate()
    {
        // Generate PDF certificate
        // This would integrate with PDF generation library
        $certificateUrl = "certificates/gift_{$this->id}.pdf";
        
        $this->certificate_url = $certificateUrl;
        $this->save();
        
        return $certificateUrl;
    }

    public function sendToRecipient()
    {
        // Send email with certificate
        // Mail::to($this->recipient_email)->send(new GiftDonationMail($this));
    }
}