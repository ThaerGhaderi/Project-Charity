<?php

namespace App\Http\Controllers;  

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Register FCM Token for push notifications
     * POST /api/notifications/register-token
     */
    public function registerToken(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }

        $request->validate([
            'fcm_token' => 'required|string',
            'device_type' => 'nullable|in:ios,android,web'
        ]);

        $user->updateFcmToken($request->fcm_token, $request->device_type);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الجهاز بنجاح للإشعارات'
        ], 200);
    }

    /**
     * Remove FCM Token (logout)
     * POST /api/notifications/remove-token
     */
    public function removeToken(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }
        
        $user->removeFcmToken();

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء تسجيل الجهاز'
        ], 200);
    }

    /**
     * Test push notification
     * POST /api/notifications/test-push
     */
    public function testPush(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }

        if (!$user->hasFcmToken()) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم تسجيل أي جهاز للإشعارات. يرجى تسجيل جهازك أولاً عبر register-token'
            ], 400);
        }

        // استخدام send (تخزين في DB + إرسال Firebase)
        Notification::send(
            $user->id,
            '🔔 اختبار إشعار',
            'تم توصيل إشعارات Firebase بنجاح!',
            'test',
            ['test_data' => 'Hello World']
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال إشعار تجريبي'
        ], 200);
    }

    /**
     * Get user's notifications (Local)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }
        
        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        $unreadCount = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]
        ], 200);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }
        
        $count = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => ['unread_count' => $count]
        ], 200);
    }

    public function markAsRead($id, Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }
        
        $notification = Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'تم وضع علامة مقروء'
        ], 200);
    }

    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }
        
        Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'تم وضع علامة مقروء على جميع الإشعارات'
        ], 200);
    }

    public function destroy($id, Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }
        
        $notification = Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الإشعار'
        ], 200);
    }

    public function deleteAll(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }
        
        Notification::where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف جميع الإشعارات'
        ], 200);
    }

    public function preferences(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }
        
        $preferences = $user->notificationPreferences;
        
        if (!$preferences) {
            $preferences = NotificationPreference::create([
                'user_id' => $user->id,
                'push_enabled' => true,
                'email_enabled' => true,
                'sms_enabled' => false,
                'whatsapp_enabled' => false
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $preferences
        ], 200);
    }

    public function updatePreferences(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }
        
        $validated = $request->validate([
            'push_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean'
        ]);

        $preferences = $user->notificationPreferences;
        
        if (!$preferences) {
            $preferences = NotificationPreference::create(array_merge(
                ['user_id' => $user->id],
                $validated
            ));
        } else {
            $preferences->update($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث تفضيلات الإشعارات',
            'data' => $preferences
        ], 200);
    }
}