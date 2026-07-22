<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = [
        'conversation_id', 'sender_id', 'reply_to_id', 'message', 'type',
        'file_path', 'file_name', 'file_size', 'is_read', 'read_at',
        'delivered_at', 'is_deleted', 'deleted_at'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_deleted' => 'boolean',
        'read_at' => 'datetime',
        'delivered_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(ChatMessage::class, 'reply_to_id');
    }

    public function status()
    {
        return $this->hasMany(ChatMessageStatus::class);
    }

    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }
}