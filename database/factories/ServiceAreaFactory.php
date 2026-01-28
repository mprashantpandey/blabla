<?php

namespace Database\Factories;

use App\Models\ServiceArea;
use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceAreaFactory extends Factory
{
    protected $model = ServiceArea::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['circle', 'polygon']);
        $centerLat = $this->faker->latitude();
        $centerLng = $this->faker->longitude();

        return [
            'city_id' => City::factory(),
            'name' => $this->faker->streetName(),
            'type' => $type,
            'center_lat' => $type === 'circle' ? $centerLat : null,
            'center_lng' => $type === 'circle' ? $centerLng : null,
            'radius_km' => $type === 'circle' ? $this->faker->randomFloat(2, 1, 50) : null,
            'polygon' => $type === 'polygon' ? $this->generatePolygon($centerLat, $centerLng) : null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    protected function generatePolygon(float $centerLat, float $centerLng): array
    {
        // Generate a simple square polygon around center
        $offset = 0.01; // ~1km
        
        return [
            ['lat' => $centerLat - $offset, 'lng' => $centerLng - $offset],
            ['lat' => $centerLat - $offset, 'lng' => $centerLng + $offset],
            ['lat' => $centerLat + $offset, 'lng' => $centerLng + $offset],
            ['lat' => $centerLat + $offset, 'lng' => $centerLng - $offset],
        ];
    }
}
