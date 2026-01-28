<?php

namespace App\Services;

use App\Models\User;
use App\Models\RatingSummary;
use App\Models\DriverProfile;
use Carbon\Carbon;

class TrustService
{
    /**
     * Get user trust profile.
     */
    public function getUserTrustProfile(User $user, ?string $role = null): array
    {
        $profile = [
            'user_id' => $user->id,
            'member_since' => $user->created_at,
        ];

        // Get driver trust indicators
        if ($role === 'driver' || $user->driverProfile) {
            $driverSummary = RatingSummary::where('user_id', $user->id)
                ->where('role', 'driver')
                ->first();

            $profile['driver'] = [
                'avg_rating' => $driverSummary?->avg_rating ?? 0,
                'total_ratings' => $driverSummary?->total_ratings ?? 0,
                'total_trips' => $driverSummary?->total_trips ?? 0,
                'is_verified' => $user->driverProfile?->status === 'approved',
                'verified_at' => $user->driverProfile?->verified_at,
            ];
        }

        // Get rider trust indicators
        if ($role === 'rider' || !$user->driverProfile) {
            $riderSummary = RatingSummary::where('user_id', $user->id)
                ->where('role', 'rider')
                ->first();

            $profile['rider'] = [
                'avg_rating' => $riderSummary?->avg_rating ?? 0,
                'total_ratings' => $riderSummary?->total_ratings ?? 0,
                'total_trips' => $riderSummary?->total_trips ?? 0,
            ];
        }

        return $profile;
    }

    /**
     * Get trust badges for a user.
     */
    public function getTrustBadges(User $user): array
    {
        $badges = [];

        // Verified driver badge
        if ($user->driverProfile && $user->driverProfile->status === 'approved') {
            $badges[] = [
                'type' => 'verified_driver',
                'label' => 'Verified Driver',
                'icon' => 'verified',
            ];
        }

        // High rating badge
        $driverSummary = RatingSummary::where('user_id', $user->id)
            ->where('role', 'driver')
            ->first();

        if ($driverSummary && $driverSummary->avg_rating >= 4.5 && $driverSummary->total_ratings >= 10) {
            $badges[] = [
                'type' => 'top_rated',
                'label' => 'Top Rated',
                'icon' => 'star',
            ];
        }

        // Experienced badge
        if ($driverSummary && $driverSummary->total_trips >= 100) {
            $badges[] = [
                'type' => 'experienced',
                'label' => 'Experienced',
                'icon' => 'trip',
            ];
        }

        return $badges;
    }
}

