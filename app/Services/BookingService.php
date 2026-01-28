<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Ride;
use App\Models\User;
use App\Models\SystemSetting;
use App\Services\NotificationService;
use App\Services\ConversationService;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    protected NotificationService $notificationService;
    protected ConversationService $conversationService;
    protected MessageService $messageService;

    public function __construct(
        NotificationService $notificationService,
        ConversationService $conversationService,
        MessageService $messageService
    ) {
        $this->notificationService = $notificationService;
        $this->conversationService = $conversationService;
        $this->messageService = $messageService;
    }

    /**
     * Create a booking request.
     */
    public function createBooking(Ride $ride, User $rider, int $seatsRequested, string $paymentMethod): Booking
    {
        // Validate ride
        if (!$ride->isPublished() || !$ride->isUpcoming()) {
            throw ValidationException::withMessages([
                'ride' => ['Ride is not available for booking.'],
            ]);
        }

        if ($seatsRequested > $ride->seats_available) {
            throw ValidationException::withMessages([
                'seats' => ['Not enough seats available.'],
            ]);
        }

        // Check max active requests per user
        $maxActive = SystemSetting::get('bookings.max_active_requests_per_user', 5);
        $activeCount = Booking::where('rider_user_id', $rider->id)
            ->whereIn('status', ['requested', 'accepted', 'payment_pending', 'confirmed'])
            ->count();
        
        if ($activeCount >= $maxActive) {
            throw ValidationException::withMessages([
                'bookings' => ["Maximum {$maxActive} active booking requests allowed."],
            ]);
        }

        // Calculate pricing
        $pricePerSeat = $ride->price_per_seat;
        $subtotal = $pricePerSeat * $seatsRequested;

        // Get commission settings
        $commissionType = SystemSetting::get('business.commission_type', 'percent');
        $commissionValue = SystemSetting::get('business.commission_value', 0);
        $commissionAmount = 0;

        if ($commissionType === 'percent') {
            $commissionAmount = ($subtotal * $commissionValue) / 100;
        } elseif ($commissionType === 'fixed') {
            $commissionAmount = $commissionValue;
        }

        $totalAmount = $subtotal; // Rider pays subtotal; commission is platform take

        // Reserve seats atomically
        if (!$ride->reserveSeats($seatsRequested)) {
            throw ValidationException::withMessages([
                'seats' => ['Seats are no longer available.'],
            ]);
        }

        // Set hold expiration
        $holdMinutes = SystemSetting::get('bookings.seat_hold_minutes', 10);
        $holdExpiresAt = now()->addMinutes($holdMinutes);

        // Determine initial status
        $requireAcceptance = SystemSetting::get('bookings.require_driver_acceptance_default', true);
        $status = 'requested';

        if ($ride->allow_instant_booking && !$requireAcceptance && $paymentMethod === 'cash') {
            $status = 'accepted';
        } elseif ($paymentMethod !== 'cash') {
            $status = 'payment_pending';
        }

        DB::beginTransaction();
        try {
            $booking = Booking::create([
                'ride_id' => $ride->id,
                'rider_user_id' => $rider->id,
                'driver_profile_id' => $ride->driver_profile_id,
                'city_id' => $ride->city_id,
                'status' => $status,
                'seats_requested' => $seatsRequested,
                'price_per_seat' => $pricePerSeat,
                'subtotal' => $subtotal,
                'commission_type' => $commissionType,
                'commission_value' => $commissionValue,
                'commission_amount' => $commissionAmount,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentMethod === 'cash' ? 'unpaid' : 'pending',
                'hold_expires_at' => $holdExpiresAt,
            ]);

            $booking->recordEvent('requested', $rider->id);

            if ($status === 'accepted') {
                $booking->accept();
            }

            DB::commit();

        // Send notifications
        $this->notificationService->sendBookingNotification('requested', $booking);

        if ($status === 'accepted') {
            $this->notificationService->sendBookingNotification('accepted', $booking);
            
            // Create conversation and insert system message
            try {
                $conversation = $this->conversationService->getOrCreateConversation($booking);
                $this->messageService->insertSystemMessage($conversation, 'booking_accepted');
            } catch (\Exception $e) {
                // Log but don't fail booking creation
                \Illuminate\Support\Facades\Log::warning('Failed to create conversation', ['error' => $e->getMessage()]);
            }
        }

        return $booking;
        } catch (\Exception $e) {
            DB::rollBack();
            // Release seats on error
            $ride->releaseSeats($seatsRequested);
            throw $e;
        }
    }

    /**
     * Accept a booking.
     */
    public function acceptBooking(Booking $booking, User $driver): void
    {
        if ($booking->driver_profile_id !== $driver->driverProfile->id) {
            throw ValidationException::withMessages([
                'booking' => ['Unauthorized.'],
            ]);
        }

        if ($booking->status !== 'requested') {
            throw ValidationException::withMessages([
                'booking' => ['Booking cannot be accepted in current status.'],
            ]);
        }

        $booking->accept($driver->id);

        // If cash payment, confirm immediately
        if ($booking->payment_method === 'cash') {
            $booking->confirm($driver->id);
        }

        // Send notification
        $this->notificationService->sendBookingNotification('accepted', $booking);

        // Create conversation and insert system message
        try {
            $conversation = $this->conversationService->getOrCreateConversation($booking);
            $this->messageService->insertSystemMessage($conversation, 'booking_accepted');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to create conversation', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reject a booking.
     */
    public function rejectBooking(Booking $booking, User $driver, ?string $reason = null): void
    {
        if ($booking->driver_profile_id !== $driver->driverProfile->id) {
            throw ValidationException::withMessages([
                'booking' => ['Unauthorized.'],
            ]);
        }

        if ($booking->status !== 'requested') {
            throw ValidationException::withMessages([
                'booking' => ['Booking cannot be rejected in current status.'],
            ]);
        }

        $booking->reject($reason, $driver->id);

        // Send notification
        $this->notificationService->sendBookingNotification('rejected', $booking, $reason ? "Your booking was rejected: {$reason}" : null);

        // Insert system message if conversation exists
        try {
            $conversation = $booking->conversation;
            if ($conversation) {
                $this->messageService->insertSystemMessage($conversation, 'booking_rejected', ['reason' => $reason]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to insert system message', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Cancel a booking.
     */
    public function cancelBooking(Booking $booking, User $user, ?string $reason = null): void
    {
        // Check authorization
        $isRider = $booking->rider_user_id === $user->id;
        $isDriver = $booking->driver_profile_id === $user->driverProfile->id ?? null;

        if (!$isRider && !$isDriver) {
            throw ValidationException::withMessages([
                'booking' => ['Unauthorized.'],
            ]);
        }

        if (!$booking->canBeCancelled()) {
            throw ValidationException::withMessages([
                'booking' => ['Booking cannot be cancelled.'],
            ]);
        }

        $oldStatus = $booking->status;
        $booking->cancel($reason, $user->id);

        // Handle refund if needed
        if ($booking->payment_status === 'paid' && $booking->payment_method !== 'cash') {
            $refundPolicy = SystemSetting::get('bookings.refund_policy', 'none');
            
            if ($refundPolicy !== 'none') {
                // Mark for refund (actual refund processing in PaymentService)
                $booking->payment_status = 'refunded';
                $booking->status = 'refunded';
                $booking->refunded_at = now();
                $booking->save();
            }
        }

        // Send notifications
        $this->notificationService->sendBookingNotification('cancelled', $booking, $reason ? "Booking cancelled: {$reason}" : null);

        // Insert system message and close conversation
        try {
            $conversation = $booking->conversation;
            if ($conversation) {
                $this->messageService->insertSystemMessage($conversation, 'booking_cancelled', ['reason' => $reason]);
                $this->conversationService->closeConversation($booking);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to update conversation', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Confirm booking after payment.
     */
    public function confirmBooking(Booking $booking): void
    {
        if (!in_array($booking->status, ['accepted', 'payment_pending'])) {
            throw ValidationException::withMessages([
                'booking' => ['Booking cannot be confirmed in current status.'],
            ]);
        }

        $booking->confirm();

        // Send notifications
        $this->notificationService->sendBookingNotification('confirmed', $booking);
        
        // Also notify driver
        $this->notificationService->sendToUser(
            $booking->driverProfile->user,
            'Booking Confirmed',
            'A booking has been confirmed for your ride.',
            ['type' => 'booking_confirmed', 'booking_id' => $booking->id],
            true
        );

        // Insert system message
        try {
            $conversation = $booking->conversation;
            if ($conversation) {
                $this->messageService->insertSystemMessage($conversation, 'booking_confirmed');
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to insert system message', ['error' => $e->getMessage()]);
        }
    }
}

