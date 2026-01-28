<?php

namespace Tests\Feature\Device;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/devices/register', [
            'device_id' => 'test-device-123',
            'platform' => 'android',
            'fcm_token' => 'test-fcm-token',
            'app_version' => '1.0.0',
            'device_model' => 'Pixel 5',
            'os_version' => 'Android 12',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'device_id' => 'test-device-123',
            'platform' => 'android',
            'fcm_token' => 'test-fcm-token',
        ]);
    }

    public function test_device_registration_updates_existing_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        UserDevice::create([
            'user_id' => $user->id,
            'device_id' => 'test-device-123',
            'platform' => 'android',
            'fcm_token' => 'old-token',
        ]);

        $response = $this->postJson('/api/v1/devices/register', [
            'device_id' => 'test-device-123',
            'platform' => 'android',
            'fcm_token' => 'new-token',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'device_id' => 'test-device-123',
            'fcm_token' => 'new-token',
        ]);

        $this->assertEquals(1, UserDevice::where('user_id', $user->id)->count());
    }

    public function test_user_can_unregister_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        UserDevice::create([
            'user_id' => $user->id,
            'device_id' => 'test-device-123',
            'platform' => 'android',
        ]);

        $response = $this->postJson('/api/v1/devices/unregister', [
            'device_id' => 'test-device-123',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('user_devices', [
            'user_id' => $user->id,
            'device_id' => 'test-device-123',
        ]);
    }
}
