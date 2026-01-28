<?php

namespace App\Services;

use App\Models\Report;
use App\Models\User;
use App\Models\Booking;
use App\Models\SystemSetting;
use Illuminate\Validation\ValidationException;

class ReportService
{
    /**
     * Submit a report.
     */
    public function submitReport(
        User $reporter,
        int $reportedUserId,
        string $type,
        string $reason,
        ?string $comment = null,
        ?int $bookingId = null
    ): Report {
        // Check if reports are enabled
        if (!SystemSetting::get('reports.enabled', true)) {
            throw ValidationException::withMessages([
                'report' => ['Reporting is currently disabled.'],
            ]);
        }

        // Validate reported user exists
        $reportedUser = User::findOrFail($reportedUserId);

        // Cannot report yourself
        if ($reporter->id === $reportedUserId) {
            throw ValidationException::withMessages([
                'reported_user_id' => ['You cannot report yourself.'],
            ]);
        }

        // Check if comment required
        $requireComment = SystemSetting::get('reports.require_comment', false);
        if ($requireComment && empty($comment)) {
            throw ValidationException::withMessages([
                'comment' => ['Comment is required when submitting a report.'],
            ]);
        }

        // Validate booking if provided
        $booking = null;
        if ($bookingId) {
            $booking = Booking::findOrFail($bookingId);
            
            // Verify booking involves reported user
            $isRider = $booking->rider_user_id === $reportedUserId;
            $isDriver = $booking->driverProfile && $booking->driverProfile->user_id === $reportedUserId;
            
            if (!$isRider && !$isDriver) {
                throw ValidationException::withMessages([
                    'booking_id' => ['The reported user is not associated with this booking.'],
                ]);
            }
        }

        // Create report
        $report = Report::create([
            'booking_id' => $bookingId,
            'reporter_user_id' => $reporter->id,
            'reported_user_id' => $reportedUserId,
            'type' => $type,
            'reason' => $reason,
            'comment' => $comment ? trim($comment) : null,
            'status' => 'open',
        ]);

        // TODO: Send notification to admins (Phase 8)

        return $report;
    }

    /**
     * Update report status (admin only).
     */
    public function updateStatus(Report $report, string $status, ?string $adminNote = null): void
    {
        $validStatuses = ['open', 'reviewed', 'action_taken', 'dismissed'];
        if (!in_array($status, $validStatuses)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid status.'],
            ]);
        }

        // Require admin note for action_taken
        if ($status === 'action_taken' && empty($adminNote)) {
            throw ValidationException::withMessages([
                'admin_note' => ['Admin note is required when marking as action taken.'],
            ]);
        }

        $report->status = $status;
        if ($adminNote) {
            $report->admin_note = trim($adminNote);
        }
        $report->save();

        // TODO: Send notification to reporter (Phase 8)
    }
}

