<?php

namespace App\Services;

class GeoService
{
    /**
     * Earth's radius in kilometers.
     */
    const EARTH_RADIUS_KM = 6371.0;

    /**
     * Calculate distance between two points using Haversine formula.
     */
    public function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * Check if a point is within a circle.
     */
    public function pointInCircle(float $lat, float $lng, float $centerLat, float $centerLng, float $radiusKm): bool
    {
        $distance = $this->distance($lat, $lng, $centerLat, $centerLng);
        return $distance <= $radiusKm;
    }

    /**
     * Check if a point is within a polygon using ray casting algorithm.
     * 
     * @param float $lat Point latitude
     * @param float $lng Point longitude
     * @param array $polygon Array of [lat, lng] or [lng, lat] points
     * @return bool
     */
    public function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        if (count($polygon) < 3) {
            return false;
        }

        // Normalize polygon points to [lat, lng] format
        $points = [];
        foreach ($polygon as $point) {
            if (isset($point['lat']) && isset($point['lng'])) {
                $points[] = [(float) $point['lat'], (float) $point['lng']];
            } elseif (is_array($point) && count($point) >= 2) {
                // Assume [lat, lng] or [lng, lat] - try to detect
                $points[] = [(float) $point[0], (float) $point[1]];
            }
        }

        if (count($points) < 3) {
            return false;
        }

        $inside = false;
        $j = count($points) - 1;

        for ($i = 0; $i < count($points); $i++) {
            $xi = $points[$i][0];
            $yi = $points[$i][1];
            $xj = $points[$j][0];
            $yj = $points[$j][1];

            $intersect = (($yi > $lat) !== ($yj > $lat)) &&
                         ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = !$inside;
            }

            $j = $i;
        }

        return $inside;
    }

    /**
     * Find nearest city to a point.
     */
    public function findNearestCity(float $lat, float $lng, ?int $maxDistanceKm = null): ?\App\Models\City
    {
        $cities = \App\Models\City::where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($cities as $city) {
            $distance = $this->distance($lat, $lng, (float) $city->latitude, (float) $city->longitude);
            
            if ($maxDistanceKm !== null && $distance > $maxDistanceKm) {
                continue;
            }

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = $city;
            }
        }

        return $nearest;
    }

    /**
     * Check if a point is serviceable for a city.
     */
    public function isPointServiceableForCity(int $cityId, float $lat, float $lng): array
    {
        $city = \App\Models\City::find($cityId);
        
        if (!$city || !$city->is_active) {
            return [
                'serviceable' => false,
                'reason' => 'City not found or inactive',
            ];
        }

        $serviceAreas = $city->activeServiceAreas()->get();

        // If no service areas configured
        if ($serviceAreas->isEmpty()) {
            $requireServiceArea = \App\Models\SystemSetting::get('locations.require_service_area', false);
            
            if ($requireServiceArea) {
                return [
                    'serviceable' => false,
                    'reason' => 'No service areas configured and service area is required',
                ];
            }

            // Fallback: allow if city has coordinates (within default radius)
            if ($city->latitude && $city->longitude) {
                $defaultRadius = (float) ($city->default_search_radius_km ?? 30.0);
                $distance = $this->distance($lat, $lng, (float) $city->latitude, (float) $city->longitude);
                
                return [
                    'serviceable' => $distance <= $defaultRadius,
                    'reason' => $distance <= $defaultRadius ? 'Within default radius' : 'Outside default radius',
                    'distance_km' => round($distance, 2),
                ];
            }

            return [
                'serviceable' => true,
                'reason' => 'No service areas configured, allowing all',
            ];
        }

        // Check against service areas
        foreach ($serviceAreas as $area) {
            if ($area->containsPoint($lat, $lng)) {
                return [
                    'serviceable' => true,
                    'matched_area' => [
                        'id' => $area->id,
                        'name' => $area->name,
                        'type' => $area->type,
                    ],
                ];
            }
        }

        return [
            'serviceable' => false,
            'reason' => 'Point not within any service area',
        ];
    }
}

