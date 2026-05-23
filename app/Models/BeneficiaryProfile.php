<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeneficiaryProfile extends Model
{
    protected $fillable = [
    'user_id',
    'Breadwinner',
    'has_income',
    'income_range',
    'photo_Family_notebook',
    'photo_Supporting',
    'is_Anonymous',
    'family_members_count',
    'marital_status',
    ];

    protected $casts = [
        'Breadwinner' => 'boolean',
        'has_income'  => 'boolean',
        'is_ananyomus' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function types()
    {
        return $this->belongsToMany(Type::class,'beneficiary_types');
    }
}

