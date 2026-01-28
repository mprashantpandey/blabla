<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Conversation;
use App\Services\ConversationService;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    protected ConversationService $conversationService;
    protected MessageService $messageService;

    public function __construct(
        ConversationService $conversationService,
        MessageService $messageService
    ) {
        $this->conversationService = $conversationService;
        $this->messageService = $messageService;
    }

    /**
     * Get conversation for a booking.
     */
    public function getConversation(int $bookingId): JsonResponse
    {
        $user = Auth::user();
        $booking = Booking::with(['ride', 'rider', 'driverProfile.user'])->findOrFail($bookingId);

        // Check authorization
        $this->conversationService->assertUserCanChat($user, $booking);

        // Get or create conversation
        $conversation = $this->conversationService->getOrCreateConversation($booking);

        // Load messages with sender and read receipts
        $messages = $conversation->messages()
            ->with(['sender:id,name,phone', 'reads'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        // Get unread count
        $unreadCount = $this->messageService->getUnreadCount($conversation, $user);

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => [
                    'id' => $conversation->id,
                    'booking_id' => $conversation->booking_id,
                    'status' => $conversation->status,
                    'last_message_at' => $conversation->last_message_at,
                ],
                'messages' => $messages->map(function ($message) use ($user) {
                    return [
                        'id' => $message->id,
                        'sender' => $message->sender ? [
                            'id' => $message->sender->id,
                            'name' => $message->sender->name,
                        ] : null,
                        'message_type' => $message->message_type,
                        'body' => $message->body,
                        'is_system' => $message->isSystem(),
                        'created_at' => $message->created_at,
                        'is_read' => $message->isReadBy($user->id),
                    ];
                }),
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * Send a message.
     */
    public function sendMessage(Request $request, int $bookingId): JsonResponse
    {
        $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $user = Auth::user();
        $booking = Booking::findOrFail($bookingId);

        // Check authorization
        $this->conversationService->assertUserCanChat($user, $booking);

        // Get or create conversation
        $conversation = $this->conversationService->getOrCreateConversation($booking);

        // Send message
        $message = $this->messageService->sendMessage($conversation, $user, $request->body);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => [
                    'id' => $message->id,
                    'sender' => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ],
                    'message_type' => $message->message_type,
                    'body' => $message->body,
                    'is_system' => $message->isSystem(),
                    'created_at' => $message->created_at,
                    'is_read' => true,
                ],
            ],
        ], 201);
    }

    /**
     * Mark messages as read.
     */
    public function markAsRead(int $bookingId): JsonResponse
    {
        $user = Auth::user();
        $booking = Booking::findOrFail($bookingId);

        // Check authorization
        $this->conversationService->assertUserCanChat($user, $booking);

        $conversation = $booking->conversation;
        if (!$conversation) {
            return response()->json([
                'success' => true,
                'data' => ['marked_count' => 0],
            ]);
        }

        $markedCount = $this->messageService->markAsRead($conversation, $user);

        return response()->json([
            'success' => true,
            'data' => ['marked_count' => $markedCount],
        ]);
    }
}
