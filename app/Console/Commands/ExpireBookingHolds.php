<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\CronRun;
use Illuminate\Console\Command;

class ExpireBookingHolds extends Command
{
    protected $signature = 'bookings:expire-holds';

    protected $description = 'Expire booking holds that have passed their expiration time';

    public function handle(): int
    {
        try {
            $expiredCount = 0;

            $expiredBookings = Booking::whereIn('status', ['requested', 'payment_pending'])
                ->where('hold_expires_at', '<', now())
                ->get();

            foreach ($expiredBookings as $booking) {
                $booking->markExpired();
                $expiredCount++;
            }

            $message = "Expired {$expiredCount} booking holds.";
            $this->info($message);

            CronRun::record($this->signature, 'success', $message);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $errorMessage = 'Failed to expire booking holds: ' . $e->getMessage();
            $this->error($errorMessage);
            
            CronRun::record($this->signature, 'failure', $errorMessage);
            
            return Command::FAILURE;
        }
    }
}
