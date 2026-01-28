<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    /**
     * Get or create conversation for a booking.
     */
    public function getOrCreateConversation(Booking $booking): Conversation
    {
        $conversation = $booking->conversation;

        if (!$conversation) {
            // Check if chat is enabled
            if (!SystemSetting::get('chat.enabled', true)) {
                throw ValidationException::withMessages([
                    'chat' => ['Chat is currently disabled.'],
                ]);
            }

            // Check if booking allows chat
            if (!$this->canChat($booking)) {
                throw ValidationException::withMessages([
                    'chat' => ['Chat is not available for this booking.'],
                ]);
            }

            $driverUserId = null;
            if ($booking->driver_profile_id && $booking->driverProfile) {
                $driverUserId = $booking->driverProfile->user_id;
            }

            $conversation = Conversation::create([
                'booking_id' => $booking->id,
                'ride_id' => $booking->ride_id,
                'rider_user_id' => $booking->rider_user_id,
                'driver_user_id' => $driverUserId,
                'status' => 'active',
            ]);
        }

        return $conversation;
    }

    /**
     * Close conversation for a booking.
     */
    public function closeConversation(Booking $booking): void
    {
        $conversation = $booking->conversation;
        if ($conversation && $conversation->isActive()) {
            $conversation->close();
        }
    }

    /**
     * Check if user can chat in this booking.
     */
    public function assertUserCanChat(User $user, Booking $booking): void
    {
        if (!$this->canChat($booking)) {
            throw ValidationException::withMessages([
                'chat' => ['Chat is not available for this booking.'],
            ]);
        }

        $isRider = $user->id === $booking->rider_user_id;
        $isDriver = $booking->driverProfile && $user->id === $booking->driverProfile->user_id;

        if (!$isRider && !$isDriver) {
            throw ValidationException::withMessages([
                'chat' => ['You are not authorized to chat in this booking.'],
            ]);
        }
    }

    /**
     * Check if chat is available for a booking.
     */
    public function canChat(Booking $booking): bool
    {
        // Chat must be enabled
        if (!SystemSetting::get('chat.enabled', true)) {
            return false;
        }

        // Chat opens when booking is accepted or later
        $allowedStatuses = ['accepted', 'payment_pending', 'confirmed'];
        if (!in_array($booking->status, $allowedStatuses)) {
            return false;
        }

        // Check if chat should close after completion
        $allowAfterCompletion = SystemSetting::get('chat.allow_after_completion', false);
        if (!$allowAfterCompletion && in_array($booking->status, ['completed', 'cancelled', 'refunded'])) {
            return false;
        }

        return true;
    }
}

