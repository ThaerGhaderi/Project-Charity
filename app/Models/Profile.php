<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $table = 'profiles';
    
    protected $fillable = [
        'user_id',
        'city_id',
        'photo_id',
        'phone',
        'birth_date',
        'gender',
        'personal_photo',
        'bio'
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}