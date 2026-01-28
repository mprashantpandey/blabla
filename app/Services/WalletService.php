<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\DriverWallet;
use App\Models\WalletTransaction;
use App\Models\Booking;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    /**
     * Get or create wallet for driver.
     */
    public function getOrCreateWallet(DriverProfile $driverProfile): DriverWallet
    {
        $wallet = $driverProfile->wallet;

        if (!$wallet) {
            $wallet = DriverWallet::create([
                'driver_profile_id' => $driverProfile->id,
                'balance' => 0,
                'lifetime_earned' => 0,
                'lifetime_withdrawn' => 0,
            ]);
        }

        return $wallet;
    }

    /**
     * Credit driver wallet.
     */
    public function credit(DriverProfile $driverProfile, float $amount, string $type, ?Booking $booking = null, ?string $description = null, array $meta = []): WalletTransaction
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Amount must be greater than zero.'],
            ]);
        }

        $wallet = $this->getOrCreateWallet($driverProfile);

        return DB::transaction(function () use ($wallet, $amount, $type, $booking, $description, $meta) {
            // Create transaction
            $transaction = WalletTransaction::create([
                'driver_wallet_id' => $wallet->id,
                'booking_id' => $booking?->id,
                'type' => $type,
                'amount' => $amount,
                'direction' => 'credit',
                'description' => $description ?? $this->getDefaultDescription($type, $booking),
                'meta' => $meta,
            ]);

            // Update wallet balance
            $wallet->balance += $amount;
            
            if ($type === 'earning') {
                $wallet->lifetime_earned += $amount;
            }
            
            $wallet->last_updated_at = now();
            $wallet->save();

            return $transaction;
        });
    }

    /**
     * Debit driver wallet.
     */
    public function debit(DriverProfile $driverProfile, float $amount, string $type, ?Booking $booking = null, ?string $description = null, array $meta = []): WalletTransaction
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Amount must be greater than zero.'],
            ]);
        }

        $wallet = $this->getOrCreateWallet($driverProfile);

        // Check balance
        $allowNegative = SystemSetting::get('wallet.allow_negative_balance', false);
        if (!$allowNegative && $wallet->balance < $amount) {
            throw ValidationException::withMessages([
                'balance' => ['Insufficient wallet balance.'],
            ]);
        }

        return DB::transaction(function () use ($wallet, $amount, $type, $booking, $description, $meta) {
            // Create transaction
            $transaction = WalletTransaction::create([
                'driver_wallet_id' => $wallet->id,
                'booking_id' => $booking?->id,
                'type' => $type,
                'amount' => $amount,
                'direction' => 'debit',
                'description' => $description ?? $this->getDefaultDescription($type, $booking),
                'meta' => $meta,
            ]);

            // Update wallet balance
            $wallet->balance -= $amount;
            
            if ($type === 'payout') {
                $wallet->lifetime_withdrawn += $amount;
            }
            
            $wallet->last_updated_at = now();
            $wallet->save();

            return $transaction;
        });
    }

    /**
     * Get wallet balance.
     */
    public function getBalance(DriverProfile $driverProfile): float
    {
        $wallet = $this->getOrCreateWallet($driverProfile);
        return (float) $wallet->balance;
    }

    /**
     * Ensure sufficient balance.
     */
    public function ensureSufficientBalance(DriverProfile $driverProfile, float $amount): void
    {
        $balance = $this->getBalance($driverProfile);
        $allowNegative = SystemSetting::get('wallet.allow_negative_balance', false);
        
        if (!$allowNegative && $balance < $amount) {
            throw ValidationException::withMessages([
                'balance' => ['Insufficient wallet balance.'],
            ]);
        }
    }

    /**
     * Process booking completion - credit earnings.
     */
    public function processBookingCompletion(Booking $booking): void
    {
        if ($booking->status !== 'completed') {
            return;
        }

        $driverProfile = $booking->driverProfile;
        if (!$driverProfile) {
            return;
        }

        // Calculate earnings (subtotal - commission)
        $earnings = $booking->subtotal - $booking->commission_amount;

        if ($earnings > 0) {
            $this->credit(
                $driverProfile,
                $earnings,
                'earning',
                $booking,
                "Earnings from booking #{$booking->id}",
                [
                    'subtotal' => $booking->subtotal,
                    'commission_amount' => $booking->commission_amount,
                    'commission_type' => $booking->commission_type,
                ]
            );
        }

        // Optionally record commission as separate transaction for clarity
        if ($booking->commission_amount > 0) {
            WalletTransaction::create([
                'driver_wallet_id' => $driverProfile->wallet->id,
                'booking_id' => $booking->id,
                'type' => 'commission',
                'amount' => $booking->commission_amount,
                'direction' => 'debit',
                'description' => "Commission deducted from booking #{$booking->id}",
                'meta' => [
                    'commission_type' => $booking->commission_type,
                    'commission_value' => $booking->commission_value,
                ],
            ]);
        }
    }

    /**
     * Process booking refund - reverse earnings.
     */
    public function processBookingRefund(Booking $booking): void
    {
        $driverProfile = $booking->driverProfile;
        if (!$driverProfile) {
            return;
        }

        $wallet = $this->getOrCreateWallet($driverProfile);

        // Find original earning transaction
        $earningTransaction = WalletTransaction::where('driver_wallet_id', $wallet->id)
            ->where('booking_id', $booking->id)
            ->where('type', 'earning')
            ->where('direction', 'credit')
            ->first();

        if ($earningTransaction) {
            // Reverse the earning
            $this->debit(
                $driverProfile,
                $earningTransaction->amount,
                'refund',
                $booking,
                "Refund for booking #{$booking->id}",
                [
                    'original_transaction_id' => $earningTransaction->id,
                ]
            );
        }
    }

    /**
     * Get default description for transaction type.
     */
    protected function getDefaultDescription(string $type, ?Booking $booking = null): string
    {
        $bookingRef = $booking ? " (Booking #{$booking->id})" : '';

        return match ($type) {
            'earning' => "Earnings{$bookingRef}",
            'commission' => "Commission{$bookingRef}",
            'refund' => "Refund{$bookingRef}",
            'adjustment' => "Admin adjustment{$bookingRef}",
            'payout' => "Payout{$bookingRef}",
            default => "Transaction{$bookingRef}",
        };
    }
}

