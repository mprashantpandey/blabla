<?php

namespace Tests\Feature\Ride;

use App\Models\User;
use App\Models\City;
use App\Models\DriverProfile;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RideCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup ride settings
        SystemSetting::set('rides.enabled', true, 'boolean', 'rides');
        SystemSetting::set('rides.allow_draft', true, 'boolean', 'rides');
        SystemSetting::set('rides.min_hours_before_departure', 1, 'integer', 'rides');
        SystemSetting::set('rides.max_days_in_future', 60, 'integer', 'rides');
        SystemSetting::set('rides.max_seats', 8, 'integer', 'rides');
    }

    public function test_approved_driver_can_create_ride(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->approved()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/driver/rides', [
            'city_id' => $city->id,
            'origin_name' => 'Origin Address',
            'origin_lat' => 40.7128,
            'origin_lng' => -74.0060,
            'destination_name' => 'Destination Address',
            'destination_lat' => 40.7580,
            'destination_lng' => -73.9855,
            'departure_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'price_per_seat' => 25.00,
            'seats_total' => 4,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('rides', [
            'driver_profile_id' => $profile->id,
            'city_id' => $city->id,
            'status' => 'draft',
            'seats_available' => 4,
        ]);
    }

    public function test_non_approved_driver_cannot_create_ride(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->pending()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/driver/rides', [
            'city_id' => $city->id,
            'origin_name' => 'Origin',
            'origin_lat' => 40.7128,
            'origin_lng' => -74.0060,
            'destination_name' => 'Destination',
            'destination_lat' => 40.7580,
            'destination_lng' => -73.9855,
            'departure_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'price_per_seat' => 25.00,
            'seats_total' => 4,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_publish_validates_time_window(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->approved()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
        ]);
        Sanctum::actingAs($user);

        $ride = \App\Models\Ride::factory()->create([
            'driver_profile_id' => $profile->id,
            'city_id' => $city->id,
            'status' => 'draft',
            'departure_at' => now()->addMinutes(30), // Less than 1 hour
        ]);

        $response = $this->postJson("/api/v1/driver/rides/{$ride->id}/publish");

        $response->assertStatus(422);
    }

    public function test_seat_reservation_is_atomic(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->approved()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
        ]);
        Sanctum::actingAs($user);

        $ride = \App\Models\Ride::factory()->published()->create([
            'driver_profile_id' => $profile->id,
            'city_id' => $city->id,
            'seats_total' => 4,
            'seats_available' => 2,
        ]);

        // Simulate concurrent reservations
        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $results[] = $ride->reserveSeats(1);
        }

        // Only 2 should succeed (2 seats available)
        $successCount = count(array_filter($results));
        $this->assertEquals(2, $successCount);
        $this->assertEquals(0, $ride->fresh()->seats_available);
    }
}
