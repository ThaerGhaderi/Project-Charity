<?php
// app/Providers/BroadcastServiceProvider.php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Broadcast::routes();

        // ✅ قناة للمحادثة
        Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
            return \App\Models\ChatParticipant::where('conversation_id', $conversationId)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->exists();
        });

        // ✅ قناة للمستخدم
        Broadcast::channel('user.{id}', function ($user, $id) {
            return (int) $user->id === (int) $id;
        });
    }
}