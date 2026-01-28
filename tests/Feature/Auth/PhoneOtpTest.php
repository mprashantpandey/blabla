<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Otp;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhoneOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Enable phone OTP and use custom SMS
        SystemSetting::set('auth.enable_phone_otp', true, 'boolean', 'auth');
        SystemSetting::set('auth.otp_provider', 'custom_sms', 'string', 'auth');
        SystemSetting::set('auth.otp_ttl_seconds', 300, 'integer', 'auth');
    }

    public function test_user_can_send_otp_for_login(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/send', [
            'phone' => '1234567890',
            'country_code' => '+1',
            'context' => 'login',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('otps', [
            'phone' => '+11234567890',
            'context' => 'login',
        ]);
    }

    public function test_user_can_verify_otp_and_login(): void
    {
        $user = User::factory()->create([
            'phone' => '+11234567890',
            'password' => Hash::make(uniqid()),
        ]);

        // Create OTP
        $code = '123456';
        $otp = Otp::create([
            'phone' => '+11234567890',
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'context' => 'login',
        ]);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '1234567890',
            'country_code' => '+1',
            'code' => $code,
            'context' => 'login',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'token',
                ],
            ]);

        // Verify OTP is marked as used
        $otp->refresh();
        $this->assertNotNull($otp->used_at);
    }

    public function test_otp_verification_fails_with_wrong_code(): void
    {
        $otp = Otp::create([
            'phone' => '+11234567890',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'context' => 'login',
        ]);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '1234567890',
            'country_code' => '+1',
            'code' => '000000',
            'context' => 'login',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }
}
