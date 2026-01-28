<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverWallet extends Model
{
    protected $fillable = [
        'driver_profile_id',
        'balance',
        'lifetime_earned',
        'lifetime_withdrawn',
        'last_updated_at',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'lifetime_earned' => 'decimal:2',
        'lifetime_withdrawn' => 'decimal:2',
        'last_updated_at' => 'datetime',
    ];

    /**
     * Get the driver profile.
     */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    /**
     * Get the transactions.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get formatted balance.
     */
    public function getFormattedBalance(): string
    {
        return number_format($this->balance, 2);
    }

    /**
     * Check if wallet has sufficient balance.
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }
}
