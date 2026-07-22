<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessageStatus extends Model
{
      protected $table = 'chat_message_status';
    protected $fillable = [
        'message_id', 'user_id', 'is_read', 'read_at'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(ChatMessage::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}