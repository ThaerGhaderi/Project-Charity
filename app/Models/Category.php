<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Category extends Model
{
    protected $fillable = ['name'];
    public function volunteers()
    {
        return $this->belongsToMany(VolunterProfile::class, 'volunteer_categories');
    }
}
