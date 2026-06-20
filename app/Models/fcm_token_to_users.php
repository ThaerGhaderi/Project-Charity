<?php

namespace App\Models;

use App\Traits\HasFcmToken;
use Illuminate\Database\Eloquent\Model;

class fcm_token_to_users extends Model
{
    use  HasFcmToken;
    protected $fillable = [
    // ...
    'fcm_token',
    'device_type',
];
}
