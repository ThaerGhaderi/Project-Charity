<?php
// app/Models/CampaignUpdate.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CampaignUpdate extends Model
{
    protected $fillable = ['campaign_id', 'title', 'content', 'images', 'created_by'];
    
    protected $casts = ['images' => 'array'];
    
    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
    
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}