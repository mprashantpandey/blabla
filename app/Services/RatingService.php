<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Rating;
use App\Models\RatingSummary;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RatingService
{
    /**
     * Check if user can rate a booking.
     */
    public function canRate(Booking $booking, User $user): array
    {
        // Check if ratings are enabled
        if (!SystemSetting::get('ratings.enabled', true)) {
            return ['can_rate' => false, 'reason' => 'Ratings are currently disabled.'];
        }

        // Booking must be completed
        if ($booking->status !== 'completed') {
            return ['can_rate' => false, 'reason' => 'Booking must be completed to rate.'];
        }

        // Check rating window
        $windowDays = SystemSetting::get('ratings.window_days', 7);
        if ($booking->completed_at) {
            $windowEnd = $booking->completed_at->copy()->addDays($windowDays);
            
            if (now()->greaterThan($windowEnd)) {
                return ['can_rate' => false, 'reason' => "Rating window has expired. You can only rate within {$windowDays} days of completion."];
            }
        }

        // Determine role
        $isRider = $user->id === $booking->rider_user_id;
        $isDriver = $booking->driverProfile && $user->id === $booking->driverProfile->user_id;

        if (!$isRider && !$isDriver) {
            return ['can_rate' => false, 'reason' => 'You are not authorized to rate this booking.'];
        }

        $role = $isRider ? 'rider_to_driver' : 'driver_to_rider';

        // Check if already rated
        $existingRating = Rating::where('booking_id', $booking->id)
            ->where('rater_user_id', $user->id)
            ->where('role', $role)
            ->first();

        if ($existingRating) {
            return ['can_rate' => false, 'reason' => 'You have already rated this booking.', 'existing_rating' => $existingRating];
        }

        return ['can_rate' => true, 'role' => $role];
    }

    /**
     * Submit a rating.
     */
    public function submitRating(Booking $booking, User $rater, int $rating, ?string $comment = null): Rating
    {
        // Validate can rate
        $canRate = $this->canRate($booking, $rater);
        if (!$canRate['can_rate']) {
            throw ValidationException::withMessages([
                'rating' => [$canRate['reason']],
            ]);
        }

        // Validate rating value
        $minValue = SystemSetting::get('ratings.min_value', 1);
        $maxValue = SystemSetting::get('ratings.max_value', 5);
        
        if ($rating < $minValue || $rating > $maxValue) {
            throw ValidationException::withMessages([
                'rating' => ["Rating must be between {$minValue} and {$maxValue}."],
            ]);
        }

        // Check if comment required
        $requireCommentBelow = SystemSetting::get('ratings.require_comment_below', 0);
        if ($rating <= $requireCommentBelow && empty($comment)) {
            throw ValidationException::withMessages([
                'comment' => ['Comment is required for ratings of ' . $requireCommentBelow . ' stars or below.'],
            ]);
        }

        // Determine ratee
        $role = $canRate['role'];
        $rateeUserId = $role === 'rider_to_driver' 
            ? $booking->driverProfile->user_id 
            : $booking->rider_user_id;

        // Create rating
        $ratingModel = Rating::create([
            'booking_id' => $booking->id,
            'ride_id' => $booking->ride_id,
            'rater_user_id' => $rater->id,
            'ratee_user_id' => $rateeUserId,
            'role' => $role,
            'rating' => $rating,
            'comment' => $comment ? trim($comment) : null,
        ]);

        // Update summary
        $this->updateSummary($rateeUserId, $ratingModel->getRoleForSummary());

        return $ratingModel;
    }

    /**
     * Update rating summary for a user and role.
     */
    public function updateSummary(int $userId, string $role): void
    {
        // Calculate average rating and total ratings
        $ratings = Rating::where('ratee_user_id', $userId)
            ->where('role', $role === 'driver' ? 'rider_to_driver' : 'driver_to_rider')
            ->where('is_hidden', false)
            ->get();

        $avgRating = $ratings->avg('rating') ?? 0;
        $totalRatings = $ratings->count();

        // Count total trips (completed bookings)
        $totalTrips = Booking::where(function ($query) use ($userId, $role) {
            if ($role === 'driver') {
                $query->whereHas('driverProfile', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
            } else {
                $query->where('rider_user_id', $userId);
            }
        })
        ->where('status', 'completed')
        ->count();

        // Update or create summary
        RatingSummary::updateOrCreate(
            [
                'user_id' => $userId,
                'role' => $role,
            ],
            [
                'avg_rating' => round($avgRating, 2),
                'total_ratings' => $totalRatings,
                'total_trips' => $totalTrips,
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Hide a rating.
     */
    public function hideRating(Rating $rating, bool $hide = true): void
    {
        $rating->is_hidden = $hide;
        $rating->save();

        // Update summary when hiding/unhiding
        $this->updateSummary($rating->ratee_user_id, $rating->getRoleForSummary());
    }

    /**
     * Get pending ratings for a user.
     */
    public function getPendingRatings(User $user): array
    {
        $bookings = Booking::where(function ($query) use ($user) {
            $query->where('rider_user_id', $user->id)
                  ->orWhereHas('driverProfile', function ($q) use ($user) {
                      $q->where('user_id', $user->id);
                  });
        })
        ->where('status', 'completed')
        ->whereNotNull('completed_at')
        ->with(['rider', 'driverProfile.user', 'ride'])
        ->get();

        $pending = [];

        foreach ($bookings as $booking) {
            $canRate = $this->canRate($booking, $user);
            if ($canRate['can_rate']) {
                $pending[] = [
                    'booking' => $booking,
                    'role' => $canRate['role'],
                ];
            }
        }

        return $pending;
    }
}

