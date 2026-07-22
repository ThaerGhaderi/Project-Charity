<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatConversation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'type', 'created_by', 'avatar', 'description', 'is_active', 'last_message_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants()
    {
        return $this->hasMany(ChatParticipant::class, 'conversation_id');
    }

    // ✅ ✅ ✅ أضف المفتاح الخارجي هنا
    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }
    public function lastMessage()
{
    return $this->hasOne(ChatMessage::class, 'conversation_id')->latest();
}

    public function scopeByUser($query, $userId)
    {
        return $query->whereHas('participants', function($q) use ($userId) {
            $q->where('user_id', $userId)->where('is_active', true);
        });
    }
}