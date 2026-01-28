<?php

namespace Tests\Feature\Location;

use App\Models\City;
use App\Models\ServiceArea;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_location_within_circle_service_area(): void
    {
        $city = City::factory()->create([
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'is_active' => true,
        ]);

        ServiceArea::factory()->create([
            'city_id' => $city->id,
            'type' => 'circle',
            'center_lat' => 40.7128,
            'center_lng' => -74.0060,
            'radius_km' => 10.0,
            'is_active' => true,
        ]);

        // Point within circle
        $response = $this->postJson('/api/v1/location/check', [
            'city_id' => $city->id,
            'lat' => 40.7200,
            'lng' => -74.0100,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'serviceable' => true,
                ],
            ])
            ->assertJsonHasPath('data.matched_area');
    }

    public function test_check_location_outside_circle_service_area(): void
    {
        $city = City::factory()->create([
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'is_active' => true,
        ]);

        ServiceArea::factory()->create([
            'city_id' => $city->id,
            'type' => 'circle',
            'center_lat' => 40.7128,
            'center_lng' => -74.0060,
            'radius_km' => 1.0, // Small radius
            'is_active' => true,
        ]);

        // Point far from center
        $response = $this->postJson('/api/v1/location/check', [
            'city_id' => $city->id,
            'lat' => 40.8000,
            'lng' => -74.1000,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'serviceable' => false,
                ],
            ]);
    }

    public function test_check_location_with_polygon_service_area(): void
    {
        $city = City::factory()->create(['is_active' => true]);

        // Create a simple square polygon
        $polygon = [
            ['lat' => 40.7000, 'lng' => -74.0100],
            ['lat' => 40.7000, 'lng' => -74.0000],
            ['lat' => 40.7100, 'lng' => -74.0000],
            ['lat' => 40.7100, 'lng' => -74.0100],
        ];

        ServiceArea::factory()->create([
            'city_id' => $city->id,
            'type' => 'polygon',
            'polygon' => $polygon,
            'is_active' => true,
        ]);

        // Point inside polygon
        $response = $this->postJson('/api/v1/location/check', [
            'city_id' => $city->id,
            'lat' => 40.7050,
            'lng' => -74.0050,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'serviceable' => true,
                ],
            ]);
    }

    public function test_check_location_fallback_to_default_radius(): void
    {
        SystemSetting::set('locations.require_service_area', false, 'boolean', 'locations');

        $city = City::factory()->create([
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'default_search_radius_km' => 30.0,
            'is_active' => true,
        ]);

        // No service areas configured
        // Point within default radius
        $response = $this->postJson('/api/v1/location/check', [
            'city_id' => $city->id,
            'lat' => 40.7200,
            'lng' => -74.0100,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'serviceable' => true,
                ],
            ]);
    }
}
