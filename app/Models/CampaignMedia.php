<?php
// app/Models/CampaignMedia.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CampaignMedia extends Model
{
    protected $fillable = ['campaign_id', 'media_url', 'media_type'];
    
    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}