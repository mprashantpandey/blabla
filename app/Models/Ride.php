<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class Ride extends Model
{
    protected $fillable = [
        'driver_profile_id',
        'city_id',
        'status',
        'origin_name',
        'origin_lat',
        'origin_lng',
        'destination_name',
        'destination_lat',
        'destination_lng',
        'waypoints',
        'route_polyline',
        'departure_at',
        'arrival_estimated_at',
        'price_per_seat',
        'currency_code',
        'seats_total',
        'seats_available',
        'allow_instant_booking',
        'notes',
        'rules_json',
        'cancellation_policy',
        'published_at',
        'cancelled_at',
        'cancellation_reason',
        'completed_at',
        'created_by_ip',
    ];

    protected $casts = [
        'origin_lat' => 'decimal:8',
        'origin_lng' => 'decimal:8',
        'destination_lat' => 'decimal:8',
        'destination_lng' => 'decimal:8',
        'waypoints' => 'array',
        'departure_at' => 'datetime',
        'arrival_estimated_at' => 'datetime',
        'price_per_seat' => 'decimal:2',
        'seats_total' => 'integer',
        'seats_available' => 'integer',
        'allow_instant_booking' => 'boolean',
        'rules_json' => 'array',
        'published_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the driver profile.
     */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    /**
     * Get the city.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the ride stops.
     */
    public function stops(): HasMany
    {
        return $this->hasMany(RideStop::class)->orderBy('stop_order');
    }

    /**
     * Get the ride views.
     */
    public function views(): HasMany
    {
        return $this->hasMany(RideView::class);
    }

    /**
     * Scope: Published rides.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope: Upcoming rides.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('departure_at', '>', now());
    }

    /**
     * Scope: Rides in a specific city.
     */
    public function scopeInCity(Builder $query, int $cityId): Builder
    {
        return $query->where('city_id', $cityId);
    }

    /**
     * Scope: Rides between dates.
     */
    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('departure_at', [$startDate, $endDate]);
    }

    /**
     * Scope: Rides within price range.
     */
    public function scopePriceBetween(Builder $query, ?float $minPrice = null, ?float $maxPrice = null): Builder
    {
        if ($minPrice !== null) {
            $query->where('price_per_seat', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $query->where('price_per_seat', '<=', $maxPrice);
        }
        return $query;
    }

    /**
     * Scope: Rides with available seats.
     */
    public function scopeWithAvailableSeats(Builder $query, int $seats = 1): Builder
    {
        return $query->where('seats_available', '>=', $seats);
    }

    /**
     * Check if ride is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Check if ride is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->departure_at > now();
    }

    /**
     * Check if ride can be edited.
     */
    public function canBeEdited(): bool
    {
        return $this->status === 'draft' || ($this->status === 'published' && $this->isUpcoming());
    }

    /**
     * Check if ride can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'published']) && $this->isUpcoming();
    }

    /**
     * Reserve seats atomically (prevents overselling).
     */
    public function reserveSeats(int $count): bool
    {
        if ($count <= 0) {
            return false;
        }

        return DB::transaction(function () use ($count) {
            // Lock the row for update
            $ride = static::where('id', $this->id)
                ->where('seats_available', '>=', $count)
                ->lockForUpdate()
                ->first();

            if (!$ride) {
                return false;
            }

            $ride->seats_available -= $count;
            return $ride->save();
        });
    }

    /**
     * Release seats atomically.
     */
    public function releaseSeats(int $count): bool
    {
        if ($count <= 0) {
            return false;
        }

        return DB::transaction(function () use ($count) {
            // Lock the row for update
            $ride = static::where('id', $this->id)
                ->lockForUpdate()
                ->first();

            if (!$ride) {
                return false;
            }

            // Don't exceed original seats_total
            $newAvailable = min($ride->seats_available + $count, $ride->seats_total);
            $ride->seats_available = $newAvailable;
            return $ride->save();
        });
    }

    /**
     * Publish the ride.
     */
    public function publish(): void
    {
        $this->status = 'published';
        $this->published_at = now();
        $this->save();
    }

    /**
     * Cancel the ride.
     */
    public function cancel(?string $reason = null): void
    {
        $this->status = 'cancelled';
        $this->cancelled_at = now();
        if ($reason) {
            $this->cancellation_reason = $reason;
        }
        $this->save();
    }

    /**
     * Mark ride as completed.
     */
    public function markCompleted(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Auto-cancel if departure time has passed.
     */
    public function autoCancelIfPast(): bool
    {
        $autoCancel = \App\Models\SystemSetting::get('rides.auto_cancel_on_departure_past', true);
        
        if (!$autoCancel) {
            return false;
        }

        if ($this->status === 'published' && $this->departure_at < now()) {
            $this->cancel('Auto-cancelled: Departure time has passed');
            return true;
        }

        return false;
    }

    /**
     * Record a view.
     */
    public function recordView(?int $userId = null, ?int $cityId = null): void
    {
        RideView::create([
            'ride_id' => $this->id,
            'user_id' => $userId,
            'city_id' => $cityId ?? $this->city_id,
            'viewed_at' => now(),
        ]);
    }

    /**
     * Get the bookings.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
