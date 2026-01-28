<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'provider',
        'provider_payment_id',
        'provider_order_id',
        'amount',
        'currency_code',
        'status',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    /**
     * Get the booking.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Mark payment as paid.
     */
    public function markPaid(): void
    {
        $this->status = 'paid';
        $this->save();

        // Update booking payment status
        $this->booking->payment_status = 'paid';
        $this->booking->save();
    }

    /**
     * Mark payment as failed.
     */
    public function markFailed(): void
    {
        $this->status = 'failed';
        $this->save();

        $this->booking->payment_status = 'failed';
        $this->booking->save();
    }

    /**
     * Mark payment as refunded.
     */
    public function markRefunded(): void
    {
        $this->status = 'refunded';
        $this->save();

        $this->booking->payment_status = 'refunded';
        $this->booking->refunded_at = now();
        $this->booking->status = 'refunded';
        $this->booking->save();
    }
}
