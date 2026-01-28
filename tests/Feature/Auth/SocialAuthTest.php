<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserIdentity;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        SystemSetting::set('auth.enable_social_google', true, 'boolean', 'auth');
        SystemSetting::set('auth.enable_social_apple', true, 'boolean', 'auth');
        SystemSetting::set('auth.social_auto_register', true, 'boolean', 'auth');
    }

    public function test_user_can_login_with_google(): void
    {
        // Mock Google token verification
        $mockVerifier = Mockery::mock(\App\Services\Auth\GoogleTokenVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->andReturn([
                'sub' => 'google-user-123',
                'email' => 'google@example.com',
                'email_verified' => true,
                'name' => 'Google User',
            ]);
        
        $this->app->instance(\App\Services\Auth\GoogleTokenVerifier::class, $mockVerifier);

        $response = $this->postJson('/api/v1/auth/social', [
            'provider' => 'google',
            'id_token' => 'mock-google-token',
            'email' => 'google@example.com',
            'name' => 'Google User',
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

        $this->assertDatabaseHas('users', [
            'email' => 'google@example.com',
            'auth_provider' => 'google',
        ]);

        $this->assertDatabaseHas('user_identities', [
            'provider' => 'google',
            'provider_user_id' => 'google-user-123',
        ]);
    }

    public function test_existing_google_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'google@example.com',
            'auth_provider' => 'google',
        ]);

        UserIdentity::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-user-123',
        ]);

        $mockVerifier = Mockery::mock(\App\Services\Auth\GoogleTokenVerifier::class);
        $mockVerifier->shouldReceive('verify')
            ->once()
            ->andReturn([
                'sub' => 'google-user-123',
                'email' => 'google@example.com',
            ]);
        
        $this->app->instance(\App\Services\Auth\GoogleTokenVerifier::class, $mockVerifier);

        $response = $this->postJson('/api/v1/auth/social', [
            'provider' => 'google',
            'id_token' => 'mock-google-token',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
