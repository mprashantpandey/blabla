<?php

namespace Tests\Feature\Driver;

use App\Models\User;
use App\Models\City;
use App\Models\DriverProfile;
use App\Models\CityAdminAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_driver(): void
    {
        Storage::fake('public');
        
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/admin/drivers/{$profile->id}/approve", [
            'admin_note' => 'Approved after verification',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals('approved', $profile->fresh()->status);
        $this->assertNotNull($profile->fresh()->verified_at);
    }

    public function test_admin_can_reject_driver(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/admin/drivers/{$profile->id}/reject", [
            'reason' => 'Documents not clear',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals('rejected', $profile->fresh()->status);
        $this->assertEquals('Documents not clear', $profile->fresh()->rejected_reason);
    }

    public function test_city_admin_cannot_approve_driver_outside_assigned_city(): void
    {
        $cityAdmin = User::factory()->create();
        $cityAdmin->assignRole('City Admin');

        $assignedCity = City::factory()->create(['is_active' => true]);
        $otherCity = City::factory()->create(['is_active' => true]);

        CityAdminAssignment::create([
            'user_id' => $cityAdmin->id,
            'city_id' => $assignedCity->id,
            'role_scope' => 'city_admin',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $profile = DriverProfile::factory()->create([
            'user_id' => $user->id,
            'city_id' => $otherCity->id, // Different city
            'status' => 'pending',
        ]);

        Sanctum::actingAs($cityAdmin);

        $response = $this->postJson("/api/v1/admin/drivers/{$profile->id}/approve");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_city_admin_can_approve_driver_in_assigned_city(): void
    {
        $cityAdmin = User::factory()->create();
        $cityAdmin->assignRole('City Admin');

        $assignedCity = City::factory()->create(['is_active' => true]);

        CityAdminAssignment::create([
            'user_id' => $cityAdmin->id,
            'city_id' => $assignedCity->id,
            'role_scope' => 'city_admin',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $profile = DriverProfile::factory()->create([
            'user_id' => $user->id,
            'city_id' => $assignedCity->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($cityAdmin);

        $response = $this->postJson("/api/v1/admin/drivers/{$profile->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_admin_can_approve_document(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
        ]);

        $document = \App\Models\DriverDocument::factory()->create([
            'driver_profile_id' => $profile->id,
            'key' => 'license',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/admin/driver-documents/{$document->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals('approved', $document->fresh()->status);
    }

    public function test_admin_can_reject_document(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
        ]);

        $document = \App\Models\DriverDocument::factory()->create([
            'driver_profile_id' => $profile->id,
            'key' => 'license',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/admin/driver-documents/{$document->id}/reject", [
            'reason' => 'Document is blurry',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals('rejected', $document->fresh()->status);
        $this->assertEquals('Document is blurry', $document->fresh()->rejection_reason);
    }
}
