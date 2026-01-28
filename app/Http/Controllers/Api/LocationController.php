<?php

namespace App\Http\Controllers\Api;

use App\Services\GeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends BaseController
{
    protected GeoService $geoService;

    public function __construct(GeoService $geoService)
    {
        $this->geoService = $geoService;
    }

    /**
     * Check if a location is serviceable for a city.
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'city_id' => 'required|exists:cities,id',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $cityId = (int) $request->city_id;
        $lat = (float) $request->lat;
        $lng = (float) $request->lng;

        $result = $this->geoService->isPointServiceableForCity($cityId, $lat, $lng);

        return $this->success($result, 'Location check completed');
    }
}
