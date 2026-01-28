<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'ride_id',
        'rater_user_id',
        'ratee_user_id',
        'role',
        'rating',
        'comment',
        'is_hidden',
        'created_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_hidden' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($rating) {
            if (!$rating->created_at) {
                $rating->created_at = now();
            }
        });

        static::saved(function ($rating) {
            // Update rating summary
            $ratingService = app(\App\Services\RatingService::class);
            $ratingService->updateSummary($rating->ratee_user_id, $rating->getRoleForSummary());
        });

        static::deleted(function ($rating) {
            // Update rating summary
            $ratingService = app(\App\Services\RatingService::class);
            $ratingService->updateSummary($rating->ratee_user_id, $rating->getRoleForSummary());
        });
    }

    /**
     * Get the booking.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Get the ride.
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    /**
     * Get the rater user.
     */
    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_user_id');
    }

    /**
     * Get the ratee user.
     */
    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratee_user_id');
    }

    /**
     * Get role for summary (driver or rider).
     */
    public function getRoleForSummary(): string
    {
        return $this->role === 'rider_to_driver' ? 'driver' : 'rider';
    }

    /**
     * Check if rating is visible.
     */
    public function isVisible(): bool
    {
        return !$this->is_hidden;
    }
}
