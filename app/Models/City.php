<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    public function profiles() {
    return $this->hasMany(Profile::class);
}
}
