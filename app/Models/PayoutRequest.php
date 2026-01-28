<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutRequest extends Model
{
    protected $fillable = [
        'driver_profile_id',
        'amount',
        'method',
        'status',
        'payout_reference',
        'admin_note',
        'requested_at',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($payout) {
            if (!$payout->requested_at) {
                $payout->requested_at = now();
            }
        });
    }

    /**
     * Get the driver profile.
     */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    /**
     * Check if payout is pending.
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['requested', 'approved', 'processing']);
    }

    /**
     * Check if payout is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if payout is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
