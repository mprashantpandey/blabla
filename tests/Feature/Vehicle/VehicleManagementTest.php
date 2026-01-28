<?php

namespace Tests\Feature\Vehicle;

use App\Models\User;
use App\Models\City;
use App\Models\DriverProfile;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_create_vehicle(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
            'status' => 'approved',
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/driver/vehicles', [
            'city_id' => $city->id,
            'type' => 'car',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2020,
            'color' => 'Red',
            'plate_number' => 'ABC123',
            'seats_total' => 4,
            'seats_available_default' => 3,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('vehicles', [
            'driver_profile_id' => $profile->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'is_primary' => true, // First vehicle is primary
        ]);
    }

    public function test_driver_can_upload_vehicle_photos(): void
    {
        Storage::fake('public');
        
        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
        ]);
        $vehicle = Vehicle::factory()->create([
            'driver_profile_id' => $profile->id,
            'city_id' => $city->id,
        ]);
        Sanctum::actingAs($user);

        $photos = [
            UploadedFile::fake()->image('photo1.jpg'),
            UploadedFile::fake()->image('photo2.jpg'),
        ];

        $response = $this->postJson("/api/v1/driver/vehicles/{$vehicle->id}/photos", [
            'photos' => $photos,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals(2, $vehicle->fresh()->getMedia('photos')->count());
    }

    public function test_driver_can_set_primary_vehicle(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
        ]);
        
        $vehicle1 = Vehicle::factory()->create([
            'driver_profile_id' => $profile->id,
            'city_id' => $city->id,
            'is_primary' => true,
        ]);
        
        $vehicle2 = Vehicle::factory()->create([
            'driver_profile_id' => $profile->id,
            'city_id' => $city->id,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/driver/vehicles/{$vehicle2->id}/set-primary");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertFalse($vehicle1->fresh()->is_primary);
        $this->assertTrue($vehicle2->fresh()->is_primary);
    }

    public function test_driver_can_update_vehicle(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
        ]);
        $vehicle = Vehicle::factory()->create([
            'driver_profile_id' => $profile->id,
            'city_id' => $city->id,
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/driver/vehicles/{$vehicle->id}", [
            'color' => 'Blue',
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals('Blue', $vehicle->fresh()->color);
        $this->assertFalse($vehicle->fresh()->is_active);
    }
}
