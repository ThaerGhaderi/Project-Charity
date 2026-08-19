<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatParticipant;
use App\Models\ChatMessage;
use App\Models\ChatMessageStatus;
use App\Models\Notification;
use App\Models\User;
use App\Events\NewChatMessage;
use App\Events\MessageReadStatus;
use App\Events\UserTyping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * عرض جميع محادثات المستخدم
     */
    public function conversations(Request $request)
    {
        $user = $request->user();

        $conversations = ChatConversation::byUser($user->id)
            ->with(['participants.user', 'lastMessage'])
            ->orderBy('last_message_at', 'desc')
            ->get();

        return response()->json([
            'code' => '200',
            'success' => true,
            'data' => $conversations
        ], 200);
    }
    /**
     * إنشاء محادثة جديدة
     */
  public function createConversation(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'type' => 'required|in:private,group,public',
            'name' => 'required_if:type,group|nullable|string|max:255',
            'participants' => 'required|array|min:1',
            'participants.*' => 'exists:users,id',
        ]);

        try {
            DB::beginTransaction();

            $conversation = ChatConversation::create([
                'name' => $validated['name'] ?? null,
                'type' => $validated['type'],
                'created_by' => $user->id,
                'is_active' => true,
            ]);

            // إضافة المشاركين
            $participants = array_merge([$user->id], $validated['participants']);

            foreach ($participants as $participantId) {
                ChatParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $participantId,
                    'role' => $participantId === $user->id ? 'admin' : 'member',
                    'joined_at' => now(),
                    'is_active' => true,
                ]);
            }

            DB::commit();

            return response()->json([
                'code' => '201',
                'success' => true,
                'message' => 'تم إنشاء المحادثة',
                'data' => $conversation->load(['participants.user'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
        }
public function messages($conversationId, Request $request)
{
    $user = $request->user();

    $conversation = ChatConversation::byUser($user->id)
        ->where('id', $conversationId)
        ->firstOrFail();

    $messages = ChatMessage::where('conversation_id', $conversationId)
        ->with(['sender'])
        ->notDeleted()
        ->orderBy('created_at', 'asc')
        ->get();  // ← بدلاً من paginate(50)

    // تحديث حالة القراءة
    $this->markMessagesAsRead($conversationId, $user->id);

    return response()->json([
        'code' => '200',
        'success' => true,
        'data' => $messages  // ← مصفوفة بسيطة بدون pagination
    ], 200);
}

    /**
     * إرسال رسالة (مع البث المباشر)
     */
    public function sendMessage(Request $request, $conversationId)
    {
        $user = $request->user();

        $conversation = ChatConversation::byUser($user->id)
            ->where('id', $conversationId)
            ->firstOrFail();

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        try {
            DB::beginTransaction();

            $message = ChatMessage::create([
                'conversation_id' => $conversationId,
                'sender_id' => $user->id,
                'message' => $validated['message'],
                'type' => 'text',
                'is_read' => false,
            ]);

            $conversation->update(['last_message_at' => now()]);

            // ✅ بث الرسالة
            broadcast(new NewChatMessage($message))->toOthers();

            ChatMessageStatus::create([
                'message_id' => $message->id,
                'user_id' => $user->id,
                'is_read' => true,
                'read_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'code' => '201',
                'success' => true,
                'message' => 'تم إرسال الرسالة',
                'data' => $message->load(['sender'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code' => '500',
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * وضع علامة مقروء
     */
    public function markAsRead($conversationId, Request $request)
    {
        $user = $request->user();

        $this->markMessagesAsRead($conversationId, $user->id);

        // ✅ بث حالة القراءة
        broadcast(new MessageReadStatus($conversationId, $user->id))->toOthers();

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم تحديث حالة القراءة'
        ], 200);
    }

    /**
     * إشارة كتابة
     */
    public function typing(Request $request, $conversationId)
    {
        $user = $request->user();

        $isTyping = $request->boolean('is_typing', true);

        broadcast(new UserTyping(
            $conversationId,
            $user->id,
            $user->name,
            $isTyping
        ))->toOthers();

        return response()->json([
            'code' => '200',
            'success' => true,
        ], 200);
    }

    /**
     * مغادرة المحادثة
     */
    public function leaveConversation($conversationId, Request $request)
    {
        $user = $request->user();

        $participant = ChatParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $participant->update(['is_active' => false]);

        return response()->json([
            'code' => '200',
            'success' => true,
            'message' => 'تم مغادرة المحادثة'
        ], 200);
    }

    // ==================== PRIVATE METHODS ====================

    private function markMessagesAsRead($conversationId, $userId)
    {
        $messages = ChatMessage::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $userId)
            ->get();

        foreach ($messages as $message) {
            ChatMessageStatus::updateOrCreate(
                ['message_id' => $message->id, 'user_id' => $userId],
                ['is_read' => true, 'read_at' => now()]
            );
        }
    }
}
