<?php

namespace Tests\Feature\Location;

use App\Models\City;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_cities_returns_active_sorted(): void
    {
        City::factory()->create(['name' => 'City B', 'is_active' => true, 'sort_order' => 2]);
        City::factory()->create(['name' => 'City A', 'is_active' => true, 'sort_order' => 1]);
        City::factory()->create(['name' => 'City C', 'is_active' => false, 'sort_order' => 0]);

        $response = $this->getJson('/api/v1/cities');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'country',
                        'currency_code',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('City A', $data[0]['name']);
        $this->assertEquals('City B', $data[1]['name']);
    }

    public function test_get_city_by_slug(): void
    {
        $city = City::factory()->create([
            'name' => 'New York',
            'slug' => 'new-york',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/cities/new-york');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $city->id,
                    'slug' => 'new-york',
                ],
            ]);
    }

    public function test_resolve_city_finds_nearest(): void
    {
        // Create cities with known coordinates
        $city1 = City::factory()->create([
            'name' => 'New York',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'is_active' => true,
        ]);

        $city2 = City::factory()->create([
            'name' => 'Los Angeles',
            'latitude' => 34.0522,
            'longitude' => -118.2437,
            'is_active' => true,
        ]);

        SystemSetting::set('locations.max_city_distance_km', 1000, 'integer', 'locations');

        // Point near New York
        $response = $this->postJson('/api/v1/cities/resolve', [
            'lat' => 40.7580,
            'lng' => -73.9855,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.city.id', $city1->id);
    }

    public function test_resolve_city_respects_max_distance(): void
    {
        City::factory()->create([
            'name' => 'New York',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'is_active' => true,
        ]);

        SystemSetting::set('locations.max_city_distance_km', 10, 'integer', 'locations');

        // Point far from New York
        $response = $this->postJson('/api/v1/cities/resolve', [
            'lat' => 34.0522,
            'lng' => -118.2437,
        ]);

        $response->assertStatus(404);
    }

    public function test_get_service_areas_for_city(): void
    {
        $city = City::factory()->create(['is_active' => true]);
        $serviceArea = \App\Models\ServiceArea::factory()->create([
            'city_id' => $city->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/cities/{$city->id}/service-areas");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data');
    }
}
