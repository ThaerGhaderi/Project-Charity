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
        'data',
        'firebase_sent',
        'firebase_sent_at',
        'firebase_error',
        'priority'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'data' => 'array',
        // ✅ أضفنا الكاستات الجديدة
        'firebase_sent' => 'boolean',
        'firebase_sent_at' => 'datetime',
    ];

    // ✅ الأعمدة الافتراضية
    protected $attributes = [
        'is_read' => false,
        'firebase_sent' => false,
        'priority' => 'normal'
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

    public static function send($userId, $title, $body, $type = 'general', $data = [], $sendPush = true)
    {

        $notification = self::create([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'data' => $data,
            'is_read' => false,
            'firebase_sent' => false,
            'priority' => 'normal'
        ]);


        if ($sendPush) {
            try {
                $user = User::find($userId);
                if ($user && $user->hasFcmToken()) {
                    $sent = $notification->sendPushNotification();

                    if ($sent) {
                        $notification->firebase_sent = true;
                        $notification->firebase_sent_at = now();
                        $notification->save();
                    }
                }
            } catch (\Exception $e) {
                $notification->firebase_error = $e->getMessage();
                $notification->save();
                Log::error('Firebase send failed: ' . $e->getMessage());
            }
        }

        return $notification;
    }

    public function sendPushNotification()
    {
        $user = $this->user;

        if (!$user || !$user->hasFcmToken()) {
            return false;
        }

        try {
            $messaging = app(\Kreait\Firebase\Messaging::class);

            // ✅ إرسال إشعار من نوع Data-Only (الأفضل لتطبيقات فلاتر)
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withAndroidConfig(
                    \Kreait\Firebase\Messaging\AndroidConfig::fromArray([
                        'priority' => 'high',
                    ])
                )
                ->withApnsConfig(
                    \Kreait\Firebase\Messaging\ApnsConfig::fromArray([
                        'payload' => [
                            'aps' => [
                                'content-available' => true, // يوقظ التطبيق في الخلفية
                            ],
                        ],
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                    ])
                )
                ->withData([
                    'title' => $this->title,         // 👈 العنوان داخل الداتا
                    'body' => $this->body,           // 👈 النص داخل الداتا
                    'type' => $this->type,
                    'notification_id' => (string) $this->id,
                    'click_action' => $this->getClickAction(),
                    'payload' => json_encode($this->data ?? []),
                ]);

            $messaging->send($message);

            Log::info("Firebase Data-Only push sent to user {$user->id}", [
                'notification_id' => $this->id
            ]);

            return true;

        } catch (\Exception $e) {
            // ✅ تسجيل الخطأ بشكل صريح لكي نراه في قاعدة البيانات
            $this->firebase_error = $e->getMessage();
            $this->save();

            Log::error('Firebase push failed: ' . $e->getMessage(), [
                'notification_id' => $this->id,
                'user_id' => $user->id
            ]);
            return false;
        }
    }

    // ================================================================
    // ✅ دالة sendPushOnly (نفس السلوك الجديد: تخزين + Firebase)
    // ================================================================
    public static function sendPushOnly($userId, $title, $body, $type = 'general', $data = [])
    {
        // ✅ نستخدم نفس دالة send (تخزين + Firebase)
        return self::send($userId, $title, $body, $type, $data, true);
    }

    // ================================================================
    // ✅ دالة للتحقق من إمكانية إعادة المحاولة
    // ================================================================
    public function canRetryFirebase(): bool
    {
        return !$this->firebase_sent && $this->user && $this->user->hasFcmToken();
    }

    // ================================================================
    // ✅ دالة لإعادة محاولة الإشعارات الفاشلة
    // ================================================================
    public static function retryFailedNotifications(int $limit = 100): int
    {
        $notifications = self::where('firebase_sent', false)
            ->whereNotNull('firebase_error')
            ->where('created_at', '>=', now()->subDays(7))
            ->limit($limit)
            ->get();

        $count = 0;

        foreach ($notifications as $notification) {
            try {
                $user = User::find($notification->user_id);

                if ($user && $user->hasFcmToken()) {
                    $sent = $notification->sendPushNotification();

                    if ($sent) {
                        $notification->firebase_sent = true;
                        $notification->firebase_sent_at = now();
                        $notification->firebase_error = null;
                        $notification->save();
                        $count++;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Retry failed for notification ' . $notification->id, [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $count;
    }

    // ================================================================
    // ✅ دوال مساعدة للإرسال الجماعي
    // ================================================================
    public static function sendBatchPush($userIds, $title, $body, $type = 'general', $data = [])
    {
        $results = [];

        foreach ($userIds as $userId) {
            $results[$userId] = self::sendPushOnly($userId, $title, $body, $type, $data);
        }

        return $results;
    }

    public static function sendToRole($role, $title, $body, $type = 'general', $data = [])
    {
        $users = User::where('role', $role)->get();
        $results = [];

        foreach ($users as $user) {
            $results[$user->id] = self::sendPushOnly($user->id, $title, $body, $type, $data);
        }

        return $results;
    }

    public static function sendToAdmins($title, $body, $type = 'admin', $data = [])
    {
        return self::sendToRole('admin', $title, $body, $type, $data);
    }

    // ================================================================
    // ✅ دوال مساعدة
    // ================================================================
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
            'aid_application' => 'AID_DETAILS',
            'aid_application_status' => 'AID_STATUS',
            'visit' => 'VISIT_DETAILS',
            'sponsorship' => 'SPONSORSHIP_DETAILS',
            'certificate' => 'CERTIFICATE_DETAILS',
            default => 'MAIN_ACTIVITY',
        };
    }
}
