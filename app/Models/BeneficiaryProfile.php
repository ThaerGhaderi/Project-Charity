<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeneficiaryProfile extends Model
{
      protected $fillable = [
        'user_id',
        'address',
        'region',
        'category',
        'priority_score',
        'birth_date',
        'gender',
        'marital_status',
        'is_anonymized'

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

