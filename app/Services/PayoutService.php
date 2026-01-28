<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\PayoutRequest;
use App\Models\SystemSetting;
use App\Services\WalletService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayoutService
{
    protected WalletService $walletService;
    protected NotificationService $notificationService;

    public function __construct(
        WalletService $walletService,
        NotificationService $notificationService
    ) {
        $this->walletService = $walletService;
        $this->notificationService = $notificationService;
    }

    /**
     * Request a payout.
     */
    public function requestPayout(DriverProfile $driverProfile, float $amount, string $method): PayoutRequest
    {
        // Check if payouts are enabled
        if (!SystemSetting::get('payouts.enabled', true)) {
            throw ValidationException::withMessages([
                'payout' => ['Payouts are currently disabled.'],
            ]);
        }

        // Check minimum payout amount
        $minAmount = SystemSetting::get('wallet.min_payout_amount', 100);
        if ($amount < $minAmount) {
            throw ValidationException::withMessages([
                'amount' => ["Minimum payout amount is " . number_format($minAmount, 2) . "."],
            ]);
        }

        // Check available methods
        $allowedMethods = SystemSetting::get('payouts.methods', ['bank', 'manual']);
        if (!in_array($method, $allowedMethods)) {
            throw ValidationException::withMessages([
                'method' => ['Selected payout method is not available.'],
            ]);
        }

        // Ensure sufficient balance
        $this->walletService->ensureSufficientBalance($driverProfile, $amount);

        return DB::transaction(function () use ($driverProfile, $amount, $method) {
            // Debit wallet immediately (hold funds)
            $this->walletService->debit(
                $driverProfile,
                $amount,
                'payout',
                null,
                "Payout request: {$method}",
                ['method' => $method]
            );

            // Create payout request
            $payout = PayoutRequest::create([
                'driver_profile_id' => $driverProfile->id,
                'amount' => $amount,
                'method' => $method,
                'status' => 'requested',
            ]);

            // Auto-approve if enabled
            $autoApprove = SystemSetting::get('payouts.auto_approve', false);
            if ($autoApprove) {
                $this->approvePayout($payout, auth()->user() ?? $driverProfile->user);
            } else {
                // Send notification to admins (TODO: Phase 9)
                $this->notificationService->sendToUser(
                    $driverProfile->user,
                    'Payout Requested',
                    "Your payout request of " . number_format($amount, 2) . " has been submitted.",
                    ['type' => 'payout_requested', 'payout_id' => $payout->id],
                    true
                );
            }

            return $payout;
        });
    }

    /**
     * Approve a payout.
     */
    public function approvePayout(PayoutRequest $payout, $admin): void
    {
        if ($payout->status !== 'requested') {
            throw ValidationException::withMessages([
                'payout' => ['Payout cannot be approved in current status.'],
            ]);
        }

        $payout->status = 'approved';
        $payout->save();

        // Send notification
        $this->notificationService->sendToUser(
            $payout->driverProfile->user,
            'Payout Approved',
            "Your payout request of " . number_format($payout->amount, 2) . " has been approved.",
            ['type' => 'payout_approved', 'payout_id' => $payout->id],
            true
        );
    }

    /**
     * Reject a payout.
     */
    public function rejectPayout(PayoutRequest $payout, $admin, string $reason): void
    {
        if (!in_array($payout->status, ['requested', 'approved'])) {
            throw ValidationException::withMessages([
                'payout' => ['Payout cannot be rejected in current status.'],
            ]);
        }

        DB::transaction(function () use ($payout, $reason) {
            // Credit wallet back
            $this->walletService->credit(
                $payout->driverProfile,
                $payout->amount,
                'refund',
                null,
                "Payout rejection refund",
                [
                    'payout_request_id' => $payout->id,
                    'reason' => $reason,
                ]
            );

            $payout->status = 'rejected';
            $payout->admin_note = $reason;
            $payout->save();
        });

        // Send notification
        $this->notificationService->sendToUser(
            $payout->driverProfile->user,
            'Payout Rejected',
            "Your payout request has been rejected: {$reason}",
            ['type' => 'payout_rejected', 'payout_id' => $payout->id],
            true
        );
    }

    /**
     * Mark payout as paid.
     */
    public function markPaid(PayoutRequest $payout, string $reference): void
    {
        if (!in_array($payout->status, ['approved', 'processing'])) {
            throw ValidationException::withMessages([
                'payout' => ['Payout cannot be marked as paid in current status.'],
            ]);
        }

        $payout->status = 'paid';
        $payout->payout_reference = $reference;
        $payout->processed_at = now();
        $payout->save();

        // Send notification
        $this->notificationService->sendToUser(
            $payout->driverProfile->user,
            'Payout Completed',
            "Your payout of " . number_format($payout->amount, 2) . " has been processed. Reference: {$reference}",
            ['type' => 'payout_paid', 'payout_id' => $payout->id],
            true
        );
    }
}

