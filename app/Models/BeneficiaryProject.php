<?php
// app/Models/BeneficiaryProject.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class BeneficiaryProject extends Model
{
    protected $fillable = ['beneficiary_id', 'campaign_id', 'amount_received', 'aid_type', 'received_at'];
    
    public function beneficiary()
    {
        return $this->belongsTo(BeneficiaryProfile::class, 'beneficiary_id');
    }
    
    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}