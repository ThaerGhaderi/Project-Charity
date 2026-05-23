<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $fillable = ['volunter_profile_id','pic_certificate','name',];
    public function volunteerProfile()
    {
        return $this->belongsTo(VolunterProfile::class, 'volunter_profile_id');
    }
}
