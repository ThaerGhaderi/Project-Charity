<?php

namespace App\Models;

use App\Enums\Day;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $fillable = [
    'user_id',
    'city_id',
    'photo_id',
    'phone',
    'birth_date',
    'gender',
    'Personal_photo',
];
    protected function casts(): array
    {
        return [
            'availability' => 'array',
        ];
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
