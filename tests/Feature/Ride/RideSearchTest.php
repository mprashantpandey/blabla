<?php

namespace Tests\Feature\Ride;

use App\Models\City;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RideSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_only_published_upcoming_rides(): void
    {
        $city = City::factory()->create(['is_active' => true]);
        $driver = DriverProfile::factory()->approved()->create(['city_id' => $city->id]);

        // Create various rides
        Ride::factory()->published()->upcoming()->create([
            'driver_profile_id' => $driver->id,
            'city_id' => $city->id,
        ]);
        Ride::factory()->create(['driver_profile_id' => $driver->id, 'city_id' => $city->id, 'status' => 'draft']); // Draft
        Ride::factory()->published()->create([
            'driver_profile_id' => $driver->id,
            'city_id' => $city->id,
            'departure_at' => now()->subHour(), // Past
        ]);

        $response = $this->getJson("/api/v1/rides/search?city_id={$city->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('published', $data[0]['status']);
    }

    public function test_search_filters_by_date(): void
    {
        $city = City::factory()->create(['is_active' => true]);
        $driver = DriverProfile::factory()->approved()->create(['city_id' => $city->id]);
        $date = now()->addDays(5)->format('Y-m-d');

        Ride::factory()->published()->create([
            'driver_profile_id' => $driver->id,
            'city_id' => $city->id,
            'departure_at' => $date . ' 10:00:00',
        ]);
        Ride::factory()->published()->create([
            'driver_profile_id' => $driver->id,
            'city_id' => $city->id,
            'departure_at' => now()->addDays(6)->format('Y-m-d') . ' 10:00:00',
        ]);

        $response = $this->getJson("/api/v1/rides/search?city_id={$city->id}&date={$date}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_search_filters_by_seats(): void
    {
        $city = City::factory()->create(['is_active' => true]);
        $driver = DriverProfile::factory()->approved()->create(['city_id' => $city->id]);

        Ride::factory()->published()->upcoming()->create([
            'driver_profile_id' => $driver->id,
            'city_id' => $city->id,
            'seats_available' => 3,
        ]);
        Ride::factory()->published()->upcoming()->create([
            'driver_profile_id' => $driver->id,
            'city_id' => $city->id,
            'seats_available' => 1,
        ]);

        $response = $this->getJson("/api/v1/rides/search?city_id={$city->id}&seats=2");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(3, $data[0]['seats_available']);
    }

    public function test_search_filters_by_price(): void
    {
        $city = City::factory()->create(['is_active' => true]);
        $driver = DriverProfile::factory()->approved()->create(['city_id' => $city->id]);

        Ride::factory()->published()->upcoming()->create([
            'driver_profile_id' => $driver->id,
            'city_id' => $city->id,
            'price_per_seat' => 25.00,
        ]);
        Ride::factory()->published()->upcoming()->create([
            'driver_profile_id' => $driver->id,
            'city_id' => $city->id,
            'price_per_seat' => 50.00,
        ]);

        $response = $this->getJson("/api/v1/rides/search?city_id={$city->id}&min_price=20&max_price=30");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(25.00, $data[0]['price_per_seat']);
    }
}
