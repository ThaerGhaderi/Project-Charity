<?php
// app/Models/ContactTicket.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactTicket extends Model
{
    protected $table = 'contact_tickets';
    
    protected $fillable = [
        'user_id',
        'subject',
        'message',
        'status',
        'assigned_to'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}