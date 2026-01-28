<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\SystemSetting;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class MessageService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(Conversation $conversation, User $sender, string $body): Message
    {
        // Check if conversation is active
        if ($conversation->isClosed()) {
            throw ValidationException::withMessages([
                'conversation' => ['This conversation is closed.'],
            ]);
        }

        // Check rate limit
        $this->checkRateLimit($sender);

        // Validate message length
        $maxLength = SystemSetting::get('chat.max_message_length', 1000);
        if (strlen($body) > $maxLength) {
            throw ValidationException::withMessages([
                'body' => ["Message must not exceed {$maxLength} characters."],
            ]);
        }

        // Create message
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_user_id' => $sender->id,
            'message_type' => 'text',
            'body' => trim($body),
        ]);

        // Update conversation last message time
        $conversation->updateLastMessageAt();

        // Mark as read by sender
        $message->markAsReadBy($sender->id);

        // Send notification to receiver
        $receiver = $conversation->rider_user_id === $sender->id 
            ? $conversation->driver 
            : $conversation->rider;

        if ($receiver) {
            $this->notificationService->sendChatNotification($receiver, $message);
        }

        return $message;
    }

    /**
     * Mark messages as read for a user.
     */
    public function markAsRead(Conversation $conversation, User $user): int
    {
        $unreadMessages = $conversation->messages()
            ->where('sender_user_id', '!=', $user->id)
            ->whereDoesntHave('reads', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->get();

        $count = 0;
        foreach ($unreadMessages as $message) {
            $message->markAsReadBy($user->id);
            $count++;
        }

        return $count;
    }

    /**
     * Insert a system message.
     */
    public function insertSystemMessage(Conversation $conversation, string $event, array $meta = []): Message
    {
        if (!SystemSetting::get('chat.system_messages_enabled', true)) {
            return null;
        }

        $body = $this->getSystemMessageBody($event, $meta);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_user_id' => null,
            'message_type' => 'system',
            'body' => $body,
            'meta' => array_merge($meta, ['event' => $event]),
        ]);

        $conversation->updateLastMessageAt();

        return $message;
    }

    /**
     * Get unread message count for a user.
     */
    public function getUnreadCount(Conversation $conversation, User $user): int
    {
        return $conversation->messages()
            ->where('sender_user_id', '!=', $user->id)
            ->where('message_type', 'text')
            ->whereDoesntHave('reads', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->count();
    }

    /**
     * Check rate limit for sending messages.
     */
    protected function checkRateLimit(User $user): void
    {
        $rateLimit = SystemSetting::get('chat.rate_limit_per_minute', 10);
        $key = 'message_rate_limit:' . $user->id;

        $executed = RateLimiter::attempt(
            $key,
            $rateLimit,
            function () {
                // Rate limit passed
            },
            60 // 1 minute
        );

        if (!$executed) {
            throw ValidationException::withMessages([
                'rate_limit' => ["You can only send {$rateLimit} messages per minute. Please wait a moment."],
            ]);
        }
    }

    /**
     * Get system message body based on event.
     */
    protected function getSystemMessageBody(string $event, array $meta): string
    {
        return match ($event) {
            'booking_accepted' => 'Booking has been accepted by the driver.',
            'booking_rejected' => 'Booking has been rejected by the driver.',
            'booking_cancelled' => 'Booking has been cancelled.',
            'booking_completed' => 'Ride has been completed.',
            'booking_confirmed' => 'Booking has been confirmed.',
            default => 'System notification.',
        };
    }
}

