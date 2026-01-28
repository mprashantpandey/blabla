<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Ride\SearchRidesRequest;
use App\Models\Ride;
use App\Models\SystemSetting;
use App\Services\GeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideController extends BaseController
{
    protected GeoService $geoService;

    public function __construct(GeoService $geoService)
    {
        $this->geoService = $geoService;
    }

    /**
     * Search rides (public).
     */
    public function search(SearchRidesRequest $request): JsonResponse
    {
        $query = Ride::with(['driverProfile.user', 'city', 'stops'])
            ->published()
            ->upcoming()
            ->inCity($request->city_id);

        // Date filter
        if ($request->has('date')) {
            $date = \Carbon\Carbon::parse($request->date);
            $query->whereDate('departure_at', $date->format('Y-m-d'));
        }

        // Seats filter
        if ($request->has('seats')) {
            $query->withAvailableSeats($request->seats);
        }

        // Price filter
        if ($request->has('min_price') || $request->has('max_price')) {
            $query->priceBetween($request->min_price, $request->max_price);
        }

        // Location-based search (optional)
        if ($request->has('origin_lat') && $request->has('origin_lng')) {
            $radiusKm = $request->get('radius_km', SystemSetting::get('rides.default_search_radius_km', 30));
            
            // Simple distance-based filtering (can be optimized with spatial indexes)
            $query->whereRaw(
                "(6371 * acos(cos(radians(?)) * cos(radians(origin_lat)) * cos(radians(origin_lng) - radians(?)) + sin(radians(?)) * sin(radians(origin_lat)))) <= ?",
                [$request->origin_lat, $request->origin_lng, $request->origin_lat, $radiusKm]
            );
        }

        $rides = $query->orderBy('departure_at', 'asc')
            ->paginate($request->get('per_page', 15));

        $data = $rides->map(fn ($ride) => $this->formatRideForSearch($ride));

        return $this->success($data, 'Rides retrieved successfully', 200, [
            'current_page' => $rides->currentPage(),
            'last_page' => $rides->lastPage(),
            'per_page' => $rides->perPage(),
            'total' => $rides->total(),
        ]);
    }

    /**
     * Get ride details (public).
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $ride = Ride::with(['driverProfile.user', 'driverProfile.vehicles', 'city', 'stops'])
            ->published()
            ->upcoming()
            ->find($id);

        if (!$ride) {
            return $this->error('Ride not found', [], 404);
        }

        // Record view
        $userId = $request->user()?->id;
        $ride->recordView($userId, $ride->city_id);

        return $this->success($this->formatRideDetail($ride), 'Ride details retrieved successfully');
    }

    /**
     * Record a view (optional analytics).
     */
    public function recordView(Request $request, int $id): JsonResponse
    {
        $ride = Ride::published()->find($id);

        if (!$ride) {
            return $this->error('Ride not found', [], 404);
        }

        $userId = $request->user()?->id;
        $ride->recordView($userId, $ride->city_id);

        return $this->success(null, 'View recorded');
    }

    /**
     * Format ride for search results.
     */
    protected function formatRideForSearch(Ride $ride): array
    {
        $driver = $ride->driverProfile->user;

        return [
            'id' => $ride->id,
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
            'seats_available' => $ride->seats_available,
            'seats_total' => $ride->seats_total,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                // 'rating' => null, // Phase 7
            ],
            'city' => $ride->city ? [
                'id' => $ride->city->id,
                'name' => $ride->city->name,
            ] : null,
        ];
    }

    /**
     * Format ride for detail view.
     */
    protected function formatRideDetail(Ride $ride): array
    {
        $driver = $ride->driverProfile->user;
        $primaryVehicle = $ride->driverProfile->vehicles()->where('is_primary', true)->first();

        $data = [
            'id' => $ride->id,
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
            'waypoints' => $ride->waypoints ?? [],
            'route_polyline' => $ride->route_polyline,
            'departure_at' => $ride->departure_at->toDateTimeString(),
            'arrival_estimated_at' => $ride->arrival_estimated_at?->toDateTimeString(),
            'price_per_seat' => (float) $ride->price_per_seat,
            'currency_code' => $ride->currency_code,
            'seats_total' => $ride->seats_total,
            'seats_available' => $ride->seats_available,
            'allow_instant_booking' => $ride->allow_instant_booking,
            'notes' => $ride->notes,
            'rules_json' => $ride->rules_json,
            'cancellation_policy' => $ride->cancellation_policy,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                // 'rating' => null, // Phase 7
            ],
            'vehicle' => $primaryVehicle ? [
                'id' => $primaryVehicle->id,
                'type' => $primaryVehicle->type,
                'make' => $primaryVehicle->make,
                'model' => $primaryVehicle->model,
                'year' => $primaryVehicle->year,
                'color' => $primaryVehicle->color,
                'seats_total' => $primaryVehicle->seats_total,
            ] : null,
            'city' => $ride->city ? [
                'id' => $ride->city->id,
                'name' => $ride->city->name,
            ] : null,
            'stops' => $ride->stops->map(fn ($stop) => [
                'type' => $stop->type,
                'name' => $stop->name,
                'lat' => (float) $stop->lat,
                'lng' => (float) $stop->lng,
                'stop_order' => $stop->stop_order,
            ]),
        ];

        return $data;
    }
}
