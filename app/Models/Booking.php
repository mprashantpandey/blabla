<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $fillable = [
        'ride_id',
        'rider_user_id',
        'driver_profile_id',
        'city_id',
        'status',
        'seats_requested',
        'price_per_seat',
        'subtotal',
        'commission_type',
        'commission_value',
        'commission_amount',
        'total_amount',
        'payment_method',
        'payment_status',
        'hold_expires_at',
        'accepted_at',
        'rejected_at',
        'confirmed_at',
        'cancelled_at',
        'completed_at',
        'refunded_at',
        'cancel_reason',
    ];

    protected $casts = [
        'price_per_seat' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'commission_value' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'hold_expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * Get the ride.
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    /**
     * Get the rider user.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_user_id');
    }

    /**
     * Get the driver profile.
     */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    /**
     * Get the city.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the payment.
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Get the booking events.
     */
    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class)->orderBy('created_at');
    }

    /**
     * Check if booking is expired.
     */
    public function isExpired(): bool
    {
        return $this->hold_expires_at && $this->hold_expires_at->isPast() &&
               in_array($this->status, ['requested', 'payment_pending']);
    }

    /**
     * Check if booking can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        if (in_array($this->status, ['cancelled', 'completed', 'expired', 'refunded'])) {
            return false;
        }

        // Check cancellation deadline
        $allowCancellation = \App\Models\SystemSetting::get('bookings.allow_cancellation', true);
        if (!$allowCancellation) {
            return false;
        }

        $deadlineHours = \App\Models\SystemSetting::get('bookings.cancellation_deadline_hours', 3);
        if ($this->ride->departure_at < now()->addHours($deadlineHours)) {
            return false;
        }

        return true;
    }

    /**
     * Record a booking event.
     */
    public function recordEvent(string $event, ?int $performedBy = null, array $meta = []): void
    {
        BookingEvent::create([
            'booking_id' => $this->id,
            'event' => $event,
            'performed_by_user_id' => $performedBy ?? auth()->id(),
            'meta' => $meta,
        ]);
    }

    /**
     * Accept booking.
     */
    public function accept(?int $performedBy = null): void
    {
        $this->status = 'accepted';
        $this->accepted_at = now();
        $this->save();

        $this->recordEvent('accepted', $performedBy);
    }

    /**
     * Reject booking.
     */
    public function reject(?string $reason = null, ?int $performedBy = null): void
    {
        $this->status = 'rejected';
        $this->rejected_at = now();
        $this->save();

        // Release seats
        $this->ride->releaseSeats($this->seats_requested);

        $this->recordEvent('rejected', $performedBy, ['reason' => $reason]);
    }

    /**
     * Confirm booking.
     */
    public function confirm(?int $performedBy = null): void
    {
        $this->status = 'confirmed';
        $this->confirmed_at = now();
        $this->save();

        $this->recordEvent('confirmed', $performedBy);
    }

    /**
     * Cancel booking.
     */
    public function cancel(?string $reason = null, ?int $performedBy = null): void
    {
        $oldStatus = $this->status;
        $this->status = 'cancelled';
        $this->cancelled_at = now();
        if ($reason) {
            $this->cancel_reason = $reason;
        }
        $this->save();

        // Release seats if not already released
        if (!in_array($oldStatus, ['rejected', 'expired'])) {
            $this->ride->releaseSeats($this->seats_requested);
        }

        $this->recordEvent('cancelled', $performedBy, ['reason' => $reason]);
    }

    /**
     * Mark booking as expired.
     */
    public function markExpired(): void
    {
        $this->status = 'expired';
        $this->save();

        // Release seats
        $this->ride->releaseSeats($this->seats_requested);

        $this->recordEvent('expired', null);
    }

    /**
     * Mark booking as completed.
     */
    public function markCompleted(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();

        $this->recordEvent('completed', null);

        // Insert system message and close conversation if not allowed after completion
        try {
            $conversation = $this->conversation;
            if ($conversation) {
                $messageService = app(\App\Services\MessageService::class);
                $messageService->insertSystemMessage($conversation, 'booking_completed');
                
                $allowAfterCompletion = \App\Models\SystemSetting::get('chat.allow_after_completion', false);
                if (!$allowAfterCompletion) {
                    $conversationService = app(\App\Services\ConversationService::class);
                    $conversationService->closeConversation($this);
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to update conversation on completion', ['error' => $e->getMessage()]);
        }

        // Credit driver wallet
        try {
            $walletService = app(\App\Services\WalletService::class);
            $walletService->processBookingCompletion($this);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to process wallet credit on completion', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get the conversation.
     */
    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    /**
     * Get the ratings.
     */
    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Get support tickets linked to this booking.
     */
    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }
}
