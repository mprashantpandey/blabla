<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Services\RatingService;
use App\Services\TrustService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    protected RatingService $ratingService;
    protected TrustService $trustService;

    public function __construct(
        RatingService $ratingService,
        TrustService $trustService
    ) {
        $this->ratingService = $ratingService;
        $this->trustService = $trustService;
    }

    /**
     * Get pending ratings for current user.
     */
    public function pending(): JsonResponse
    {
        $user = Auth::user();
        $pending = $this->ratingService->getPendingRatings($user);

        return response()->json([
            'success' => true,
            'data' => [
                'pending_ratings' => array_map(function ($item) {
                    return [
                        'booking_id' => $item['booking']->id,
                        'ride_id' => $item['booking']->ride_id,
                        'role' => $item['role'],
                        'booking' => [
                            'id' => $item['booking']->id,
                            'origin' => $item['booking']->ride->origin_name,
                            'destination' => $item['booking']->ride->destination_name,
                            'departure_at' => $item['booking']->ride->departure_at,
                            'completed_at' => $item['booking']->completed_at,
                        ],
                        'other_party' => $item['role'] === 'rider_to_driver' 
                            ? [
                                'id' => $item['booking']->driverProfile->user_id,
                                'name' => $item['booking']->driverProfile->user->name,
                            ]
                            : [
                                'id' => $item['booking']->rider->id,
                                'name' => $item['booking']->rider->name,
                            ],
                    ];
                }, $pending),
            ],
        ]);
    }

    /**
     * Submit a rating.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        $booking = Booking::findOrFail($request->booking_id);

        try {
            $rating = $this->ratingService->submitRating(
                $booking,
                $user,
                $request->rating,
                $request->comment
            );

            return response()->json([
                'success' => true,
                'message' => 'Rating submitted successfully',
                'data' => [
                    'rating' => [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'comment' => $rating->comment,
                        'created_at' => $rating->created_at,
                    ],
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get ratings for a user.
     */
    public function userRatings(int $userId, Request $request): JsonResponse
    {
        $role = $request->get('role', 'driver'); // driver or rider
        
        $ratings = \App\Models\Rating::where('ratee_user_id', $userId)
            ->where('role', $role === 'driver' ? 'rider_to_driver' : 'driver_to_rider')
            ->where('is_hidden', false)
            ->with(['rater:id,name', 'booking.ride'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'ratings' => $ratings->items()->map(function ($rating) {
                    return [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'comment' => $rating->comment,
                        'rater' => [
                            'id' => $rating->rater->id,
                            'name' => $rating->rater->name,
                        ],
                        'ride' => [
                            'origin' => $rating->booking->ride->origin_name,
                            'destination' => $rating->booking->ride->destination_name,
                        ],
                        'created_at' => $rating->created_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $ratings->currentPage(),
                    'last_page' => $ratings->lastPage(),
                    'per_page' => $ratings->perPage(),
                    'total' => $ratings->total(),
                ],
            ],
        ]);
    }

    /**
     * Get trust profile for a user.
     */
    public function trustProfile(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $profile = $this->trustService->getUserTrustProfile($user);
        $badges = $this->trustService->getTrustBadges($user);

        return response()->json([
            'success' => true,
            'data' => [
                'trust_profile' => $profile,
                'badges' => $badges,
            ],
        ]);
    }
}
