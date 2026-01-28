<?php

namespace Tests\Feature\Driver;

use App\Models\User;
use App\Models\City;
use App\Models\DriverProfile;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup driver settings
        SystemSetting::set('driver.enabled', true, 'boolean', 'driver');
        SystemSetting::set('driver.require_verification', true, 'boolean', 'driver');
        SystemSetting::set('driver.min_age_years', 18, 'integer', 'driver');
        SystemSetting::set('driver.require_selfie', true, 'boolean', 'driver');
        SystemSetting::set('driver.required_documents', json_encode([
            ['key' => 'license', 'label' => 'Driving License', 'required' => true],
            ['key' => 'id_card', 'label' => 'Government ID', 'required' => true],
        ]), 'string', 'driver');
    }

    public function test_user_can_apply_to_become_driver(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/driver/apply', [
            'city_id' => $city->id,
            'dob' => '1990-01-01',
            'address' => '123 Main St',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('driver_profiles', [
            'user_id' => $user->id,
            'city_id' => $city->id,
            'status' => 'pending',
        ]);
    }

    public function test_apply_fails_if_below_min_age(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $minAge = SystemSetting::get('driver.min_age_years', 18);
        $tooYoungDate = now()->subYears($minAge - 1)->format('Y-m-d');

        $response = $this->postJson('/api/v1/driver/apply', [
            'city_id' => $city->id,
            'dob' => $tooYoungDate,
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_upload_selfie(): void
    {
        Storage::fake('public');
        
        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
        ]);
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('selfie.jpg');

        $response = $this->postJson('/api/v1/driver/selfie', [
            'selfie' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertTrue($profile->fresh()->hasSelfie());
    }

    public function test_user_can_upload_document(): void
    {
        Storage::fake('public');
        
        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
        ]);
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('license.jpg');

        $response = $this->postJson('/api/v1/driver/documents/license', [
            'file' => $file,
            'document_number' => 'DL123456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('driver_documents', [
            'driver_profile_id' => $profile->id,
            'key' => 'license',
            'status' => 'pending',
        ]);
    }

    public function test_submit_fails_if_required_docs_missing(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
            'status' => 'not_applied',
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/driver/submit');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonHasPath('errors.missing_documents');
    }

    public function test_submit_succeeds_with_all_required_docs(): void
    {
        Storage::fake('public');
        
        $user = User::factory()->create();
        $city = City::factory()->create(['is_active' => true]);
        $profile = DriverProfile::factory()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
            'status' => 'not_applied',
        ]);
        Sanctum::actingAs($user);

        // Upload selfie
        $selfie = UploadedFile::fake()->image('selfie.jpg');
        $this->postJson('/api/v1/driver/selfie', ['selfie' => $selfie]);

        // Upload required documents
        $license = UploadedFile::fake()->image('license.jpg');
        $this->postJson('/api/v1/driver/documents/license', ['file' => $license]);

        $idCard = UploadedFile::fake()->image('id.jpg');
        $this->postJson('/api/v1/driver/documents/id_card', ['file' => $idCard]);

        $response = $this->postJson('/api/v1/driver/submit');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals('pending', $profile->fresh()->status);
        $this->assertNotNull($profile->fresh()->applied_at);
    }
}
