<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'booking_id',
        'ride_id',
        'rider_user_id',
        'driver_user_id',
        'status',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * Get the booking.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

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
     * Get the driver user.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    /**
     * Get the messages.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->where('is_deleted', false)->orderBy('created_at');
    }

    /**
     * Get all messages including deleted.
     */
    public function allMessages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /**
     * Check if conversation is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if conversation is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Close the conversation.
     */
    public function close(): void
    {
        $this->status = 'closed';
        $this->save();
    }

    /**
     * Update last message timestamp.
     */
    public function updateLastMessageAt(): void
    {
        $this->last_message_at = now();
        $this->save();
    }
}
