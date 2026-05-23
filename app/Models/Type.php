<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Type extends Model
{
    public function beneficiaries()
    {
        return $this->belongsToMany(BeneficiaryProfile::class, 'beneficiary_types');
    }
}
