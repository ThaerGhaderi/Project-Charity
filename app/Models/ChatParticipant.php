<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatParticipant extends Model
{
    protected $fillable = [
        'conversation_id', 'user_id', 'last_read_at', 'joined_at', 'role', 'is_muted', 'is_active'
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'joined_at' => 'datetime',
        'is_muted' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}