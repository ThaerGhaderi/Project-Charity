<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'type',
        'channel',
        'is_read',
        'data'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function markAsRead()
    {
        $this->is_read = true;
        $this->save();
        
        return $this;
    }

    /**
     * ✅ الدالة الموجودة (لا تغيرها) - ترسل Local + Firebase
     */
    public static function send($userId, $title, $body, $type = 'general', $data = [], $sendPush = true)
    {
        // 1. حفظ في قاعدة البيانات (Local) - لا نغير هذا
        $notification = self::create([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'data' => $data
        ]);

        // 2. إرسال Firebase Push Notification (إضافة جديدة)
        if ($sendPush) {
            $notification->sendPushNotification();
        }

        return $notification;
    }

    /**
     * ✅ دالة جديدة: إرسال Firebase فقط (تُستدعى من الدالة أعلاه)
     */
    public function sendPushNotification()
    {
        $user = $this->user;
        
        if (!$user || !$user->hasFcmToken()) {
            return false;
        }

        try {
            $messaging = app(\Kreait\Firebase\Messaging::class);
            
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(FirebaseNotification::create(
                    $this->title,
                    $this->body
                ))
                ->withData([
                    'notification_id' => (string) $this->id,
                    'type' => $this->type,
                    'click_action' => $this->getClickAction(),
                    ...$this->data
                ]);
            
            $messaging->send($message);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Firebase push failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ دالة جديدة: إرسال إشعار Firebase فقط (بدون حفظ في DB)
     * تُستخدم عندما تريد Firebase فقط
     */
    public static function sendPushOnly($userId, $title, $body, $type = 'general', $data = [])
    {
        $user = User::find($userId);
        
        if (!$user || !$user->hasFcmToken()) {
            return false;
        }

        try {
            $messaging = app(\Kreait\Firebase\Messaging::class);
            
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(FirebaseNotification::create($title, $body))
                ->withData(array_merge([
                    'type' => $type,
                    'click_action' => self::getClickActionStatic($type),
                ], $data));
            
            $messaging->send($message);
            
            Log::info("Firebase push sent to user {$userId}");
            return true;
            
        } catch (\Exception $e) {
            Log::error('Firebase push failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * دالة مساعدة للحصول على click action
     */
    private function getClickAction()
    {
        return self::getClickActionStatic($this->type);
    }

    private static function getClickActionStatic($type)
    {
        return match ($type) {
            'donation' => 'DONATION_DETAILS',
            'campaign' => 'CAMPAIGN_DETAILS',
            'badge' => 'LOYALTY_POINTS',
            'refund' => 'REFUND_STATUS',
            default => 'MAIN_ACTIVITY',
        };
    }

    /**
     * ✅ إرسال إشعار لمجموعة من المستخدمين (Firebase فقط)
     */
    public static function sendBatchPush($userIds, $title, $body, $type = 'general', $data = [])
    {
        $results = [];
        
        foreach ($userIds as $userId) {
            $results[$userId] = self::sendPushOnly($userId, $title, $body, $type, $data);
        }
        
        return $results;
    }

    /**
     * ✅ إرسال إشعار لجميع المتبرعين (Firebase فقط)
     */
    public static function sendToAllDonors($title, $body, $type = 'general', $data = [])
    {
        $donors = User::where('role', 'Donor')
            ->whereNotNull('fcm_token')
            ->get();
        
        $results = [];
        
        foreach ($donors as $donor) {
            $results[$donor->id] = self::sendPushOnly($donor->id, $title, $body, $type, $data);
        }
        
        return $results;
    }

    /**
     * ✅ إرسال إشعار لدور محدد (Firebase فقط)
     */
    public static function sendToRole($role, $title, $body, $type = 'general', $data = [])
    {
        $users = User::where('role', $role)
            ->whereNotNull('fcm_token')
            ->get();
        
        $results = [];
        
        foreach ($users as $user) {
            $results[$user->id] = self::sendPushOnly($user->id, $title, $body, $type, $data);
        }
        
        return $results;
    }
}