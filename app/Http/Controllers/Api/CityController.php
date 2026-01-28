<?php

namespace App\Http\Controllers\Api;

use App\Models\City;
use App\Models\UserCityPreference;
use App\Services\GeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends BaseController
{
    protected GeoService $geoService;

    public function __construct(GeoService $geoService)
    {
        $this->geoService = $geoService;
    }

    /**
     * Get all active cities.
     */
    public function index(): JsonResponse
    {
        $cities = City::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($city) {
                return [
                    'id' => $city->id,
                    'name' => $city->name,
                    'slug' => $city->slug,
                    'country' => $city->country,
                    'state' => $city->state,
                    'currency_code' => $city->currency_code ?? $city->currency ?? 'USD',
                    'timezone' => $city->timezone,
                    'latitude' => $city->latitude,
                    'longitude' => $city->longitude,
                    'default_search_radius_km' => $city->default_search_radius_km ?? 30.0,
                ];
            });

        return $this->success($cities, 'Cities retrieved successfully');
    }

    /**
     * Get city by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $city = City::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$city) {
            return $this->error('City not found', [], 404);
        }

        $data = [
            'id' => $city->id,
            'name' => $city->name,
            'slug' => $city->slug,
            'country' => $city->country,
            'state' => $city->state,
            'currency_code' => $city->currency_code ?? $city->currency ?? 'USD',
            'timezone' => $city->timezone,
            'latitude' => $city->latitude,
            'longitude' => $city->longitude,
            'default_search_radius_km' => $city->default_search_radius_km ?? 30.0,
            'service_areas_count' => $city->activeServiceAreas()->count(),
        ];

        return $this->success($data, 'City retrieved successfully');
    }

    /**
     * Get service areas for a city.
     */
    public function serviceAreas(int $cityId): JsonResponse
    {
        $city = City::where('id', $cityId)
            ->where('is_active', true)
            ->first();

        if (!$city) {
            return $this->error('City not found', [], 404);
        }

        $serviceAreas = $city->activeServiceAreas()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($area) {
                $data = [
                    'id' => $area->id,
                    'name' => $area->name,
                    'type' => $area->type,
                    'is_active' => $area->is_active,
                ];

                if ($area->type === 'circle') {
                    $data['center'] = [
                        'lat' => $area->center_lat,
                        'lng' => $area->center_lng,
                    ];
                    $data['radius_km'] = $area->radius_km;
                } elseif ($area->type === 'polygon') {
                    $data['polygon'] = $area->polygon;
                }

                return $data;
            });

        return $this->success($serviceAreas, 'Service areas retrieved successfully');
    }

    /**
     * Resolve city from coordinates.
     */
    public function resolve(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $lat = (float) $request->lat;
        $lng = (float) $request->lng;

        $maxDistance = \App\Models\SystemSetting::get('locations.max_city_distance_km', 100);
        $nearestCity = $this->geoService->findNearestCity($lat, $lng, $maxDistance);

        if (!$nearestCity) {
            return $this->error('No city found within range', [], 404);
        }

        // Check if point is serviceable
        $serviceability = $this->geoService->isPointServiceableForCity($nearestCity->id, $lat, $lng);

        $data = [
            'city' => [
                'id' => $nearestCity->id,
                'name' => $nearestCity->name,
                'slug' => $nearestCity->slug,
                'country' => $nearestCity->country,
                'state' => $nearestCity->state,
                'currency_code' => $nearestCity->currency_code ?? $nearestCity->currency ?? 'USD',
                'timezone' => $nearestCity->timezone,
                'latitude' => $nearestCity->latitude,
                'longitude' => $nearestCity->longitude,
            ],
            'serviceable' => $serviceability['serviceable'],
            'serviceability_info' => $serviceability,
        ];

        // If user is authenticated, save preference
        if ($request->user()) {
            UserCityPreference::updateOrCreate(
                ['user_id' => $request->user()->id],
                [
                    'city_id' => $nearestCity->id,
                    'last_selected_at' => now(),
                ]
            );
        }

        return $this->success($data, 'City resolved successfully');
    }
}
