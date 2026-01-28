<?php

namespace App\Services;

use App\Models\Ride;
use App\Models\DriverProfile;
use App\Models\City;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RideService
{
    protected GeoService $geoService;

    public function __construct(GeoService $geoService)
    {
        $this->geoService = $geoService;
    }

    /**
     * Validate ride creation data.
     */
    public function validateRideData(array $data, DriverProfile $driverProfile): array
    {
        // Check driver is approved
        if (!$driverProfile->isApproved()) {
            throw ValidationException::withMessages([
                'driver' => ['Only approved drivers can create rides.'],
            ]);
        }

        // Check rides are enabled
        if (!SystemSetting::get('rides.enabled', true)) {
            throw ValidationException::withMessages([
                'rides' => ['Ride creation is currently disabled.'],
            ]);
        }

        // Validate city
        $city = City::find($data['city_id']);
        if (!$city || !$city->is_active) {
            throw ValidationException::withMessages([
                'city_id' => ['Selected city is not available.'],
            ]);
        }

        // Validate departure time
        $departureAt = \Carbon\Carbon::parse($data['departure_at']);
        $minHours = SystemSetting::get('rides.min_hours_before_departure', 1);
        $maxDays = SystemSetting::get('rides.max_days_in_future', 60);

        if ($departureAt < now()->addHours($minHours)) {
            throw ValidationException::withMessages([
                'departure_at' => ["Departure time must be at least {$minHours} hours from now."],
            ]);
        }

        if ($departureAt > now()->addDays($maxDays)) {
            throw ValidationException::withMessages([
                'departure_at' => ["Departure time cannot be more than {$maxDays} days in the future."],
            ]);
        }

        // Validate seats
        $maxSeats = SystemSetting::get('rides.max_seats', 8);
        if ($data['seats_total'] < 1 || $data['seats_total'] > $maxSeats) {
            throw ValidationException::withMessages([
                'seats_total' => ["Seats must be between 1 and {$maxSeats}."],
            ]);
        }

        // Validate price
        $minPrice = SystemSetting::get('rides.min_price', 0);
        $maxPrice = SystemSetting::get('rides.max_price', 9999);
        if ($data['price_per_seat'] < $minPrice || $data['price_per_seat'] > $maxPrice) {
            throw ValidationException::withMessages([
                'price_per_seat' => ["Price must be between {$minPrice} and {$maxPrice}."],
            ]);
        }

        // Validate waypoints
        $maxWaypoints = SystemSetting::get('rides.max_waypoints', 3);
        $waypoints = $data['waypoints'] ?? [];
        if (count($waypoints) > $maxWaypoints) {
            throw ValidationException::withMessages([
                'waypoints' => ["Maximum {$maxWaypoints} waypoints allowed."],
            ]);
        }

        // Validate serviceability if required
        $requireServiceable = SystemSetting::get('rides.require_serviceable_points', false);
        if ($requireServiceable) {
            $originCheck = $this->geoService->isPointServiceableForCity(
                $data['city_id'],
                $data['origin_lat'],
                $data['origin_lng']
            );
            if (!$originCheck['serviceable']) {
                throw ValidationException::withMessages([
                    'origin' => ['Origin point is not within serviceable area: ' . ($originCheck['reason'] ?? 'Unknown')],
                ]);
            }

            $destCheck = $this->geoService->isPointServiceableForCity(
                $data['city_id'],
                $data['destination_lat'],
                $data['destination_lng']
            );
            if (!$destCheck['serviceable']) {
                throw ValidationException::withMessages([
                    'destination' => ['Destination point is not within serviceable area: ' . ($destCheck['reason'] ?? 'Unknown')],
                ]);
            }
        }

        // Set currency from city if enabled
        if (SystemSetting::get('rides.currency_inherit_from_city', true)) {
            $data['currency_code'] = $city->currency_code ?? $city->currency ?? 'USD';
        }

        return $data;
    }

    /**
     * Create a ride.
     */
    public function createRide(array $data, DriverProfile $driverProfile, ?string $ipAddress = null): Ride
    {
        $data = $this->validateRideData($data, $driverProfile);

        // Set initial seats_available
        $data['seats_available'] = $data['seats_total'];

        // Set status (draft by default if allowed)
        $allowDraft = SystemSetting::get('rides.allow_draft', true);
        $data['status'] = $allowDraft ? 'draft' : 'published';
        if (!$allowDraft) {
            $data['published_at'] = now();
        }

        $data['driver_profile_id'] = $driverProfile->id;
        $data['created_by_ip'] = $ipAddress;

        $ride = Ride::create($data);

        // Create ride stops
        $this->createRideStops($ride, $data);

        return $ride;
    }

    /**
     * Update a ride.
     */
    public function updateRide(Ride $ride, array $data, DriverProfile $driverProfile): Ride
    {
        if (!$ride->canBeEdited()) {
            throw ValidationException::withMessages([
                'ride' => ['This ride cannot be edited.'],
            ]);
        }

        // Re-validate if key fields changed
        if (isset($data['departure_at']) || isset($data['city_id']) || isset($data['seats_total'])) {
            $mergedData = array_merge($ride->toArray(), $data);
            $data = $this->validateRideData($mergedData, $driverProfile);
        }

        // Update seats_available if seats_total changed
        if (isset($data['seats_total']) && $data['seats_total'] != $ride->seats_total) {
            $diff = $data['seats_total'] - $ride->seats_total;
            $data['seats_available'] = max(0, $ride->seats_available + $diff);
        }

        $ride->update($data);

        // Update ride stops if waypoints changed
        if (isset($data['waypoints'])) {
            $ride->stops()->delete();
            $this->createRideStops($ride, $data);
        }

        return $ride;
    }

    /**
     * Publish a ride.
     */
    public function publishRide(Ride $ride, DriverProfile $driverProfile): Ride
    {
        if (!$ride->driverProfile->isApproved()) {
            throw ValidationException::withMessages([
                'driver' => ['Only approved drivers can publish rides.'],
            ]);
        }

        if ($ride->status !== 'draft') {
            throw ValidationException::withMessages([
                'ride' => ['Only draft rides can be published.'],
            ]);
        }

        // Re-validate before publishing
        $this->validateRideData($ride->toArray(), $driverProfile);

        $ride->publish();

        return $ride;
    }

    /**
     * Cancel a ride.
     */
    public function cancelRide(Ride $ride, ?string $reason = null): Ride
    {
        if (!$ride->canBeCancelled()) {
            throw ValidationException::withMessages([
                'ride' => ['This ride cannot be cancelled.'],
            ]);
        }

        $ride->cancel($reason);

        return $ride;
    }

    /**
     * Create ride stops from waypoints.
     */
    protected function createRideStops(Ride $ride, array $data): void
    {
        $stops = [];

        // Origin
        $stops[] = [
            'ride_id' => $ride->id,
            'type' => 'origin',
            'name' => $data['origin_name'],
            'lat' => $data['origin_lat'],
            'lng' => $data['origin_lng'],
            'stop_order' => 0,
        ];

        // Waypoints
        $waypoints = $data['waypoints'] ?? [];
        $order = 1;
        foreach ($waypoints as $waypoint) {
            $stops[] = [
                'ride_id' => $ride->id,
                'type' => 'waypoint',
                'name' => $waypoint['name'] ?? 'Waypoint',
                'lat' => $waypoint['lat'],
                'lng' => $waypoint['lng'],
                'stop_order' => $order++,
            ];
        }

        // Destination
        $stops[] = [
            'ride_id' => $ride->id,
            'type' => 'destination',
            'name' => $data['destination_name'],
            'lat' => $data['destination_lat'],
            'lng' => $data['destination_lng'],
            'stop_order' => $order,
        ];

        foreach ($stops as $stop) {
            \App\Models\RideStop::create($stop);
        }
    }
}

