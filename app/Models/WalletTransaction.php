<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'driver_wallet_id',
        'booking_id',
        'type',
        'amount',
        'direction',
        'description',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($transaction) {
            if (!$transaction->created_at) {
                $transaction->created_at = now();
            }
        });
    }

    /**
     * Get the driver wallet.
     */
    public function driverWallet(): BelongsTo
    {
        return $this->belongsTo(DriverWallet::class);
    }

    /**
     * Get the booking.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Check if transaction is a credit.
     */
    public function isCredit(): bool
    {
        return $this->direction === 'credit';
    }

    /**
     * Check if transaction is a debit.
     */
    public function isDebit(): bool
    {
        return $this->direction === 'debit';
    }
}
