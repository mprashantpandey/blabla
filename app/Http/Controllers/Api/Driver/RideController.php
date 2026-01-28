<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Ride\CreateRideRequest;
use App\Http\Requests\Ride\UpdateRideRequest;
use App\Models\Ride;
use App\Services\RideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideController extends BaseController
{
    protected RideService $rideService;

    public function __construct(RideService $rideService)
    {
        $this->rideService = $rideService;
    }

    /**
     * Get driver's rides.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found');
        }

        $query = $profile->rides()->with(['city', 'stops']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('upcoming')) {
            if ($request->boolean('upcoming')) {
                $query->upcoming();
            } else {
                $query->where('departure_at', '<=', now());
            }
        }

        $rides = $query->orderBy('departure_at', 'desc')->paginate($request->get('per_page', 15));

        $data = $rides->map(fn ($ride) => $this->formatRide($ride));

        return $this->success($data, 'Rides retrieved successfully', 200, [
            'current_page' => $rides->currentPage(),
            'last_page' => $rides->lastPage(),
            'per_page' => $rides->perPage(),
            'total' => $rides->total(),
        ]);
    }

    /**
     * Create a ride.
     */
    public function store(CreateRideRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found');
        }

        try {
            $ride = $this->rideService->createRide(
                $request->validated(),
                $profile,
                $request->ip()
            );

            return $this->success($this->formatRide($ride), 'Ride created successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Failed to create ride: ' . $e->getMessage());
        }
    }

    /**
     * Get a ride.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found');
        }

        $ride = $profile->rides()->with(['city', 'stops'])->find($id);

        if (!$ride) {
            return $this->error('Ride not found', [], 404);
        }

        return $this->success($this->formatRide($ride, true), 'Ride retrieved successfully');
    }

    /**
     * Update a ride.
     */
    public function update(UpdateRideRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found');
        }

        $ride = $profile->rides()->find($id);

        if (!$ride) {
            return $this->error('Ride not found', [], 404);
        }

        try {
            $ride = $this->rideService->updateRide($ride, $request->validated(), $profile);

            return $this->success($this->formatRide($ride, true), 'Ride updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Failed to update ride: ' . $e->getMessage());
        }
    }

    /**
     * Publish a ride.
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found');
        }

        $ride = $profile->rides()->find($id);

        if (!$ride) {
            return $this->error('Ride not found', [], 404);
        }

        try {
            $ride = $this->rideService->publishRide($ride, $profile);

            return $this->success($this->formatRide($ride, true), 'Ride published successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Failed to publish ride: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a ride.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found');
        }

        $ride = $profile->rides()->find($id);

        if (!$ride) {
            return $this->error('Ride not found', [], 404);
        }

        try {
            $ride = $this->rideService->cancelRide($ride, $request->reason);

            return $this->success($this->formatRide($ride, true), 'Ride cancelled successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Failed to cancel ride: ' . $e->getMessage());
        }
    }

    /**
     * Format ride for response.
     */
    protected function formatRide(Ride $ride, bool $detailed = false): array
    {
        $data = [
            'id' => $ride->id,
            'status' => $ride->status,
            'origin' => [
                'name' => $ride->origin_name,
                'lat' => (float) $ride->origin_lat,
                'lng' => (float) $ride->origin_lng,
            ],
            'destination' => [
                'name' => $ride->destination_name,
                'lat' => (float) $ride->destination_lat,
                'lng' => (float) $ride->destination_lng,
            ],
            'departure_at' => $ride->departure_at->toDateTimeString(),
            'arrival_estimated_at' => $ride->arrival_estimated_at?->toDateTimeString(),
            'price_per_seat' => (float) $ride->price_per_seat,
            'currency_code' => $ride->currency_code,
            'seats_total' => $ride->seats_total,
            'seats_available' => $ride->seats_available,
            'city' => $ride->city ? [
                'id' => $ride->city->id,
                'name' => $ride->city->name,
            ] : null,
        ];

        if ($detailed) {
            $data['waypoints'] = $ride->waypoints ?? [];
            $data['route_polyline'] = $ride->route_polyline;
            $data['allow_instant_booking'] = $ride->allow_instant_booking;
            $data['notes'] = $ride->notes;
            $data['rules_json'] = $ride->rules_json;
            $data['cancellation_policy'] = $ride->cancellation_policy;
            $data['published_at'] = $ride->published_at?->toDateTimeString();
            $data['cancelled_at'] = $ride->cancelled_at?->toDateTimeString();
            $data['cancellation_reason'] = $ride->cancellation_reason;
            $data['stops'] = $ride->stops->map(fn ($stop) => [
                'type' => $stop->type,
                'name' => $stop->name,
                'lat' => (float) $stop->lat,
                'lng' => (float) $stop->lng,
                'stop_order' => $stop->stop_order,
            ]);
        }

        return $data;
    }
}
